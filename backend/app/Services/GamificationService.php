<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    private const LEVEL_VERSION = 1;

    public function awardTestCompletion(ExamAttempt $attempt): ?array
    {
        if ($attempt->status !== 'submitted' || $attempt->percentage === null) {
            return null;
        }

        $test = $attempt->test()->firstOrFail();
        $user = User::query()->lockForUpdate()->findOrFail($attempt->user_id);
        if ($user->role !== 'student' || ! $user->is_active || $test->status !== 'published') {
            return null;
        }

        if ((int) $test->created_by === $user->id) {
            return null;
        }

        $sourceType = 'test_completion';
        $sourceId = $test->id;
        $rewardType = 'first_test_completion';
        $idempotencyKey = "user:{$user->id}:test:{$test->id}:first_completion";

        if (DB::table('xp_events')->where('idempotency_key', $idempotencyKey)->exists()) {
            return null;
        }

        $settings = DB::table('gamification_settings')->where('id', 1)->lockForUpdate()->first();
        if (! $settings) {
            throw new \RuntimeException('Gamification reward settings are missing.');
        }

        $description = 'First completion of '.$test->title;
        DB::table('point_transactions')->insert([
            'user_id' => $user->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reward_type' => $rewardType,
            'idempotency_key' => $idempotencyKey,
            'amount' => $settings->points_per_test,
            'description' => $description,
            'created_at' => now(),
        ]);
        DB::table('xp_events')->insert([
            'user_id' => $user->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reward_type' => $rewardType,
            'idempotency_key' => $idempotencyKey,
            'amount' => $settings->xp_per_test,
            'description' => $description,
            'created_at' => now(),
        ]);

        $completedTests = DB::table('point_transactions')
            ->where('user_id', $user->id)
            ->where('reward_type', $rewardType)
            ->where('amount', '>', 0)
            ->count();

        $newBadges = $this->awardEligibleBadges($user->id, $attempt, $completedTests);

        return [
            'awarded' => true,
            'points' => (int) $settings->points_per_test,
            'xp' => (int) $settings->xp_per_test,
            'new_badges' => $newBadges,
        ];
    }

    public function summary(User $user): array
    {
        $xpTotal = (int) DB::table('xp_events')->where('user_id', $user->id)->sum('amount');
        $pointsBalance = (int) DB::table('point_transactions')->where('user_id', $user->id)->sum('amount');
        $levels = DB::table('gamification_levels')
            ->where('version', self::LEVEL_VERSION)
            ->orderBy('level')
            ->get();
        $currentLevel = $levels->last(fn (object $level): bool => $xpTotal >= $level->xp_required);
        $nextLevel = $levels->first(fn (object $level): bool => $xpTotal < $level->xp_required);
        $completedTests = DB::table('point_transactions')
            ->where('user_id', $user->id)
            ->where('reward_type', 'first_test_completion')
            ->where('amount', '>', 0)
            ->count();

        return [
            'xp_total' => $xpTotal,
            'points_balance' => $pointsBalance,
            'level' => $currentLevel ? [
                'number' => (int) $currentLevel->level,
                'title' => $currentLevel->title,
                'xp_required' => (int) $currentLevel->xp_required,
                'rule_version' => self::LEVEL_VERSION,
            ] : null,
            'next_level' => $nextLevel ? [
                'number' => (int) $nextLevel->level,
                'title' => $nextLevel->title,
                'xp_required' => (int) $nextLevel->xp_required,
                'xp_remaining' => (int) $nextLevel->xp_required - $xpTotal,
            ] : null,
            'completed_test_count' => $completedTests,
            'badges' => $this->badgeData($user->id),
            'recent_point_transactions' => DB::table('point_transactions')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['amount', 'description', 'created_at'])
                ->map(fn (object $transaction): array => [
                    'amount' => (int) $transaction->amount,
                    'description' => $transaction->description,
                    'created_at' => CarbonImmutable::parse($transaction->created_at, 'UTC')->toISOString(),
                ])
                ->all(),
        ];
    }

    public function publicBadges(): array
    {
        return DB::table('badge_definitions')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'description', 'icon', 'trigger_type', 'threshold', 'version'])
            ->map(fn (object $badge): array => $this->formatBadge($badge, false, null))
            ->all();
    }

    private function awardEligibleBadges(int $userId, ExamAttempt $attempt, int $completedTests): array
    {
        $earned = [];
        $definitions = DB::table('badge_definitions')->where('is_active', true)->get();

        foreach ($definitions as $badge) {
            $eligible = match ($badge->trigger_type) {
                'tests_completed' => $completedTests >= $badge->threshold,
                'perfect_test' => $attempt->percentage >= 100,
                default => false,
            };

            if (! $eligible) {
                continue;
            }

            if (DB::table('user_badges')
                ->where('user_id', $userId)
                ->where('badge_definition_id', $badge->id)
                ->exists()) {
                continue;
            }

            DB::table('user_badges')->insert([
                'user_id' => $userId,
                'badge_definition_id' => $badge->id,
                'source_attempt_id' => $attempt->id,
                'awarded_at' => now(),
            ]);

            $earned[] = [
                'code' => $badge->code,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
            ];
        }

        return $earned;
    }

    private function badgeData(int $userId): array
    {
        return DB::table('badge_definitions')
            ->leftJoin('user_badges', function ($join) use ($userId): void {
                $join->on('badge_definitions.id', '=', 'user_badges.badge_definition_id')
                    ->where('user_badges.user_id', '=', $userId);
            })
            ->where('badge_definitions.is_active', true)
            ->orderBy('badge_definitions.id')
            ->get([
                'badge_definitions.code',
                'badge_definitions.name',
                'badge_definitions.description',
                'badge_definitions.icon',
                'badge_definitions.trigger_type',
                'badge_definitions.threshold',
                'badge_definitions.version',
                'user_badges.awarded_at',
            ])
            ->map(fn (object $badge): array => $this->formatBadge($badge, $badge->awarded_at !== null, $badge->awarded_at))
            ->all();
    }

    private function formatBadge(object $badge, bool $earned, ?string $awardedAt): array
    {
        return [
            'code' => $badge->code,
            'name' => $badge->name,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'requirement' => [
                'type' => $badge->trigger_type,
                'threshold' => (int) $badge->threshold,
                'version' => (int) $badge->version,
            ],
            'earned' => $earned,
            'awarded_at' => $awardedAt ? CarbonImmutable::parse($awardedAt, 'UTC')->toISOString() : null,
        ];
    }
}
