<?php

namespace Tests\Feature;

use App\Models\ExamAttempt;
use App\Models\ModelTest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_and_reuse_a_private_referral_code(): void
    {
        [$student, $token] = $this->createUserWithToken();

        $first = $this->withToken($token)->getJson('/api/referrals/me')
            ->assertOk()
            ->assertJsonPath('referral.counts.total', 0)
            ->assertJsonPath('referral.can_redeem', true)
            ->assertJsonPath('referral.reward_status', 'unavailable_until_purchase_module');

        $code = $first->json('referral.code');
        $this->assertSame(16, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);

        $this->withToken($token)->getJson('/api/referrals/me')
            ->assertOk()
            ->assertJsonPath('referral.code', $code);
        $this->assertDatabaseHas('referral_codes', ['user_id' => $student->id, 'code' => $code]);
    }

    public function test_registration_can_attribute_a_referral_without_exposing_invitee_details_or_awarding_rewards(): void
    {
        [, $inviterToken] = $this->createUserWithToken();
        $code = $this->withToken($inviterToken)->getJson('/api/referrals/me')->json('referral.code');

        $registration = $this->postJson('/api/auth/register', [
            'name' => 'New Student',
            'email' => 'new-student@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'referral_code' => strtolower($code),
        ])->assertCreated();
        $inviteeId = $registration->json('user.id');

        $this->assertDatabaseHas('referrals', [
            'invitee_id' => $inviteeId,
            'status' => 'pending',
            'qualified_at' => null,
        ]);
        $this->withToken($registration->json('token'))->getJson('/api/referrals/me')
            ->assertOk()
            ->assertJsonPath('referral.attribution.status', 'pending')
            ->assertJsonPath('referral.can_redeem', false);
        $this->withToken($inviterToken)->getJson('/api/referrals/me')
            ->assertOk()
            ->assertJsonPath('referral.counts.total', 1)
            ->assertJsonPath('referral.counts.pending', 1);

        $invites = $this->withToken($inviterToken)->getJson('/api/referrals/me/invites?per_page=1');
        $invites->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonMissingPath('data.0.name')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.invitee_id');

        $this->assertDatabaseCount('point_transactions', 0);
        $this->assertDatabaseCount('xp_events', 0);
    }

    public function test_new_account_can_redeem_once_during_onboarding_before_starting_an_attempt(): void
    {
        [, $inviterToken] = $this->createUserWithToken();
        $code = $this->withToken($inviterToken)->getJson('/api/referrals/me')->json('referral.code');
        [, $inviteeToken] = $this->createUserWithToken();

        $this->withToken($inviteeToken)->postJson('/api/referrals/redeem', ['code' => strtolower($code)])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');
        $this->withToken($inviteeToken)->getJson('/api/referrals/me')
            ->assertOk()
            ->assertJsonPath('referral.attribution.status', 'pending')
            ->assertJsonPath('referral.can_redeem', false);

        $this->withToken($inviteeToken)->postJson('/api/referrals/redeem', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_referral_redeem_rejects_self_referral_invalid_code_and_users_past_onboarding(): void
    {
        [, $token] = $this->createUserWithToken();
        $code = $this->withToken($token)->getJson('/api/referrals/me')->json('referral.code');

        $this->withToken($token)->postJson('/api/referrals/redeem', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->withToken($token)->postJson('/api/referrals/redeem', ['code' => 'INVALID'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        [$lateInvitee, $lateInviteeToken] = $this->createUserWithToken();
        User::query()->whereKey($lateInvitee->id)->update(['created_at' => now()->subHours(25)]);
        $this->withToken($lateInviteeToken)->postJson('/api/referrals/redeem', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertNull(DB::table('referrals')->value('status'));
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_users_with_exam_activity_and_non_student_accounts_cannot_redeem_or_generate_codes(): void
    {
        [, $studentToken] = $this->createUserWithToken();
        $code = $this->withToken($studentToken)->getJson('/api/referrals/me')->json('referral.code');
        [$activeInvitee, $inviteeToken] = $this->createUserWithToken();
        $subject = Subject::query()->create(['name' => 'Referral subject', 'code' => 'REF']);
        $test = ModelTest::query()->create([
            'title' => 'Referral test',
            'code' => 'REF'.Str::upper(Str::random(8)),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ]);
        ExamAttempt::query()->create([
            'user_id' => $activeInvitee->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'total_marks' => 1,
        ]);

        $this->withToken($inviteeToken)->postJson('/api/referrals/redeem', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        [, $staffToken] = $this->createUserWithToken(['role' => 'admin']);
        $this->withToken($staffToken)->getJson('/api/referrals/me')->assertForbidden();
        $this->withToken($staffToken)->getJson('/api/referrals/me/invites')->assertForbidden();
        $this->withToken($staffToken)->postJson('/api/referrals/redeem', ['code' => $code])->assertForbidden();
    }

    public function test_registration_with_invalid_referral_code_does_not_create_the_account(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Student',
            'email' => 'invalid-ref@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'referral_code' => 'NOT-A-REAL-CODE',
        ])->assertUnprocessable()->assertJsonValidationErrors('referral_code');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-ref@example.com']);
    }

    private function createUserWithToken(array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $token = 'referral-token-'.$user->id;
        $user->apiTokens()->create([
            'name' => 'Referral test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
