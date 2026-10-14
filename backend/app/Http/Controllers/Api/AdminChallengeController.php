<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ModelTest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin management for the Challenge Module. The `admin` role is enforced
 * server-side on every operation, matching the other /api/admin endpoints.
 */
class AdminChallengeController extends Controller
{
    /**
     * GET /api/admin/challenges — paginated challenge management list.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->indexChallenges($request);
    }

    /**
     * POST /api/admin/challenges — create a challenge.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $this->validateDefinition($request->validate($this->rules(partial: false)));

        $challenge = Challenge::query()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'period' => $validated['period'],
            'test_id' => $validated['test_id'],
            'timezone' => $validated['timezone'] ?? 'Asia/Dhaka',
            'starts_at' => $this->toCarbon($validated['starts_at'])->utc(),
            'ends_at' => $this->toCarbon($validated['ends_at'])->utc(),
            'duration_minutes' => $validated['duration_minutes'],
            'attempts_allowed' => $validated['attempts_allowed'],
            'reward_points' => $validated['reward_points'] ?? 0,
            'reward_xp' => $validated['reward_xp'] ?? 0,
            'reward_wallet_minor' => $validated['reward_wallet_minor'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Challenge created.',
            'challenge' => $this->challengeData($challenge),
        ], 201);
    }

    /**
     * PATCH /api/admin/challenges/{challenge} — partial update. The test and
     * period are frozen once students have participations so occurrence keys
     * and linked attempts cannot be rewritten under them.
     */
    public function update(Request $request, int $challenge): JsonResponse
    {
        $this->authorizeAdmin($request);
        $existing = Challenge::query()->findOrFail($challenge);
        $validated = $request->validate($this->rules(partial: true));

        $merged = array_merge($existing->only([
            'title',
            'description',
            'period',
            'test_id',
            'timezone',
            'starts_at',
            'ends_at',
            'duration_minutes',
            'attempts_allowed',
            'reward_points',
            'reward_xp',
            'reward_wallet_minor',
            'is_active',
        ]), $validated);

        $this->validateRange($merged);

        $hasParticipations = $existing->participations()->exists();
        if ($hasParticipations) {
            foreach (['test_id', 'period'] as $frozen) {
                if (! array_key_exists($frozen, $validated)) {
                    continue;
                }

                $changed = $frozen === 'test_id'
                    ? (int) $validated['test_id'] !== (int) $existing->test_id
                    : $validated['period'] !== $existing->period;

                if ($changed) {
                    abort(409, 'The test and period of a challenge cannot change after students have joined.');
                }
            }
        }

        if (array_key_exists('test_id', $validated)) {
            $this->assertPublishedTest((int) $validated['test_id']);
        }

        $existing->fill($validated)->save();

        return response()->json([
            'message' => 'Challenge updated.',
            'challenge' => $this->challengeData($existing->fresh()),
        ]);
    }

    private function indexChallenges(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in([...Challenge::PERIODS, 'all'])],
            'status' => ['sometimes', Rule::in(['all', 'upcoming', 'active', 'ended', 'disabled'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $now = now();
        $challenges = Challenge::query()
            ->when(
                isset($validated['period']) && $validated['period'] !== 'all',
                fn ($query) => $query->where('period', $validated['period'])
            )
            ->when(($validated['status'] ?? 'all') === 'disabled', fn ($query) => $query->where('is_active', false))
            ->when(($validated['status'] ?? null) === 'upcoming', fn ($query) => $query
                ->where('is_active', true)
                ->where('starts_at', '>', $now))
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query
                ->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>', $now))
            ->when(($validated['status'] ?? null) === 'ended', fn ($query) => $query
                ->where('is_active', true)
                ->where('ends_at', '<=', $now))
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => collect($challenges->items())
                ->map(fn (Challenge $challenge): array => $this->challengeData($challenge))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $challenges->currentPage(),
                'last_page' => $challenges->lastPage(),
                'per_page' => $challenges->perPage(),
                'total' => $challenges->total(),
            ],
        ]);
    }

    private function rules(bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'period' => [$required, Rule::in(Challenge::PERIODS)],
            'test_id' => [$required, 'integer', 'exists:tests,id'],
            'timezone' => ['sometimes', 'string', 'max:64', $this->validTimezone()],
            'starts_at' => [$required, 'date'],
            'ends_at' => [$required, 'date'],
            'duration_minutes' => [$required, 'integer', 'min:1', 'max:1440'],
            'attempts_allowed' => [$required, 'integer', 'min:1', 'max:10'],
            'reward_points' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'reward_xp' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'reward_wallet_minor' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-field validation shared by create and update: the window must be
     * ordered and the referenced test must be a published test.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function validateDefinition(array $values): array
    {
        $this->validateRange($values);
        $this->assertPublishedTest((int) $values['test_id']);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function validateRange(array $values): void
    {
        if ($this->toCarbon($values['ends_at'])->lessThanOrEqualTo($this->toCarbon($values['starts_at']))) {
            throw ValidationException::withMessages([
                'ends_at' => ['The end date must be later than the start date.'],
            ]);
        }
    }

    private function assertPublishedTest(int $testId): void
    {
        $test = ModelTest::query()->find($testId);
        if (! $test || $test->status !== 'published') {
            throw ValidationException::withMessages([
                'test_id' => ['The challenge test must be a published test.'],
            ]);
        }
    }

    private function validTimezone(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            try {
                new \DateTimeZone((string) $value);
            } catch (\Exception) {
                $fail('The '.$attribute.' must be a valid timezone identifier.');
            }
        };
    }

    private function toCarbon(mixed $value): CarbonImmutable
    {
        return $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);
    }

    private function challengeData(Challenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'period' => $challenge->period,
            'timezone' => $challenge->timezone,
            'is_active' => $challenge->is_active,
            'status' => $this->status($challenge),
            'starts_at' => $challenge->starts_at->toISOString(),
            'ends_at' => $challenge->ends_at->toISOString(),
            'duration_minutes' => (int) $challenge->duration_minutes,
            'attempts_allowed' => (int) $challenge->attempts_allowed,
            'reward' => [
                'points' => (int) $challenge->reward_points,
                'xp' => (int) $challenge->reward_xp,
                'wallet_minor' => (int) $challenge->reward_wallet_minor,
            ],
            'test_id' => $challenge->test_id,
            'participant_count' => (int) $challenge->participations()->distinct()->count('user_id'),
            'created_at' => $challenge->created_at->toISOString(),
            'updated_at' => $challenge->updated_at->toISOString(),
        ];
    }

    private function status(Challenge $challenge): string
    {
        if (! $challenge->is_active) {
            return 'disabled';
        }
        if (now()->lessThan($challenge->starts_at)) {
            return 'upcoming';
        }
        if (now()->greaterThanOrEqualTo($challenge->ends_at)) {
            return 'ended';
        }

        return 'active';
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
