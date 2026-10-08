<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    private const ONBOARDING_WINDOW_HOURS = 24;

    public function dashboard(User $user): array
    {
        $this->ensureEligibleStudent($user);
        $code = $this->codeFor($user);
        $attribution = DB::table('referrals')
            ->where('invitee_id', $user->id)
            ->first(['status', 'created_at', 'qualifying_event', 'qualified_at']);
        $canRedeem = ! $attribution
            && now()->lessThanOrEqualTo($user->created_at->addHours(self::ONBOARDING_WINDOW_HOURS))
            && ! ExamAttempt::query()->where('user_id', $user->id)->exists();
        $counts = DB::table('referrals')
            ->where('inviter_id', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified")
            ->selectRaw("SUM(CASE WHEN status = 'disqualified' THEN 1 ELSE 0 END) as disqualified")
            ->first();

        return [
            'code' => $code,
            'attribution' => $attribution ? [
                'status' => $attribution->status,
                'referred_at' => CarbonImmutable::parse($attribution->created_at, 'UTC')->toISOString(),
                'qualifying_event' => $attribution->qualifying_event,
                'qualified_at' => $attribution->qualified_at
                    ? CarbonImmutable::parse($attribution->qualified_at, 'UTC')->toISOString()
                    : null,
            ] : null,
            'can_redeem' => $canRedeem,
            'counts' => [
                'total' => (int) $counts->total,
                'pending' => (int) $counts->pending,
                'qualified' => (int) $counts->qualified,
                'disqualified' => (int) $counts->disqualified,
            ],
            'reward_status' => 'unavailable_until_purchase_module',
        ];
    }

    public function invites(User $user, int $perPage): array
    {
        $this->ensureEligibleStudent($user);
        $referrals = DB::table('referrals')
            ->where('inviter_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => collect($referrals->items())
                ->map(fn (object $referral): array => [
                    'status' => $referral->status,
                    'referred_at' => CarbonImmutable::parse($referral->created_at, 'UTC')->toISOString(),
                    'qualifying_event' => $referral->qualifying_event,
                    'qualified_at' => $referral->qualified_at
                        ? CarbonImmutable::parse($referral->qualified_at, 'UTC')->toISOString()
                        : null,
                ])
                ->all(),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
            ],
        ];
    }

    public function attributeAtRegistration(User $invitee, string $code): void
    {
        $this->attribute($invitee, $code, false);
    }

    public function redeemDuringOnboarding(User $invitee, string $code): void
    {
        $this->attribute($invitee, $code, true);
    }

    private function attribute(User $invitee, string $code, bool $checkOnboarding): void
    {
        $this->ensureEligibleStudent($invitee);

        DB::transaction(function () use ($invitee, $code, $checkOnboarding): void {
            $invitee = User::query()->lockForUpdate()->findOrFail($invitee->id);
            if (DB::table('referrals')->where('invitee_id', $invitee->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => ['This account already has a referral attribution.'],
                ]);
            }

            if ($checkOnboarding) {
                $deadline = $invitee->created_at->addHours(self::ONBOARDING_WINDOW_HOURS);
                $hasAttempt = ExamAttempt::query()->where('user_id', $invitee->id)->exists();
                if (now()->greaterThan($deadline) || $hasAttempt) {
                    throw ValidationException::withMessages([
                        'code' => ['Referral codes can only be added within 24 hours of registration and before starting a test.'],
                    ]);
                }
            }

            $referralCode = DB::table('referral_codes')
                ->where('code', Str::upper($code))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $referralCode) {
                throw ValidationException::withMessages([
                    'code' => ['The referral code is invalid or inactive.'],
                ]);
            }

            $inviter = User::query()->lockForUpdate()->find($referralCode->user_id);
            if (! $inviter || $inviter->role !== 'student' || ! $inviter->is_active || (int) $inviter->id === (int) $invitee->id) {
                throw ValidationException::withMessages([
                    'code' => ['The referral code is invalid or inactive.'],
                ]);
            }

            DB::table('referrals')->insert([
                'referral_code_id' => $referralCode->id,
                'inviter_id' => $inviter->id,
                'invitee_id' => $invitee->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function codeFor(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = DB::table('referral_codes')->where('user_id', $user->id)->value('code');
            if ($existing) {
                return $existing;
            }

            do {
                $code = Str::upper(Str::random(16));
            } while (DB::table('referral_codes')->where('code', $code)->exists());

            DB::table('referral_codes')->insert([
                'user_id' => $user->id,
                'code' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $code;
        });
    }

    private function ensureEligibleStudent(User $user): void
    {
        if ($user->role !== 'student' || ! $user->is_active) {
            abort(403, 'Referral features are available to active student accounts only.');
        }
    }
}
