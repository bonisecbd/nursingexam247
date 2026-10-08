<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Authenticated quote/check before payment. Read-only: no reservation is
     * made here; the reservation happens atomically during checkout.
     */
    public function validate(Request $request, CouponService $coupons): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'product_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        $package = SubscriptionPackage::query()->find($validated['product_id']);
        if (! $package || ! $package->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is not available.'],
            ]);
        }

        $quote = $coupons->quote(
            $request->user(),
            $validated['code'],
            (int) $package->price_minor,
            $package->currency,
            $package->id,
        );

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $quote['coupon_id'],
                'code' => $quote['code'],
                'discount_type' => $quote['discount_type'],
            ],
            'quote' => [
                'product_id' => $package->id,
                'subtotal_minor' => $quote['subtotal_minor'],
                'discount_minor' => $quote['discount_minor'],
                'payable_minor' => $quote['payable_minor'],
                'currency' => $quote['currency'],
            ],
        ]);
    }
}
