<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    public function me(Request $request, GamificationService $gamification): JsonResponse
    {
        return response()->json(['gamification' => $gamification->summary($request->user())]);
    }

    public function badges(GamificationService $gamification): JsonResponse
    {
        return response()->json(['data' => $gamification->publicBadges()]);
    }

    public function achievements(Request $request, GamificationService $gamification): JsonResponse
    {
        $summary = $gamification->summary($request->user());

        return response()->json([
            'completed_test_count' => $summary['completed_test_count'],
            'badges' => $summary['badges'],
        ]);
    }

    public function adminRules(Request $request, GamificationService $gamification): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'rules' => DB::table('gamification_settings')->where('id', 1)->firstOrFail([
                'points_per_test',
                'xp_per_test',
                'updated_at',
            ]),
            'level_rules' => DB::table('gamification_levels')
                ->where('version', 1)
                ->orderBy('level')
                ->get(['level', 'title', 'xp_required', 'version']),
            'badges' => $gamification->publicBadges(),
        ]);
    }

    public function updateAdminRules(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'points_per_test' => ['required', 'integer', 'min:1', 'max:10000'],
            'xp_per_test' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $settings = DB::table('gamification_settings')->where('id', 1)->lockForUpdate()->firstOrFail(['id']);
            DB::table('gamification_settings')->where('id', $settings->id)->update([
                'points_per_test' => $validated['points_per_test'],
                'xp_per_test' => $validated['xp_per_test'],
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Gamification reward rules updated. Existing ledger entries were not changed.',
            'rules' => DB::table('gamification_settings')->where('id', 1)->firstOrFail([
                'points_per_test',
                'xp_per_test',
                'updated_at',
            ]),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
