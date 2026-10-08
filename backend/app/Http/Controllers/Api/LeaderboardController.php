<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboards): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'overall'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($leaderboards->paginate($validated['period'], $validated['per_page'] ?? 50));
    }

    public function me(Request $request, string $period, LeaderboardService $leaderboards): JsonResponse
    {
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly', 'overall'], true), 404);
        $user = $request->user();

        return response()->json([
            'leaderboard_opt_in' => $user->role === 'student' && $user->is_active && $user->leaderboard_opt_in,
            'position' => $leaderboards->position($period, $user->id),
        ]);
    }
}
