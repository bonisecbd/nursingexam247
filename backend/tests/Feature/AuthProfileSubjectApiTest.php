<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthProfileSubjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_root_returns_a_discovery_response(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('message', 'NurseExam247 API is running.')
            ->assertJsonPath('endpoints.subjects', 'GET /api/subjects')
            ->assertJsonPath('endpoints.leaderboards', 'GET /api/leaderboards?period=daily|weekly|monthly|overall');
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

    public function test_authenticated_requests_do_not_reset_the_token_expiry(): void
    {
        $registration = $this->postJson('/api/auth/register', [
            'name' => 'Rafi Ahmed',
            'email' => 'rafi@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertCreated();
        $token = $registration->json('token');
        $storedExpiry = ApiToken::query()
            ->where('token', hash('sha256', $token))
            ->value('expires_at');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->getJson('/api/profile')->assertOk();

        $this->assertEquals(
            $storedExpiry,
            ApiToken::query()
                ->where('token', hash('sha256', $token))
                ->value('expires_at'),
        );
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
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
            'leaderboard_opt_in' => true,
        ])->assertOk()
            ->assertJsonPath('user.phone', '+8801700000000')
            ->assertJsonPath('user.date_of_birth', '2000-01-01')
            ->assertJsonPath('user.leaderboard_opt_in', true);

        $this->withToken('profile-token')->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.name', 'Rafi Ahmed')
            ->assertJsonPath('user.gender', 'prefer_not_to_say')
            ->assertJsonPath('user.address', 'Dhaka')
            ->assertJsonPath('user.leaderboard_opt_in', true);

        Subject::query()->create(['name' => 'Nursing', 'code' => 'NUR']);
        Subject::query()->create(['name' => 'General Knowledge', 'code' => 'GK']);
        Subject::query()->create(['name' => 'Hidden Subject', 'code' => 'HID', 'is_active' => false]);

        $this->getJson('/api/subjects?search=Nursing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'NUR');
        $this->getJson('/api/subjects/'.Subject::query()->where('code', 'NUR')->value('id'))->assertOk();
    }

    public function test_user_can_upload_and_replace_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->apiTokens()->create([
            'name' => 'API token',
            'token' => hash('sha256', 'photo-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $firstUpload = $this->withToken('photo-token')->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->createWithContent(
                'first.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n6sAAAAASUVORK5CYII='),
            )->mimeType('image/png'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('message', 'Profile photo updated successfully.');

        $firstPath = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringContainsString('/storage/'.$firstPath, $firstUpload->json('user.avatar_url'));

        $this->withToken('photo-token')->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->createWithContent(
                'second.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n6sAAAAASUVORK5CYII='),
            )->mimeType('image/png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotSame($firstPath, $user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_profile_photo_upload_validates_type_and_requires_authentication(): void
    {
        $this->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        $user = User::factory()->create();
        $user->apiTokens()->create([
            'name' => 'API token',
            'token' => hash('sha256', 'invalid-photo-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->withToken('invalid-photo-token')->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }
}
