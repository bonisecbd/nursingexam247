<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamAttemptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_start_resume_navigate_and_autosave_answers(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();

        $start = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ]);

        $start->assertCreated()
            ->assertJsonPath('attempt.status', 'in_progress')
            ->assertJsonPath('attempt.time_remaining_seconds', 600)
            ->assertJsonMissingPath('attempt.questions.0.correct_option')
            ->assertJsonMissingPath('attempt.questions.0.explanation');

        $attemptId = $start->json('attempt.id');
        $firstQuestionId = $start->json('attempt.questions.0.id');
        $secondQuestionId = $start->json('attempt.questions.1.id');

        $this->withToken($token)->getJson("/api/attempts/{$attemptId}/questions/{$secondQuestionId}")
            ->assertOk()
            ->assertJsonPath('question.previous_question_id', $firstQuestionId);
        $this->withToken($token)->getJson("/api/attempts/{$attemptId}/questions/{$firstQuestionId}")
            ->assertOk()
            ->assertJsonPath('question.next_question_id', $secondQuestionId);

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$firstQuestionId}", [
            'selected_option' => 1,
        ])->assertOk()->assertJsonPath('selected_option', 1);

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$secondQuestionId}", [
            'selected_option' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('selected_option');

        $this->withToken($token)->getJson("/api/attempts/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('attempt.questions.0.selected_option', 1);

        $this->assertSame($user->id, ExamAttempt::query()->findOrFail($attemptId)->user_id);
    }

    public function test_submission_scores_correct_wrong_and_skipped_answers_and_is_idempotent(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $attempt = ExamAttempt::query()->findOrFail($attemptId);
        [$first, $second] = $attempt->answers()->orderBy('sequence')->get();

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$first->id}", [
            'selected_option' => 1,
        ])->assertOk();
        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$second->id}", [
            'selected_option' => 2,
        ])->assertOk();

        $submission = $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit");
        $submission->assertOk()
            ->assertJsonPath('attempt.status', 'submitted')
            ->assertJsonPath('result.correct_count', 1)
            ->assertJsonPath('result.incorrect_count', 1)
            ->assertJsonPath('result.unanswered_count', 0)
            ->assertJsonPath('result.score', '4.75')
            ->assertJsonPath('result.percentage', '47.50')
            ->assertJsonPath('result.passed', true);

        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('attempt.finished_at', $submission->json('attempt.finished_at'))
            ->assertJsonPath('result.score', '4.75');

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$first->id}", [
            'selected_option' => 2,
        ])->assertConflict();
    }

    public function test_unanswered_questions_are_counted_as_skipped_on_submission(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');

        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('result.correct_count', 0)
            ->assertJsonPath('result.incorrect_count', 0)
            ->assertJsonPath('result.unanswered_count', 2)
            ->assertJsonPath('result.score', '0.00')
            ->assertJsonPath('result.passed', false);
    }

    public function test_attempt_and_questions_are_scoped_to_the_authenticated_user(): void
    {
        [, $ownerToken] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attempt = $this->withToken($ownerToken)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');

        $this->withToken($otherToken)->getJson("/api/attempts/{$attempt}")->assertNotFound();
        $this->withToken($otherToken)->postJson("/api/attempts/{$attempt}/submit")->assertNotFound();
    }

    public function test_expired_attempt_is_finalized_by_server_and_cannot_save_more_answers(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $questionId = ExamAttempt::query()->findOrFail($attemptId)->answers()->value('id');

        ExamAttempt::query()->whereKey($attemptId)->update([
            'expires_at' => now()->subSecond(),
        ]);

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$questionId}", [
            'selected_option' => 1,
        ])->assertStatus(410);

        $this->assertDatabaseHas('attempts', [
            'id' => $attemptId,
            'status' => 'expired',
            'unanswered_count' => 2,
        ]);
    }

    public function test_premium_or_unpublished_test_cannot_be_started(): void
    {
        [, $token] = $this->createUserWithToken();
        $premiumTest = $this->createPublishedTest(['is_premium' => true]);

        $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $premiumTest->id,
        ])->assertForbidden();

        $draft = $this->createPublishedTest();
        $draft->update(['status' => 'draft']);
        $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $draft->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('test_id');
    }

    private function createUserWithToken(): array
    {
        $user = User::factory()->create();
        $plainTextToken = 'exam-token-'.$user->id;
        $user->apiTokens()->create([
            'name' => 'Exam token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $plainTextToken];
    }

    private function createPublishedTest(array $overrides = []): ModelTest
    {
        $subject = Subject::query()->create([
            'name' => 'Nursing '.Str::uuid(),
            'code' => 'NUR'.Str::uuid(),
        ]);
        $creator = User::factory()->create(['role' => 'admin']);
        $questions = collect(range(1, 2))->map(fn (int $number): Question => Question::query()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Exam question '.$number,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1,
            'explanation' => 'Option A is correct.',
            'difficulty' => 'medium',
            'is_active' => true,
        ]));

        $test = ModelTest::query()->create(array_merge([
            'title' => 'Exam Test '.Str::uuid(),
            'code' => 'TEST'.Str::uuid(),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 2,
            'total_marks' => 10,
            'passing_score' => 4,
            'negative_marking' => 0.25,
            'is_negative_marking_enabled' => true,
            'is_premium' => false,
            'status' => 'published',
            'created_by' => $creator->id,
        ], $overrides));

        $test->questions()->sync([
            $questions[0]->id => ['sequence' => 1, 'points' => 5],
            $questions[1]->id => ['sequence' => 2, 'points' => 5],
        ]);

        return $test;
    }
}
