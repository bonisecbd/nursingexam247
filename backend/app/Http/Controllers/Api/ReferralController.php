<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function me(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json(['referral' => $referrals->dashboard($request->user())]);
    }

    public function invites(Request $request, ReferralService $referrals): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($referrals->invites($request->user(), $validated['per_page'] ?? 15));
    }

    public function redeem(Request $request, ReferralService $referrals): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+$/'],
        ]);
        $referrals->redeemDuringOnboarding($request->user(), $validated['code']);

        return response()->json([
            'message' => 'Referral code linked to your account. It is pending qualification and does not grant an immediate reward.',
            'status' => 'pending',
        ], 201);
    }
}
