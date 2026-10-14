<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\ExamAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Challenge orchestration: eligibility, attempt limits, occurrence bucketing,
 * idempotent reward settlement, and challenge-only standings.
 *
 * The challenge never owns answers or scores — it links to a normal Exam
 * module attempt so the authoritative scoring/answer-key protections of that
 * module apply unchanged. Settlement writes to the gamification ledger
 * (point_transactions/xp_events) once per occurrence and credits any wallet
 * prize through WalletService (append-only, idempotent).
 */
class ChallengeService
{
    public function __construct(private readonly WalletService $wallets) {}

    /**
     * Currently available challenges: active, inside the overall window.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function paginate(?string $period, int $perPage): array
    {
        $now = now();
        $challenges = Challenge::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->when($period !== null, fn ($query) => $query->where('period', $period))
            ->orderBy('ends_at')
            ->orderBy('id')
            ->paginate($perPage);

        $counts = $this->participantCounts(
            collect($challenges->items())->pluck('id')->all()
        );

        return [
            'data' => collect($challenges->items())
                ->map(fn (Challenge $challenge): array => $this->challengeData(
                    $challenge,
                    (int) ($counts[$challenge->id] ?? 0),
                ))
                ->values()
                ->all(),
            'meta' => [
                'period' => $period ?? 'all',
                'current_page' => $challenges->currentPage(),
                'last_page' => $challenges->lastPage(),
                'per_page' => $challenges->perPage(),
                'total' => $challenges->total(),
            ],
        ];
    }

    /**
     * Start (or resume) this user's challenge attempt for the current
     * occurrence. Runs under a challenge row lock so the attempt-limit check
     * cannot race a concurrent start.
     *
     * @return array{participation: ChallengeParticipation, resumed: bool}
     *
     * @throws ValidationException
     */
    public function start(User $user, Challenge $challenge): array
    {
        return DB::transaction(function () use ($user, $challenge): array {
            $locked = Challenge::query()->lockForUpdate()->findOrFail($challenge->id);
            $now = now();

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'challenge' => ['This challenge is not active.'],
                ]);
            }
            if ($now->lessThan($locked->starts_at)) {
                throw ValidationException::withMessages([
                    'challenge' => ['This challenge has not started yet.'],
                ]);
            }
            if ($now->greaterThanOrEqualTo($locked->ends_at)) {
                throw ValidationException::withMessages([
                    'challenge' => ['This challenge has ended. Joining after expiry is not allowed.'],
                ]);
            }

            $test = $locked->test()->firstOrFail();

            if ($test->status !== 'published') {
                throw ValidationException::withMessages([
                    'challenge' => ['The challenge test is not currently published.'],
                ]);
            }

            if ($test->is_premium && ! app(SubscriptionService::class)->canAccessTest($user, $test)) {
                abort(403, 'This premium challenge requires an active premium subscription.');
            }

            $questions = $test->questions()->where('questions.is_active', true)->get();

            if ($questions->count() !== $test->question_count) {
                throw ValidationException::withMessages([
                    'challenge' => ['This challenge is unavailable because its published question set is incomplete.'],
                ]);
            }

            foreach ($questions as $question) {
                if (
                    ! is_array($question->options)
                    || count($question->options) < 2
                    || $question->correct_option < 1
                    || $question->correct_option > count($question->options)
                ) {
                    throw ValidationException::withMessages([
                        'challenge' => ['This challenge contains a question with invalid answer options.'],
                    ]);
                }
            }

            $occurrenceKey = $this->occurrenceKey($locked, $now);
            $participations = ChallengeParticipation::query()
                ->where('challenge_id', $locked->id)
                ->where('user_id', $user->id)
                ->where('occurrence_key', $occurrenceKey)
                ->with(['attempt.answers'])
                ->orderBy('id')
                ->get();

            // A live attempt is resumed instead of starting a second timer,
            // mirroring the Exam module's idempotent start contract.
            foreach ($participations as $participation) {
                $attempt = $participation->attempt;

                if ($attempt !== null && $attempt->status === 'in_progress' && $now->lessThan($attempt->expires_at)) {
                    return ['participation' => $participation, 'resumed' => true];
                }
            }

            if ($participations->count() >= $locked->attempts_allowed) {
                throw ValidationException::withMessages([
                    'attempt' => ['You have used all '.$locked->attempts_allowed.' allowed attempt(s) for this challenge occurrence.'],
                ]);
            }

            // The timer is clamped to the challenge end: an attempt can never
            // outlive ends_at, and joining is impossible after expiry anyway.
            $expiresAt = $now->copy()->addMinutes($locked->duration_minutes);
            if ($expiresAt->greaterThan($locked->ends_at)) {
                $expiresAt = $locked->ends_at->copy();
            }

            $attempt = ExamAttempt::query()->create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
                'started_at' => $now,
                'expires_at' => $expiresAt,
                'total_marks' => $test->total_marks,
            ]);

            foreach ($questions as $question) {
                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'sequence' => $question->pivot->sequence,
                    'question_text' => $question->question_text,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'explanation' => $question->explanation,
                    'points' => $question->pivot->points,
                ]);
            }

            $participation = ChallengeParticipation::query()->create([
                'challenge_id' => $locked->id,
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'occurrence_key' => $occurrenceKey,
                'attempt_index' => $participations->count() + 1,
            ]);

            return ['participation' => $participation->load(['attempt.answers']), 'resumed' => false];
        });
    }

    /**
     * Own state for a challenge: attempt usage, live attempt, rank, rewards.
     *
     * @return array<string, mixed>
     */
    public function me(User $user, Challenge $challenge): array
    {
        $this->settle($challenge, $user->id);

        $now = now();
        $occurrenceKey = $this->occurrenceKey($challenge, $now);
        $participantCount = (int) ($this->participantCounts([$challenge->id])[$challenge->id] ?? 0);

        $participations = ChallengeParticipation::query()
            ->where('challenge_id', $challenge->id)
            ->where('user_id', $user->id)
            ->with('attempt')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $attemptsUsed = (int) ChallengeParticipation::query()
            ->where('challenge_id', $challenge->id)
            ->where('user_id', $user->id)
            ->where('occurrence_key', $occurrenceKey)
            ->count();

        $liveAttempt = $participations->first(
            fn (ChallengeParticipation $participation): bool => $participation->attempt !== null
                && $participation->attempt->status === 'in_progress'
                && $now->lessThan($participation->attempt->expires_at)
        )?->attempt;

        $mine = $this->standingsQuery($challenge, includeOrder: false)
            ->where('challenge_participations.user_id', $user->id)
            ->first();

        return [
            'challenge' => $this->challengeData($challenge, $participantCount),
            'occurrence' => $occurrenceKey,
            'attempts_allowed' => (int) $challenge->attempts_allowed,
            'attempts_used' => $attemptsUsed,
            'live_attempt' => $liveAttempt !== null ? $this->attemptData($liveAttempt) : null,
            'rank' => $mine ? $this->rankOf($challenge, $mine) : null,
            'score' => $mine ? round((float) $mine->score, 2) : null,
            'history' => $participations
                ->map(fn (ChallengeParticipation $participation): array => $this->participationData($participation))
                ->values()
                ->all(),
        ];
    }

    /**
     * Settle every finished, not-yet-settled participation of a challenge
     * (optionally scoped to one user). Idempotent: replaying is a no-op.
     */
    public function settle(Challenge $challenge, ?int $userId = null): void
    {
        $participations = ChallengeParticipation::query()
            ->where('challenge_id', $challenge->id)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereNull('settled_at')
            ->orderBy('id')
            ->get();

        foreach ($participations as $participation) {
            $this->settleParticipation($participation);
        }
    }

    /**
     * Settle one participation and return its stored reward outcome.
     *
     * The reward (points/XP/prize) attaches to the first *submitted*
     * participation of an occurrence; expired or later attempts settle with
     * zero. Every ledger write is idempotent on challenge/user/occurrence.
     *
     * @return array<string, mixed>
     */
    public function settleParticipation(ChallengeParticipation $participation): array
    {
        return DB::transaction(function () use ($participation): array {
            $locked = ChallengeParticipation::query()->lockForUpdate()->findOrFail($participation->id);

            if ($locked->settled_at !== null) {
                return $this->rewardData($locked);
            }

            $attempt = ExamAttempt::query()->lockForUpdate()->findOrFail($locked->attempt_id);

            if ($attempt->status === 'in_progress') {
                // Still running: settled on a later sweep once it is finalized.
                return $this->rewardData($locked);
            }

            $challenge = Challenge::query()->findOrFail($locked->challenge_id);
            $user = User::query()->find($locked->user_id);
            $test = $attempt->test()->first();

            $eligible = $attempt->status === 'submitted'
                && $user !== null
                && $user->role === 'student'
                && $user->is_active
                && ! ($test !== null && (int) $test->created_by === (int) $user->id)
                && ! ChallengeParticipation::query()
                    ->where('challenge_id', $challenge->id)
                    ->where('user_id', $locked->user_id)
                    ->where('occurrence_key', $locked->occurrence_key)
                    ->where('rewarded', true)
                    ->exists();

            $points = $eligible ? (int) $challenge->reward_points : 0;
            $xp = $eligible ? (int) $challenge->reward_xp : 0;
            $walletMinor = $eligible ? (int) $challenge->reward_wallet_minor : 0;

            $walletTransactionId = null;
            if ($walletMinor > 0) {
                $entry = $this->wallets->credit(
                    $locked->user_id,
                    $walletMinor,
                    'challenge',
                    $challenge->id,
                    $this->walletKey($challenge, $locked),
                    'Challenge reward: '.$challenge->title,
                );
                $walletTransactionId = $entry['transaction_id'];
            }

            if ($eligible && ($points > 0 || $xp > 0)) {
                $this->recordGamificationRewards($challenge, $locked, $points, $xp);
            }

            $locked->update([
                'points_awarded' => $points,
                'xp_awarded' => $xp,
                'wallet_minor_awarded' => $walletMinor,
                'wallet_transaction_id' => $walletTransactionId,
                'rewarded' => $eligible,
                'settled_at' => now(),
            ]);

            return $this->rewardData($locked->refresh());
        });
    }

    /**
     * Challenge-only public standings: best finalized percentage per student.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function standings(Challenge $challenge, int $perPage): array
    {
        $rankings = $this->standingsQuery($challenge)->paginate($perPage);
        $startRank = ($rankings->currentPage() - 1) * $rankings->perPage();

        return [
            'data' => collect($rankings->items())
                ->values()
                ->map(fn (object $row, int $index): array => $this->entryData($row, $startRank + $index + 1))
                ->all(),
            'meta' => [
                'challenge_id' => $challenge->id,
                'current_page' => $rankings->currentPage(),
                'last_page' => $rankings->lastPage(),
                'per_page' => $rankings->perPage(),
                'total' => $rankings->total(),
            ],
        ];
    }

    /**
     * Safe challenge payload shared by list, detail, start, and me responses.
     *
     * @return array<string, mixed>
     */
    public function challengeData(Challenge $challenge, int $participantCount = 0): array
    {
        $now = now();
        $test = $challenge->relationLoaded('test') ? $challenge->test : $challenge->test()->first();

        return [
            'id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'period' => $challenge->period,
            'timezone' => $challenge->timezone,
            'status' => $this->status($challenge, $now),
            'is_active' => $challenge->is_active,
            'starts_at' => $challenge->starts_at->toISOString(),
            'ends_at' => $challenge->ends_at->toISOString(),
            'current_occurrence' => $this->occurrenceWindow($challenge, $now),
            'duration_minutes' => (int) $challenge->duration_minutes,
            'attempts_allowed' => (int) $challenge->attempts_allowed,
            'reward' => [
                'points' => (int) $challenge->reward_points,
                'xp' => (int) $challenge->reward_xp,
                'wallet_minor' => (int) $challenge->reward_wallet_minor,
                'currency' => WalletService::CURRENCY,
            ],
            'test' => $test ? [
                'id' => $test->id,
                'title' => $test->title,
                'question_count' => (int) $test->question_count,
                'duration_minutes' => (int) $test->duration_minutes,
                'total_marks' => (float) $test->total_marks,
                'passing_score' => (float) $test->passing_score,
                'is_premium' => (bool) $test->is_premium,
            ] : null,
            'participant_count' => $participantCount,
        ];
    }

    /**
     * Participation payload; never exposes another user's data.
     *
     * @return array<string, mixed>
     */
    public function participationData(ChallengeParticipation $participation): array
    {
        $attempt = $participation->relationLoaded('attempt') ? $participation->attempt : $participation->attempt()->first();

        return [
            'id' => $participation->id,
            'occurrence_key' => $participation->occurrence_key,
            'attempt_index' => (int) $participation->attempt_index,
            'attempt_id' => $participation->attempt_id,
            'attempt' => $attempt ? $this->attemptData($attempt) : null,
            'reward' => $this->rewardData($participation),
        ];
    }

    /**
     * Attempt payload mirroring the Exam module start/resume contract so the
     * standard /api/attempts answer-save, resume, and submit endpoints work
     * unchanged against a challenge attempt.
     *
     * @return array<string, mixed>
     */
    public function attemptData(ExamAttempt $attempt): array
    {
        $data = [
            'id' => $attempt->id,
            'test_id' => $attempt->test_id,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at->toISOString(),
            'expires_at' => $attempt->expires_at->toISOString(),
            'finished_at' => $attempt->finished_at?->toISOString(),
            'time_remaining_seconds' => $attempt->status === 'in_progress'
                ? max(0, (int) ceil(now()->diffInSeconds($attempt->expires_at, false)))
                : 0,
            'total_marks' => (float) $attempt->total_marks,
        ];

        if ($attempt->relationLoaded('answers')) {
            $data['questions'] = $attempt->answers
                ->map(fn ($answer): array => [
                    'id' => $answer->id,
                    'sequence' => $answer->sequence,
                    'question_text' => $answer->question_text,
                    'options' => $answer->options,
                    'points' => $answer->points,
                    'selected_option' => $answer->selected_option,
                ])
                ->values()
                ->all();
        }

        if ($attempt->status !== 'in_progress') {
            $data['result'] = [
                'correct_count' => $attempt->correct_count,
                'incorrect_count' => $attempt->incorrect_count,
                'unanswered_count' => $attempt->unanswered_count,
                'score' => $attempt->score,
                'total_marks' => $attempt->total_marks,
                'percentage' => $attempt->percentage,
                'passed' => $attempt->passed,
                'duration_seconds' => $attempt->duration_seconds,
            ];
        }

        return $data;
    }

    /**
     * Occurrence bucket for a point in time, computed in the challenge's
     * timezone: daily:YYYY-MM-DD | weekly:YYYY-Www | once.
     */
    public function occurrenceKey(Challenge $challenge, \DateTimeInterface $now): string
    {
        $local = CarbonImmutable::parse($now)->setTimezone($challenge->timezone);

        return match ($challenge->period) {
            'daily' => 'daily:'.$local->toDateString(),
            'weekly' => 'weekly:'.$local->format('o-\WW'),
            default => 'once',
        };
    }

    /**
     * Current occurrence window (UTC), intersected with the overall range.
     *
     * @return array{key: string, starts_at: string, ends_at: string}|null
     */
    public function occurrenceWindow(Challenge $challenge, \DateTimeInterface $now): ?array
    {
        $local = CarbonImmutable::parse($now)->setTimezone($challenge->timezone);

        [$start, $end] = match ($challenge->period) {
            'daily' => [$local->copy()->startOfDay(), $local->copy()->startOfDay()->addDay()],
            'weekly' => [$local->copy()->startOfWeek(CarbonImmutable::MONDAY), $local->copy()->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
            default => [
                $challenge->starts_at->toImmutable(),
                $challenge->ends_at->toImmutable(),
            ],
        };

        $start = $start->utc();
        $end = $end->utc();
        $rangeStart = $challenge->starts_at->toImmutable()->utc();
        $rangeEnd = $challenge->ends_at->toImmutable()->utc();

        $windowStart = $start->greaterThan($rangeStart) ? $start : $rangeStart;
        $windowEnd = $end->lessThan($rangeEnd) ? $end : $rangeEnd;

        if ($windowStart->greaterThanOrEqualTo($windowEnd)) {
            return null;
        }

        return [
            'key' => $this->occurrenceKey($challenge, $now),
            'starts_at' => $windowStart->toISOString(),
            'ends_at' => $windowEnd->toISOString(),
        ];
    }

    /**
     * Grouped standings query. Only finalized attempts of active students
     * appear; the best percentage per student is ranked by score desc, then
     * earlier first completion, then stable user ID.
     */
    private function standingsQuery(Challenge $challenge, bool $includeOrder = true): Builder
    {
        $query = DB::table('challenge_participations')
            ->join('attempts', 'attempts.id', '=', 'challenge_participations.attempt_id')
            ->join('users', 'users.id', '=', 'challenge_participations.user_id')
            ->where('challenge_participations.challenge_id', $challenge->id)
            ->where('users.role', 'student')
            ->where('users.is_active', true)
            ->whereIn('attempts.status', ['submitted', 'expired'])
            ->whereNotNull('attempts.percentage')
            ->whereNotNull('attempts.finished_at')
            ->select(
                'challenge_participations.user_id',
                'users.name as display_name',
                'users.avatar_path',
                DB::raw('MAX(attempts.percentage) as score'),
                DB::raw('COUNT(*) as attempt_count'),
                DB::raw('MIN(attempts.finished_at) as achieved_at'),
            )
            ->groupBy(
                'challenge_participations.user_id',
                'users.name',
                'users.avatar_path',
            );

        if ($includeOrder) {
            $query->orderByDesc('score')
                ->orderBy('achieved_at')
                ->orderBy('challenge_participations.user_id');
        }

        return $query;
    }

    private function rankOf(Challenge $challenge, object $mine): int
    {
        $ranked = $this->standingsQuery($challenge, includeOrder: false);

        return (int) DB::query()
            ->fromSub($ranked, 'challenge_standings')
            ->where(function ($query) use ($mine): void {
                $query->where('score', '>', $mine->score)
                    ->orWhere(function ($query) use ($mine): void {
                        $query->where('score', '=', $mine->score)
                            ->where('achieved_at', '<', $mine->achieved_at);
                    })
                    ->orWhere(function ($query) use ($mine): void {
                        $query->where('score', '=', $mine->score)
                            ->where('achieved_at', '=', $mine->achieved_at)
                            ->where('user_id', '<', $mine->user_id);
                    });
            })
            ->count() + 1;
    }

    private function entryData(object $row, int $rank): array
    {
        return [
            'rank' => $rank,
            'display_name' => $row->display_name,
            'avatar_url' => $row->avatar_path
                ? Storage::disk('public')->url($row->avatar_path)
                : null,
            'score' => round((float) $row->score, 2),
            'attempt_count' => (int) $row->attempt_count,
            'achieved_at' => CarbonImmutable::parse($row->achieved_at, 'UTC')->toISOString(),
        ];
    }

    private function recordGamificationRewards(Challenge $challenge, ChallengeParticipation $participation, int $points, int $xp): void
    {
        $idempotencyKey = $this->ledgerKey($challenge, $participation);
        $row = [
            'user_id' => $participation->user_id,
            'source_type' => 'challenge',
            'source_id' => $challenge->id,
            // Occurrence-scoped reward type keeps the ledger's per-source
            // uniqueness from blocking the next daily/weekly occurrence.
            'reward_type' => 'challenge_'.str_replace(':', '_', $participation->occurrence_key),
            'idempotency_key' => $idempotencyKey,
            'description' => 'Challenge completion: '.$challenge->title,
            'created_at' => now(),
        ];

        if ($points > 0 && ! DB::table('point_transactions')->where('idempotency_key', $idempotencyKey)->exists()) {
            DB::table('point_transactions')->insert($row + ['amount' => $points]);
        }

        if ($xp > 0 && ! DB::table('xp_events')->where('idempotency_key', $idempotencyKey)->exists()) {
            DB::table('xp_events')->insert($row + ['amount' => $xp]);
        }
    }

    private function ledgerKey(Challenge $challenge, ChallengeParticipation $participation): string
    {
        return sprintf(
            'challenge:%d:user:%d:occ:%s',
            $challenge->id,
            $participation->user_id,
            $participation->occurrence_key,
        );
    }

    private function walletKey(Challenge $challenge, ChallengeParticipation $participation): string
    {
        return sprintf(
            'challenge:%d:user:%d:occ:%s:wallet',
            $challenge->id,
            $participation->user_id,
            $participation->occurrence_key,
        );
    }

    /**
     * @return array<int, int> challenge id => distinct participant count
     */
    private function participantCounts(array $challengeIds): array
    {
        if ($challengeIds === []) {
            return [];
        }

        return DB::table('challenge_participations')
            ->whereIn('challenge_id', $challengeIds)
            ->groupBy('challenge_id')
            ->select('challenge_id', DB::raw('COUNT(DISTINCT user_id) as participant_count'))
            ->get()
            ->reduce(fn (array $counts, object $row): array => $counts + [(int) $row->challenge_id => (int) $row->participant_count], []);
    }

    private function rewardData(ChallengeParticipation $participation): array
    {
        return [
            'settled' => $participation->settled_at !== null,
            'awarded' => $participation->rewarded,
            'points' => (int) $participation->points_awarded,
            'xp' => (int) $participation->xp_awarded,
            'wallet_minor' => (int) $participation->wallet_minor_awarded,
            'wallet_transaction_id' => $participation->wallet_transaction_id,
            'settled_at' => $participation->settled_at?->toISOString(),
        ];
    }

    private function status(Challenge $challenge, \DateTimeInterface $now): string
    {
        if (! $challenge->is_active) {
            return 'disabled';
        }
        if ($now < $challenge->starts_at) {
            return 'upcoming';
        }
        if ($now >= $challenge->ends_at) {
            return 'ended';
        }

        return 'active';
    }
}
