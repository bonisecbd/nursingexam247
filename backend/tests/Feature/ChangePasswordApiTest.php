<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    private function registerUser(string $email = 'changer@example.com'): array
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Password Changer',
            'email' => $email,
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertCreated();

        return [$response->json('token'), $response->json('user.id')];
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        [$token, $userId] = $this->registerUser();

        $this->withToken($token)->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])->assertOk()->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('NewPassword@456', User::query()->findOrFail($userId)->password));

        // The current session stays valid after the change.
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        // The old password no longer works; the new one does.
        $this->postJson('/api/auth/login', [
            'email' => 'changer@example.com',
            'password' => 'Password@123',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'changer@example.com',
            'password' => 'NewPassword@456',
        ])->assertOk();
    }

    public function test_other_sessions_are_revoked_when_password_changes(): void
    {
        [$tokenA] = $this->registerUser('multi@example.com');

        $second = $this->postJson('/api/auth/login', [
            'email' => 'multi@example.com',
            'password' => 'Password@123',
        ])->assertOk();
        $tokenB = $second->json('token');

        $this->withToken($tokenA)->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])->assertOk();

        // Caller keeps their session; the other token is revoked.
        $this->withToken($tokenA)->getJson('/api/auth/me')->assertOk();
        $this->withToken($tokenB)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        [$token] = $this->registerUser();

        $this->withToken($token)->patchJson('/api/auth/password', [
            'current_password' => 'WrongPassword1!',
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The current password is incorrect.');
    }

    public function test_new_password_must_differ_from_current_and_be_confirmed(): void
    {
        [$token] = $this->registerUser();

        $this->withToken($token)->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->withToken($token)->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'NewPassword@456',
            'password_confirmation' => 'Different@789',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->withToken($token)->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->patchJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'NewPassword@456',
            'password_confirmation' => 'NewPassword@456',
        ])->assertUnauthorized();
    }
}
