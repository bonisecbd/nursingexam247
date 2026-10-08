<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaderboardService
{
    private const TIMEZONE = 'Asia/Dhaka';

    public function paginate(string $period, int $perPage): array
    {
        $rankings = $this->rankingsQuery($period)->paginate($perPage);
        $startRank = ($rankings->currentPage() - 1) * $rankings->perPage();

        return [
            'data' => collect($rankings->items())
                ->values()
                ->map(fn (object $row, int $index): array => $this->entryData($row, $startRank + $index + 1))
                ->all(),
            'meta' => [
                'period' => $period,
                'timezone' => self::TIMEZONE,
                'current_page' => $rankings->currentPage(),
                'last_page' => $rankings->lastPage(),
                'per_page' => $rankings->perPage(),
                'total' => $rankings->total(),
            ],
        ];
    }

    public function position(string $period, int $userId): array
    {
        $mine = $this->rankingsQuery($period, $userId)->first();

        if (! $mine) {
            return [
                'period' => $period,
                'timezone' => self::TIMEZONE,
                'rank' => null,
                'eligible' => false,
                'score' => 0,
                'eligible_test_count' => 0,
                'achieved_at' => null,
            ];
        }

        $ranked = $this->rankingsQuery($period, includeOrder: false);
        $aheadCount = DB::query()
            ->fromSub($ranked, 'leaderboard')
            ->where(function (Builder $query) use ($mine): void {
                $query->where('score', '>', $mine->score)
                    ->orWhere(function (Builder $query) use ($mine): void {
                        $query->where('score', '=', $mine->score)
                            ->where('eligible_test_count', '>', $mine->eligible_test_count);
                    })
                    ->orWhere(function (Builder $query) use ($mine): void {
                        $query->where('score', '=', $mine->score)
                            ->where('eligible_test_count', '=', $mine->eligible_test_count)
                            ->where('achieved_at', '<', $mine->achieved_at);
                    })
                    ->orWhere(function (Builder $query) use ($mine): void {
                        $query->where('score', '=', $mine->score)
                            ->where('eligible_test_count', '=', $mine->eligible_test_count)
                            ->where('achieved_at', '=', $mine->achieved_at)
                            ->where('user_id', '<', $mine->user_id);
                    });
            })
            ->count();

        return [
            'period' => $period,
            'timezone' => self::TIMEZONE,
            'rank' => $aheadCount + 1,
            'eligible' => true,
            'score' => (float) $mine->score,
            'eligible_test_count' => (int) $mine->eligible_test_count,
            'achieved_at' => CarbonImmutable::parse($mine->achieved_at, 'UTC')->toISOString(),
        ];
    }

    private function rankingsQuery(string $period, ?int $userId = null, bool $includeOrder = true): Builder
    {
        $bestScores = DB::table('attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->join('tests', 'tests.id', '=', 'attempts.test_id')
            ->where('users.role', 'student')
            ->where('users.is_active', true)
            ->where('users.leaderboard_opt_in', true)
            ->where('tests.status', 'published')
            ->whereIn('attempts.status', ['submitted', 'expired'])
            ->whereNotNull('attempts.percentage')
            ->whereNotNull('attempts.finished_at')
            ->where(function (Builder $query): void {
                $query->whereNull('tests.created_by')
                    ->orWhereColumn('attempts.user_id', '!=', 'tests.created_by');
            })
            ->when($userId !== null, fn (Builder $query) => $query->where('users.id', $userId));

        $this->applyPeriod($bestScores, $period);

        $bestScores->select(
            'attempts.user_id',
            'attempts.test_id',
            DB::raw('MAX(attempts.percentage) as best_percentage'),
        )->groupBy('attempts.user_id', 'attempts.test_id');

        $bestAttempts = DB::table('attempts')
            ->joinSub($bestScores, 'best_scores', function ($join): void {
                $join->on('attempts.user_id', '=', 'best_scores.user_id')
                    ->on('attempts.test_id', '=', 'best_scores.test_id')
                    ->on('attempts.percentage', '=', 'best_scores.best_percentage');
            })
            ->select(
                'attempts.user_id',
                'attempts.test_id',
                'best_scores.best_percentage',
                DB::raw('MIN(attempts.finished_at) as best_finished_at'),
            )
            ->groupBy('attempts.user_id', 'attempts.test_id', 'best_scores.best_percentage');

        $rankings = DB::table('users')
            ->joinSub($bestAttempts, 'best_attempts', 'users.id', '=', 'best_attempts.user_id')
            ->select(
                'users.id as user_id',
                'users.name as display_name',
                'users.avatar_path',
                DB::raw('SUM(best_attempts.best_percentage) as score'),
                DB::raw('COUNT(*) as eligible_test_count'),
                DB::raw('MAX(best_attempts.best_finished_at) as achieved_at'),
            )
            ->groupBy('users.id', 'users.name', 'users.avatar_path');

        if ($includeOrder) {
            $rankings->orderByDesc('score')
                ->orderByDesc('eligible_test_count')
                ->orderBy('achieved_at')
                ->orderBy('user_id');
        }

        return $rankings;
    }

    private function applyPeriod(Builder $query, string $period): void
    {
        if ($period === 'overall') {
            return;
        }

        $now = CarbonImmutable::now(self::TIMEZONE);
        $start = match ($period) {
            'daily' => $now->startOfDay(),
            'weekly' => $now->startOfWeek(CarbonImmutable::MONDAY),
            'monthly' => $now->startOfMonth(),
        };
        $end = match ($period) {
            'daily' => $start->addDay(),
            'weekly' => $start->addWeek(),
            'monthly' => $start->addMonth(),
        };

        $query->where('attempts.finished_at', '>=', $start->utc()->toDateTimeString())
            ->where('attempts.finished_at', '<', $end->utc()->toDateTimeString());
    }

    private function entryData(object $row, int $rank): array
    {
        return [
            'rank' => $rank,
            'display_name' => $row->display_name,
            'avatar_url' => $row->avatar_path ? Storage::disk('public')->url($row->avatar_path) : null,
            'score' => round((float) $row->score, 2),
            'eligible_test_count' => (int) $row->eligible_test_count,
        ];
    }
}
