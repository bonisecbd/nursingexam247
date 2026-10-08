<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelTest;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Public catalogue of purchasable premium packages. */
    public function packages(SubscriptionService $subscriptions): JsonResponse
    {
        return response()->json($subscriptions->packages());
    }

    public function me(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($subscriptions->me($request->user(), $validated['per_page'] ?? 15));
    }

    /**
     * Create a pending order (server-calculated price, optional coupon).
     * No entitlement is granted here; only a verified payment activates it.
     */
    public function checkout(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $result = $subscriptions->checkout(
            $request->user(),
            $validated['package_id'],
            $validated['coupon_code'] ?? null,
        );

        return response()->json($result, 201);
    }

    /**
     * Server-side access explanation for one published test.
     */
    public function testAccess(Request $request, int $test, SubscriptionService $subscriptions): JsonResponse
    {
        $model = ModelTest::query()->where('status', 'published')->find($test);
        abort_unless($model !== null, 404);

        return response()->json(['access' => $subscriptions->testAccess($request->user(), $model)]);
    }
}
