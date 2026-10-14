<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Services\ChallengeService;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Challenge Module (docs/MODULES.md section 16).
 *
 * Read endpoints are public like the test catalogue; starting, personal
 * state, and submission require a bearer token. Challenge attempts reuse the
 * Exam module contract: answer-save, resume, and submit all run through the
 * normal /api/attempts endpoints (this controller delegates submission to
 * ExamAttemptController so scoring stays identical).
 */
class ChallengeController extends Controller
{
    /**
     * GET /api/challenges — currently available challenges.
     */
    public function index(Request $request, ChallengeService $challenges): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', Rule::in(Challenge::PERIODS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $challenges->paginate($validated['period'] ?? null, $validated['per_page'] ?? 15)
        );
    }

    /**
     * GET /api/challenges/{challenge} — safe details and question rules.
     */
    public function show(int $challenge, ChallengeService $challenges): JsonResponse
    {
        $found = Challenge::query()->findOrFail($challenge);

        return response()->json([
            'challenge' => $challenges->challengeData(
                $found,
                (int) $found->participations()->distinct()->count('user_id'),
            ),
        ]);
    }

    /**
     * GET /api/challenges/{challenge}/me — own attempts, rank, and rewards.
     */
    public function me(Request $request, int $challenge, ChallengeService $challenges): JsonResponse
    {
        $found = Challenge::query()->findOrFail($challenge);

        return response()->json($challenges->me($request->user(), $found));
    }

    /**
     * POST /api/challenges/{challenge}/attempts — start (or resume) an
     * eligible challenge attempt. Mirrors POST /api/attempts: 201 for a new
     * attempt, 200 with resumed=true for the live one.
     */
    public function start(Request $request, int $challenge, ChallengeService $challenges): JsonResponse
    {
        $found = Challenge::query()->findOrFail($challenge);
        $result = $challenges->start($request->user(), $found);

        return response()->json([
            'challenge' => $challenges->challengeData(
                $found,
                (int) $found->participations()->distinct()->count('user_id'),
            ),
            'participation' => $challenges->participationData($result['participation']),
            'attempt' => $challenges->attemptData($result['participation']->attempt),
            'resumed' => $result['resumed'],
        ], $result['resumed'] ? 200 : 201);
    }

    /**
     * POST /api/challenges/{challenge}/attempts/{attempt}/submit — same
     * contract as POST /api/attempts/{attempt}/submit, plus challenge reward
     * settlement. Delegates to the Exam controller so scoring, expiry, and
     * gamification behaviour cannot diverge; retries stay idempotent.
     */
    public function submitAttempt(
        Request $request,
        int $challenge,
        int $attempt,
        ExamAttemptController $exams,
        ChallengeService $challenges,
    ): JsonResponse {
        $found = Challenge::query()->findOrFail($challenge);
        $participation = ChallengeParticipation::query()
            ->where('challenge_id', $found->id)
            ->where('user_id', $request->user()->id)
            ->where('attempt_id', $attempt)
            ->firstOrFail();

        $response = $exams->submit($request, $attempt, app(GamificationService::class));
        $data = $response->getData(true);

        $data['challenge'] = [
            'id' => $found->id,
            'participation_id' => $participation->id,
            'occurrence_key' => $participation->occurrence_key,
            'reward' => $challenges->settleParticipation($participation),
        ];

        return response()->json($data, $response->getStatusCode());
    }

    /**
     * GET /api/challenges/{challenge}/leaderboard — challenge-only public
     * standings. Finished participations are settled first so rewards are
     * issued before the table is read.
     */
    public function leaderboard(Request $request, int $challenge, ChallengeService $challenges): JsonResponse
    {
        $found = Challenge::query()->findOrFail($challenge);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $challenges->settle($found);

        return response()->json($challenges->standings($found, $validated['per_page'] ?? 50));
    }
}
