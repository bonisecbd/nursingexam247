<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class GamificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_valid_test_completion_awards_xp_points_and_badges_once(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$student, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $firstAttempt = $this->startAttempt($token, $test);
        $questionId = ExamAttempt::query()->findOrFail($firstAttempt)->answers()->value('id');
        $this->withToken($token)->putJson("/api/attempts/{$firstAttempt}/answers/{$questionId}", [
            'selected_option' => 1,
        ])->assertOk();

        $submission = $this->withToken($token)->postJson("/api/attempts/{$firstAttempt}/submit");
        $submission->assertOk()
            ->assertJsonPath('reward.awarded', true)
            ->assertJsonPath('reward.points', 5)
            ->assertJsonPath('reward.xp', 10)
            ->assertJsonPath('reward.new_badges.0.code', 'first_test')
            ->assertJsonPath('reward.new_badges.1.code', 'perfect_test');

        $this->withToken($token)->postJson("/api/attempts/{$firstAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('reward', null);

        $secondAttempt = $this->startAttempt($token, $test);
        $this->withToken($token)->postJson("/api/attempts/{$secondAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('reward', null);

        for ($index = 0; $index < 9; $index++) {
            $anotherTest = $this->createPublishedTest();
            $anotherAttempt = $this->startAttempt($token, $anotherTest);
            $lastSubmission = $this->withToken($token)->postJson("/api/attempts/{$anotherAttempt}/submit")
                ->assertOk()
                ->assertJsonPath('reward.awarded', true);
        }

        $this->assertDatabaseCount('point_transactions', 10);
        $lastSubmission->assertJsonPath('reward.new_badges.0.code', 'ten_tests');
        $this->assertDatabaseCount('xp_events', 10);
        $this->assertDatabaseCount('user_badges', 3);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $student->id,
            'source_type' => 'test_completion',
            'source_id' => $test->id,
            'amount' => 5,
        ]);

        $this->withToken($token)->getJson('/api/gamification/me')
            ->assertOk()
            ->assertJsonPath('gamification.xp_total', 100)
            ->assertJsonPath('gamification.points_balance', 50)
            ->assertJsonPath('gamification.level.number', 2)
            ->assertJsonPath('gamification.completed_test_count', 10)
            ->assertJsonPath('gamification.badges.0.earned', true)
            ->assertJsonPath('gamification.badges.1.earned', true)
            ->assertJsonPath('gamification.badges.2.earned', true)
            ->assertJsonPath('gamification.recent_point_transactions.0.amount', 5);

        $this->withToken($token)->getJson('/api/gamification/achievements')
            ->assertOk()
            ->assertJsonPath('completed_test_count', 10)
            ->assertJsonPath('badges.0.earned', true);
    }

    public function test_only_a_published_non_author_student_submission_can_earn_rewards(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attempt = $this->startAttempt($token, $test);
        $test->update(['status' => 'draft']);

        $this->withToken($token)->postJson("/api/attempts/{$attempt}/submit")
            ->assertOk()
            ->assertJsonPath('reward', null);

        [$author, $authorToken] = $this->createUserWithToken();
        $authorTest = $this->createPublishedTest(['created_by' => $author->id]);
        $authorAttempt = $this->startAttempt($authorToken, $authorTest);
        $this->withToken($authorToken)->postJson("/api/attempts/{$authorAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('reward', null);

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertDatabaseCount('xp_events', 0);
    }

    public function test_expired_attempts_do_not_receive_completion_rewards(): void
    {
        [, $token] = $this->createUserWithToken();
        $test = $this->createPublishedTest();
        $attemptId = $this->startAttempt($token, $test);
        ExamAttempt::query()->whereKey($attemptId)->update(['expires_at' => now()->subSecond()]);

        $this->withToken($token)->postJson("/api/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('attempt.status', 'expired')
            ->assertJsonPath('reward', null);

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertDatabaseCount('xp_events', 0);
    }

    public function test_admin_can_update_future_rewards_and_other_roles_cannot(): void
    {
        [, $studentToken] = $this->createUserWithToken();
        [, $adminToken] = $this->createUserWithToken(['role' => 'admin']);
        [, $editorToken] = $this->createUserWithToken(['role' => 'editor']);

        $this->getJson('/api/admin/gamification/rules')->assertUnauthorized();
        $this->getJson('/api/gamification/badges')
            ->assertOk()
            ->assertJsonCount(3, 'data');
        $this->withToken($studentToken)->getJson('/api/gamification/me')->assertOk();
        $this->withToken($studentToken)->getJson('/api/admin/gamification/rules')->assertForbidden();
        $this->withToken($editorToken)->patchJson('/api/admin/gamification/rules', [
            'points_per_test' => 8,
            'xp_per_test' => 20,
        ])->assertForbidden();

        $this->withToken($adminToken)->getJson('/api/admin/gamification/rules')
            ->assertOk()
            ->assertJsonPath('rules.points_per_test', 5)
            ->assertJsonCount(5, 'level_rules')
            ->assertJsonCount(3, 'badges');

        $this->withToken($adminToken)->patchJson('/api/admin/gamification/rules', [
            'points_per_test' => 8,
            'xp_per_test' => 20,
        ])->assertOk()
            ->assertJsonPath('rules.points_per_test', 8)
            ->assertJsonPath('rules.xp_per_test', 20);

        $this->withToken($adminToken)->patchJson('/api/admin/gamification/rules', [
            'points_per_test' => 0,
            'xp_per_test' => 20,
        ])->assertUnprocessable()->assertJsonValidationErrors('points_per_test');

        $test = $this->createPublishedTest();
        $attempt = $this->startAttempt($studentToken, $test);
        $this->withToken($studentToken)->postJson("/api/attempts/{$attempt}/submit")
            ->assertOk()
            ->assertJsonPath('reward.points', 8)
            ->assertJsonPath('reward.xp', 20);
    }

    private function createUserWithToken(array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $token = 'gamification-token-'.$user->id;
        $user->apiTokens()->create([
            'name' => 'Gamification test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function createPublishedTest(array $overrides = []): ModelTest
    {
        $subject = Subject::query()->create([
            'name' => 'Nursing '.Str::uuid(),
            'code' => 'NUR'.Str::uuid(),
        ]);
        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Choose the correct answer.',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1,
            'explanation' => 'A is correct.',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);
        $creator = User::factory()->create(['role' => 'admin']);

        $test = ModelTest::query()->create(array_merge([
            'title' => 'Gamification Test '.Str::uuid(),
            'code' => 'GAM'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 1,
            'total_marks' => 10,
            'passing_score' => 5,
            'negative_marking' => 0,
            'is_negative_marking_enabled' => false,
            'is_premium' => false,
            'status' => 'published',
            'created_by' => $creator->id,
        ], $overrides));

        $test->questions()->sync([
            $question->id => ['sequence' => 1, 'points' => 10],
        ]);

        return $test;
    }

    private function startAttempt(string $token, ModelTest $test): int
    {
        return $this->withToken($token)->postJson('/api/attempts', [
            'test_id' => $test->id,
        ])->assertCreated()->json('attempt.id');
    }
}
