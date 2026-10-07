<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthProfileSubjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_root_returns_a_discovery_response(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('message', 'NurseExam247 API is running.')
            ->assertJsonPath('endpoints.subjects', 'GET /api/subjects');
    }

    public function test_user_can_register_login_and_revoke_a_bearer_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Rafi Ahmed',
            'email' => 'rafi@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('token_type', 'Bearer');

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'rafi@example.com',
            'password' => 'Password@123',
        ]);

        $login->assertOk()->assertJsonPath('user.email', 'rafi@example.com');
    }

    public function test_password_can_be_reset_with_the_emailed_otp_and_old_tokens_are_revoked(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rafi@example.com']);
        $token = $user->apiTokens()->create([
            'name' => 'API token',
            'token' => hash('sha256', 'existing-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        $code = null;
        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'otp' => $code,
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword@123', $user->fresh()->password));
        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_user_can_update_profile_and_list_active_subjects(): void
    {
        $user = User::factory()->create();
        $user->apiTokens()->create([
            'name' => 'API token',
            'token' => hash('sha256', 'profile-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->withToken('profile-token')->patchJson('/api/profile', [
            'name' => 'Rafi Ahmed',
            'phone' => '+8801700000000',
            'date_of_birth' => '2000-01-01',
            'gender' => 'prefer_not_to_say',
            'address' => 'Dhaka',
        ])->assertOk()
            ->assertJsonPath('user.phone', '+8801700000000')
            ->assertJsonPath('user.date_of_birth', '2000-01-01');

        Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        Subject::query()->create(['name' => 'General Knowledge', 'code' => 'GK']);
        Subject::query()->create(['name' => 'Hidden Subject', 'code' => 'HID', 'is_active' => false]);

        $this->getJson('/api/subjects?search=Nursing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'NUR');
        $this->getJson('/api/subjects/'.Subject::query()->where('code', 'NUR')->value('id'))->assertOk();
    }
}
