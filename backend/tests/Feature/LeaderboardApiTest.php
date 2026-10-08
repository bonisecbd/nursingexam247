<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_leaderboard_sums_best_percentage_per_test_and_applies_deterministic_ties(): void
    {
        CarbonImmutable::setTestNow('2026-10-08 12:00:00 UTC');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $testOne = $this->createTest($subject, $author);
        $testTwo = $this->createTest($subject, $author);
        $first = $this->createStudent('First Place');
        $second = $this->createStudent('Second Place');

        $this->createAttempt($first, $testOne, 90, '2026-10-08 07:00:00 UTC');
        $this->createAttempt($first, $testOne, 70, '2026-10-08 08:00:00 UTC');
        $this->createAttempt($first, $testTwo, 80, '2026-10-08 09:00:00 UTC');
        $this->createAttempt($second, $testOne, 90, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($second, $testTwo, 80, '2026-10-08 10:00:00 UTC');

        $this->getJson('/api/leaderboards?period=daily&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.timezone', 'Asia/Dhaka')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.display_name', 'First Place')
            ->assertJsonPath('data.0.score', 170)
            ->assertJsonPath('data.0.eligible_test_count', 2)
            ->assertJsonMissingPath('data.0.achieved_at')
            ->assertJsonMissingPath('data.0.email');

        $firstToken = $this->createToken($first);
        $this->withToken($firstToken)->getJson('/api/leaderboards/daily/me')
            ->assertOk()
            ->assertJsonPath('leaderboard_opt_in', true)
            ->assertJsonPath('position.rank', 1)
            ->assertJsonPath('position.score', 170);

        $this->withToken($this->createToken($second))->getJson('/api/leaderboards/daily/me')
            ->assertOk()
            ->assertJsonPath('position.rank', 2)
            ->assertJsonPath('position.score', 170);
    }

    public function test_daily_ranking_uses_dhaka_calendar_boundaries_and_excludes_ineligible_attempts(): void
    {
        CarbonImmutable::setTestNow('2026-10-08 12:00:00 UTC');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $author = User::factory()->create(['role' => 'admin']);
        $test = $this->createTest($subject, $author);
        $eligible = $this->createStudent('Eligible Student');
        $optedOut = User::factory()->create(['role' => 'student']);
        $inactive = $this->createStudent('Inactive Student', active: false);
        $testAuthor = $this->createStudent('Test Author');
        $createdTest = $this->createTest($subject, $testAuthor);

        $this->createAttempt($eligible, $test, 80, '2026-10-07 17:59:59 UTC');
        $this->createAttempt($eligible, $test, 75, '2026-10-07 18:00:00 UTC');
        $this->createAttempt($eligible, $test, 85, '2026-10-08 17:59:59 UTC');
        $this->createAttempt($eligible, $test, 100, '2026-10-08 18:00:00 UTC');
        $this->createAttempt($optedOut, $test, 99, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($inactive, $test, 98, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($testAuthor, $createdTest, 100, '2026-10-08 10:00:00 UTC');
        $this->createAttempt($eligible, $test, null, '2026-10-08 11:00:00 UTC', 'in_progress');

        $this->getJson('/api/leaderboards?period=daily')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Eligible Student')
            ->assertJsonPath('data.0.score', 85)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/leaderboards?period=yearly')->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_opt_out_students_receive_no_rank_and_period_me_endpoint_requires_authentication(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->createToken($student);

        $this->getJson('/api/leaderboards/monthly/me')->assertUnauthorized();

        $this->withToken($token)->getJson('/api/leaderboards/monthly/me')
            ->assertOk()
            ->assertJsonPath('leaderboard_opt_in', false)
            ->assertJsonPath('position.rank', null)
            ->assertJsonPath('position.eligible', false);

        $this->withToken($token)->getJson('/api/leaderboards/yearly/me')->assertNotFound();
    }

    public function test_weekly_monthly_and_overall_periods_use_their_dhaka_boundaries(): void
    {
        CarbonImmutable::setTestNow('2026-10-08 12:00:00 UTC');
        $subject = Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        $test = $this->createTest($subject, User::factory()->create(['role' => 'admin']));
        $student = $this->createStudent('Period Student');

        $this->createAttempt($student, $test, 70, '2026-09-30 17:59:59 UTC');
        $this->createAttempt($student, $test, 90, '2026-09-30 18:00:00 UTC');
        $this->createAttempt($student, $test, 75, '2026-10-04 17:59:59 UTC');
        $this->createAttempt($student, $test, 80, '2026-10-04 18:00:00 UTC');

        $this->getJson('/api/leaderboards?period=weekly')->assertOk()
            ->assertJsonPath('data.0.score', 80);
        $this->getJson('/api/leaderboards?period=monthly')->assertOk()
            ->assertJsonPath('data.0.score', 90);
        $this->getJson('/api/leaderboards?period=overall')->assertOk()
            ->assertJsonPath('data.0.score', 90);
    }

    private function createStudent(string $name, bool $active = true): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'is_active' => $active,
            'leaderboard_opt_in' => true,
            'email' => Str::lower(Str::random(12)).'@example.com',
        ]);
    }

    private function createToken(User $user): string
    {
        $token = Str::random(64);
        $user->apiTokens()->create([
            'name' => 'Leaderboard test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }

    private function createTest(Subject $subject, User $author): ModelTest
    {
        return ModelTest::query()->create([
            'title' => 'Nursing Practice',
            'code' => 'TST'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 30,
            'question_count' => 1,
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
            'duration_seconds' => $status === 'in_progress' ? 0 : 300,
            'score' => $percentage === null ? null : $percentage / 10,
            'total_marks' => 10,
            'percentage' => $percentage,
            'correct_count' => 1,
            'incorrect_count' => 0,
            'unanswered_count' => 0,
            'passed' => $percentage === null ? null : $percentage >= 50,
        ]);
    }
}
