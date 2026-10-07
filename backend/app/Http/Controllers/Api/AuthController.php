<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        $user = User::query()->create($validated + [
            'role' => 'student',
            'is_active' => true,
        ]);

        return response()->json($this->tokenResponse($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        return response()->json($this->tokenResponse($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower($validated['email']);
        $throttleKey = 'password-reset-otp:'.$email;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return response()->json([
                'message' => 'If an account exists for that email, a reset code will be sent.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $email],
                [
                    'code_hash' => Hash::make($code),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            Notification::send($user, new PasswordResetOtp($code));
        }

        return response()->json([
            'message' => 'If an account exists for that email, a reset code will be sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower($validated['email']);
        $reset = DB::table('password_reset_otps')->where('email', $email)->first();

        if (! $reset || now()->greaterThanOrEqualTo($reset->expires_at) || $reset->attempts >= 5) {
            return response()->json(['message' => 'The reset code is invalid or expired.'], 422);
        }

        if (! Hash::check($validated['otp'], $reset->code_hash)) {
            DB::table('password_reset_otps')->where('email', $email)->increment('attempts');

            return response()->json(['message' => 'The reset code is invalid or expired.'], 422);
        }

        DB::transaction(function () use ($email, $validated): void {
            User::query()->where('email', $email)->update([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);

            DB::table('password_reset_otps')->where('email', $email)->delete();
            ApiToken::query()->whereIn('user_id', User::query()->select('id')->where('email', $email))->delete();
        });

        return response()->json(['message' => 'Password reset successfully.']);
    }

    private function tokenResponse(User $user): array
    {
        $plainTextToken = Str::random(64);
        $expiresAt = now()->addDays(30);

        $user->apiTokens()->create([
            'name' => 'API token',
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
            'user' => $this->userData($user),
        ];
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'gender' => $user->gender,
            'address' => $user->address,
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
        ];
    }
}
