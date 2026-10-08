<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_aggregates_only_the_students_finalized_attempts(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $testOne = $this->createTest($subject, $author, 'Practice 01');
        $this->createTest($subject, $author, 'Practice 02');

        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);

        // Another student's results must never leak into this overview.
        $other = User::factory()->create(['role' => 'student']);
        $this->createAttempt($other, $testOne, 95, 10, 0, 0, '2026-10-08 09:00:00 UTC');

        $this->createAttempt($student, $testOne, 80, 4, 1, 0, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($student, $testOne, 60, 3, 2, 0, '2026-10-08 11:00:00 UTC');
        // In progress attempts are excluded from every aggregate.
        $this->createAttempt($student, $testOne, null, 0, 0, 0, '2026-10-08 12:00:00 UTC', 'in_progress');

        $this->withToken($token)
            ->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonPath('total_tests', 2)
            ->assertJsonPath('test_count', 1)
            ->assertJsonPath('attempt_count', 2)
            ->assertJsonPath('in_progress_count', 1)
            ->assertJsonPath('average_score', 70)
            ->assertJsonPath('last_score', 60)
            ->assertJsonPath('accuracy_rate', 70)
            ->assertJsonPath('completion_rate', 50);
    }

    public function test_overview_returns_zeroed_stats_for_a_student_without_attempts(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $this->createTest($subject, $author, 'Practice 01');

        $token = $this->createToken(User::factory()->create(['role' => 'student']));

        $this->withToken($token)
            ->getJson('/api/analytics/overview')
            ->assertOk()
            ->assertJsonPath('total_tests', 1)
            ->assertJsonPath('test_count', 0)
            ->assertJsonPath('attempt_count', 0)
            ->assertJsonPath('average_score', 0)
            ->assertJsonPath('last_score', null)
            ->assertJsonPath('accuracy_rate', 0)
            ->assertJsonPath('completion_rate', 0);
    }

    public function test_analytics_endpoints_require_authentication(): void
    {
        $this->getJson('/api/analytics/overview')->assertUnauthorized();
        $this->getJson('/api/analytics/subjects')->assertUnauthorized();
        $this->getJson('/api/analytics/topics')->assertUnauthorized();
    }

    public function test_subject_analysis_aggregates_attempts_per_subject(): void
    {
        $nursing = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $english = Subject::query()->create(['name' => 'English', 'code' => 'ENG']);
        $author = User::factory()->create(['role' => 'admin']);
        $nursingTest = $this->createTest($nursing, $author, 'Nursing Practice');
        $englishTest = $this->createTest($english, $author, 'English Practice');

        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);

        $this->createAttempt($student, $nursingTest, 90, 9, 1, 0, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($student, $nursingTest, 70, 7, 3, 0, '2026-10-08 11:00:00 UTC');
        $this->createAttempt($student, $englishTest, 100, 10, 0, 0, '2026-10-08 12:00:00 UTC');

        $response = $this->withToken($token)
            ->getJson('/api/analytics/subjects')
            ->assertOk()
            ->assertJsonPath('meta.subject_count', 2);

        $bySubject = collect($response->json('data'))->keyBy('subject_name');

        $nursingStats = $bySubject->get('Nursing');
        $this->assertSame(2, $nursingStats['attempt_count']);
        $this->assertSame(1, $nursingStats['test_count']);
        $this->assertSame(80, $nursingStats['average_percentage']);
        $this->assertSame(90, $nursingStats['best_percentage']);
        $this->assertSame(80, $nursingStats['accuracy_rate']);

        $englishStats = $bySubject->get('English');
        $this->assertSame(1, $englishStats['attempt_count']);
        $this->assertSame(100, $englishStats['accuracy_rate']);
        $this->assertSame(100, $englishStats['pass_rate']);
    }

    public function test_topic_analysis_ranks_weakest_topics_from_saved_answers(): void
    {
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $test = $this->createTest($subject, $author, 'Nursing Practice');

        $airway = Topic::query()->create(['subject_id' => $subject->id, 'name' => 'Airway']);
        $injection = Topic::query()->create(['subject_id' => $subject->id, 'name' => 'Injection']);
        $unassignedQuestion = Question::query()->create($this->questionPayload($subject->id, null, 'No topic question'));

        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);
        $attempt = $this->createAttempt($student, $test, 50, 1, 1, 1, '2026-10-08 10:00:00 UTC');

        $airwayQuestion = Question::query()->create($this->questionPayload($subject->id, $airway->id, 'Airway question'));
        $injectionQuestion = Question::query()->create($this->questionPayload($subject->id, $injection->id, 'Injection question'));
        $pharmacology = Topic::query()->create(['subject_id' => $subject->id, 'name' => 'Pharmacology']);
        $pharmacologyQuestion = Question::query()->create($this->questionPayload($subject->id, $pharmacology->id, 'Pharmacology question'));

        // Airway: 5 answers with 1 correct => 20% and a sufficient sample => weak.
        // Injection: 1 correct => 100%.
        // Pharmacology: 2 wrong => 0%, but below the minimum sample, so it must
        // NOT be flagged weak (MODULES.md: no weakness without enough evidence).
        $this->createAnswer($attempt, 1, $airwayQuestion, 1);
        $this->createAnswer($attempt, 2, $airwayQuestion, 2);
        $this->createAnswer($attempt, 3, $airwayQuestion, 2);
        $this->createAnswer($attempt, 4, $airwayQuestion, 2);
        $this->createAnswer($attempt, 5, $airwayQuestion, 2);
        $this->createAnswer($attempt, 6, $injectionQuestion, 1);
        $this->createAnswer($attempt, 7, $pharmacologyQuestion, 2);
        $this->createAnswer($attempt, 8, $pharmacologyQuestion, 3);

        $response = $this->withToken($token)
            ->getJson('/api/analytics/topics')
            ->assertOk()
            ->assertJsonPath('meta.topic_count', 3)
            ->assertJsonPath('meta.weak_threshold', 50)
            ->assertJsonPath('meta.min_sample', 5);

        $rows = collect($response->json('data'))->keyBy('topic_id');
        $airwayStats = $rows->get($airway->id);

        $this->assertSame(5, $airwayStats['answered_count']);
        $this->assertSame(5, $airwayStats['attempted_count']);
        $this->assertSame(0, $airwayStats['skipped_count']);
        $this->assertSame(1, $airwayStats['correct_count']);
        $this->assertSame(4, $airwayStats['incorrect_count']);
        $this->assertSame(20, $airwayStats['accuracy_rate']);
        $this->assertTrue($airwayStats['is_weak']);

        $injectionStats = $rows->get($injection->id);
        $this->assertSame(100, $injectionStats['accuracy_rate']);
        $this->assertFalse($injectionStats['is_weak']);

        // A perfect score is not weak, and a 0% topic with only two answers is
        // an insufficient sample rather than a weakness.
        $pharmacologyStats = $rows->get($pharmacology->id);
        $this->assertSame(2, $pharmacologyStats['answered_count']);
        $this->assertSame(0, $pharmacologyStats['accuracy_rate']);
        $this->assertFalse($pharmacologyStats['is_weak']);

        // Weakest topic first for the dashboard's study recommendations.
        $topicIds = collect($response->json('data'))->pluck('topic_id');
        $this->assertSame([$pharmacology->id, $airway->id, $injection->id], $topicIds->all());

        // Questions without a topic never appear in topic analytics.
        $this->assertNotNull($unassignedQuestion->id);

        // Another student sees no data at all.
        $otherToken = $this->createToken(User::factory()->create(['role' => 'student']));
        $this->withToken($otherToken)
            ->getJson('/api/analytics/topics')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function questionPayload(int $subjectId, ?int $topicId, string $text): array
    {
        return [
            'subject_id' => $subjectId,
            'topic_id' => $topicId,
            'question_text' => $text,
            'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_option' => 1,
            'difficulty' => 'medium',
        ];
    }

    private function createAnswer(ExamAttempt $attempt, int $sequence, Question $question, ?int $selected): void
    {
        AttemptAnswer::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'sequence' => $sequence,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'correct_option' => $question->correct_option,
            'explanation' => $question->explanation,
            'points' => 1,
            'selected_option' => $selected,
            'answered_at' => $selected === null ? null : now(),
        ]);
    }

    private function createTest(Subject $subject, User $author, string $title): ModelTest
    {
        return ModelTest::query()->create([
            'title' => $title,
            'code' => Str::upper(Str::random(10)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 10,
            'total_marks' => 10,
            'passing_score' => 5,
            'status' => 'published',
            'created_by' => $author->id,
        ]);
    }

    private function createAttempt(
        User $student,
        ModelTest $test,
        ?float $percentage,
        int $correct,
        int $incorrect,
        int $unanswered,
        string $finishedAt,
        string $status = 'submitted',
    ): ExamAttempt {
        return ExamAttempt::query()->create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => $status,
            'started_at' => CarbonImmutable::parse($finishedAt)->subMinutes(10),
            'expires_at' => CarbonImmutable::parse($finishedAt)->addMinutes(20),
            'finished_at' => $status === 'in_progress' ? null : CarbonImmutable::parse($finishedAt),
            'duration_seconds' => $status === 'in_progress' ? 0 : 600,
            'score' => $percentage === null ? null : $percentage / 10,
            'total_marks' => 10,
            'percentage' => $percentage,
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'unanswered_count' => $unanswered,
            'passed' => $percentage === null ? null : $percentage >= 50,
        ]);
    }

    private function createToken(User $user): string
    {
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Analytics test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }
}
