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

class ResultApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_result_summary_full_result_and_attempt_history(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $attempt = ExamAttempt::query()->findOrFail($attemptId);
        $firstQuestion = $attempt->answers()->orderBy('sequence')->firstOrFail();

        $this->withToken($token)->getJson("/api/results/{$attemptId}")
            ->assertConflict();

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$firstQuestion->id}", [
            'selected_option' => 1,
        ])->assertOk();
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")->assertOk();

        $this->withToken($token)->getJson("/api/results/{$attemptId}/summary")
            ->assertOk()
            ->assertJsonPath('result.attempt_id', $attemptId)
            ->assertJsonPath('result.correct_count', 1)
            ->assertJsonPath('result.wrong_count', 0)
            ->assertJsonPath('result.skipped_count', 1)
            ->assertJsonPath('result.score', '5.00')
            ->assertJsonPath('result.total_marks', '10.00')
            ->assertJsonPath('result.percentage', '50.00')
            ->assertJsonPath('result.passed', true);

        $this->withToken($token)->getJson("/api/results/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('result.test.id', $test->id)
            ->assertJsonPath('result.test.title', $test->title)
            ->assertJsonPath('result.status', 'submitted');

        $this->withToken($token)->getJson('/api/attempts?test_id='.$test->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attempt_id', $attemptId)
            ->assertJsonPath('data.0.test.id', $test->id);

        $this->assertSame($user->id, ExamAttempt::query()->findOrFail($attemptId)->user_id);
    }

    public function test_result_endpoints_do_not_expose_another_users_attempt(): void
    {
        [, $ownerToken] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($ownerToken)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $this->withToken($ownerToken)->postJson("/api/attempts/{$attemptId}/submit")->assertOk();

        $this->withToken($otherToken)->getJson("/api/results/{$attemptId}")->assertNotFound();
        $this->withToken($otherToken)->getJson("/api/results/{$attemptId}/summary")->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/attempts?test_id='.$test->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_history_excludes_in_progress_attempts_and_rejects_invalid_pagination(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/attempts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson('/api/attempts?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_owner_can_read_paginated_solutions_after_submission_from_attempt_snapshot(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $answers = ExamAttempt::query()->findOrFail($attemptId)->answers;
        $firstAnswer = $answers[0];
        $secondAnswer = $answers[1];

        $this->withToken($token)->getJson("/api/results/{$attemptId}/solutions")
            ->assertConflict();

        Question::query()->whereKey($firstAnswer->question_id)->update([
            'question_text' => 'Updated after this attempt',
            'correct_option' => 2,
            'explanation' => 'Updated explanation',
        ]);

        $this->withToken($token)->putJson("/api/attempts/{$attemptId}/answers/{$firstAnswer->id}", [
            'selected_option' => 1,
        ])->assertOk();
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")->assertOk();

        $this->withToken($token)->getJson("/api/results/{$attemptId}/solutions?per_page=1")
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_id', $firstAnswer->question_id)
            ->assertJsonPath('data.0.question_text', $firstAnswer->question_text)
            ->assertJsonPath('data.0.selected_option', 1)
            ->assertJsonPath('data.0.correct_option', 1)
            ->assertJsonPath('data.0.is_correct', true)
            ->assertJsonPath('data.0.explanation', $firstAnswer->explanation);

        $this->withToken($token)->getJson("/api/results/{$attemptId}/solutions/{$secondAnswer->id}")
            ->assertOk()
            ->assertJsonPath('solution.id', $secondAnswer->id)
            ->assertJsonPath('solution.question_id', $secondAnswer->question_id)
            ->assertJsonPath('solution.selected_option', null)
            ->assertJsonPath('solution.correct_option', 1)
            ->assertJsonPath('solution.is_correct', null);
    }

    public function test_solutions_are_private_and_pagination_is_validated(): void
    {
        [, $ownerToken] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->withToken($ownerToken)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
        $answerId = ExamAttempt::query()->findOrFail($attemptId)->answers->firstOrFail()->id;
        $this->withToken($ownerToken)->postJson("/api/attempts/{$attemptId}/submit")->assertOk();

        $this->withToken($otherToken)->getJson("/api/results/{$attemptId}/solutions")->assertNotFound();
        $this->withToken($otherToken)
            ->getJson("/api/results/{$attemptId}/solutions/{$answerId}")
            ->assertNotFound();
        $this->withToken($ownerToken)
            ->getJson("/api/results/{$attemptId}/solutions?per_page=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    private function createUserWithToken(): array
    {
        $user = User::factory()->create();
        $plainTextToken = 'result-token-'.Str::uuid();
        $user->apiTokens()->create([
            'name' => 'Result token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $plainTextToken];
    }

    private function createPublishedTest(): ModelTest
    {
        $subject = Subject::query()->create([
            'name' => 'Nursing '.Str::uuid(),
            'code' => 'NUR'.Str::uuid(),
        ]);
        $creator = User::factory()->create(['role' => 'admin']);
        $questions = collect(range(1, 2))->map(fn (int $number): Question => Question::query()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Result question '.$number,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1,
            'explanation' => 'Option A is correct.',
            'difficulty' => 'medium',
            'is_active' => true,
        ]));
        $test = ModelTest::query()->create([
            'title' => 'Result Test '.Str::uuid(),
            'code' => 'RES'.Str::uuid(),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 2,
            'total_marks' => 10,
            'passing_score' => 5,
            'negative_marking' => 0.25,
            'is_negative_marking_enabled' => true,
            'is_premium' => false,
            'status' => 'published',
            'created_by' => $creator->id,
        ]);
        $test->questions()->sync([
            $questions[0]->id => ['sequence' => 1, 'points' => 5],
            $questions[1]->id => ['sequence' => 2, 'points' => 5],
        ]);

        return $test;
    }
}
