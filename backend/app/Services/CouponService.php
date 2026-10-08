<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Server-side coupon validation, quoting, reservation and redemption.
 *
 * Money values are integer poisha (minor units). Clients never supply a
 * discount, total or redemption state; every rule below is enforced here.
 */
class CouponService
{
    /** How long an unpaid coupon reservation is held before it is released. */
    public const RESERVATION_TTL_MINUTES = 30;

    /**
     * Quote a coupon against a server-known price (read-only, no reservation).
     *
     * @throws ValidationException
     */
    public function quote(User $user, string $code, int $subtotalMinor, string $currency, ?int $productId): array
    {
        $coupon = $this->usableCoupon($user, $code, $subtotalMinor, $currency, $productId);
        $discount = $this->discountFor($coupon, $subtotalMinor);

        return [
            'coupon_id' => (int) $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_minor' => $discount,
            'subtotal_minor' => $subtotalMinor,
            'payable_minor' => max(0, $subtotalMinor - $discount),
            'currency' => $currency,
        ];
    }

    /**
     * Atomically reserve a validated coupon for an order inside the caller's transaction.
     *
     * @throws ValidationException
     */
    public function reserve(User $user, string $code, int $orderId, int $subtotalMinor, string $currency, ?int $productId): array
    {
        $coupon = $this->usableCoupon($user, $code, $subtotalMinor, $currency, $productId, lock: true);
        $discount = $this->discountFor($coupon, $subtotalMinor);

        DB::table('coupon_redemptions')->insert([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $orderId,
            'status' => 'reserved',
            'discount_minor' => $discount,
            'currency' => $currency,
            'expires_at' => now()->addMinutes(self::RESERVATION_TTL_MINUTES),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'coupon_id' => (int) $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_minor' => $discount,
            'subtotal_minor' => $subtotalMinor,
            'payable_minor' => max(0, $subtotalMinor - $discount),
            'currency' => $currency,
        ];
    }

    /**
     * Turn the order's reservation into a completed redemption (caller's transaction).
     */
    public function completeForOrder(int $orderId): void
    {
        DB::table('coupon_redemptions')
            ->where('order_id', $orderId)
            ->where('status', 'reserved')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Release reservations for orders that were never paid.
     */
    public function releaseExpiredReservations(): int
    {
        return DB::table('coupon_redemptions')
            ->where('status', 'reserved')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'released',
                'updated_at' => now(),
            ]);
    }

    /**
     * Validate a coupon for a user/price/product and return the normalized row.
     *
     * @throws ValidationException
     */
    private function usableCoupon(
        User $user,
        string $code,
        int $subtotalMinor,
        string $currency,
        ?int $productId,
        bool $lock = false,
    ): object {
        $query = DB::table('coupons')->where('code', $this->normalize($code));
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();

        if (! $coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages([
                'code' => ['The coupon code is invalid or inactive.'],
            ]);
        }

        $now = now();
        if ($coupon->starts_at && $now->lessThan(CarbonImmutable::parse($coupon->starts_at, 'UTC'))) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not active yet.'],
            ]);
        }
        if ($coupon->ends_at && ! $now->lessThan(CarbonImmutable::parse($coupon->ends_at, 'UTC'))) {
            throw ValidationException::withMessages([
                'code' => ['This coupon has expired.'],
            ]);
        }
        if (strtoupper($coupon->currency) !== strtoupper($currency)) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not valid for this currency.'],
            ]);
        }
        if (! $this->coversProduct($coupon, $productId)) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not valid for the selected product.'],
            ]);
        }
        if ($subtotalMinor < (int) $coupon->minimum_order_amount_minor) {
            throw ValidationException::withMessages([
                'code' => ['This coupon requires a minimum order of '.$coupon->minimum_order_amount_minor.' '.$coupon->currency.' minor units.'],
            ]);
        }

        $this->assertLimitsAvailable($coupon, $user);

        return $coupon;
    }

    /**
     * @throws ValidationException
     */
    private function assertLimitsAvailable(object $coupon, User $user): void
    {
        if ($coupon->global_redemption_limit !== null) {
            $used = $this->activeRedemptionCount((int) $coupon->id);
            if ($used >= (int) $coupon->global_redemption_limit) {
                throw ValidationException::withMessages([
                    'code' => ['This coupon has reached its redemption limit.'],
                ]);
            }
        }

        if ($coupon->per_user_redemption_limit !== null) {
            $used = $this->activeRedemptionCount((int) $coupon->id, $user->id);
            if ($used >= (int) $coupon->per_user_redemption_limit) {
                throw ValidationException::withMessages([
                    'code' => ['You have already redeemed this coupon the maximum number of times.'],
                ]);
            }
        }
    }

    /**
     * Count reservations that still hold a slot plus completed redemptions.
     * Expired reservations never count, so a stale hold cannot block a coupon forever.
     */
    public function activeRedemptionCount(int $couponId, ?int $userId = null): int
    {
        return DB::table('coupon_redemptions')
            ->where('coupon_id', $couponId)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->where(function ($query): void {
                $query->where('status', 'completed')
                    ->orWhere(fn ($query) => $query->where('status', 'reserved')->where('expires_at', '>', now()));
            })
            ->count();
    }

    /**
     * Integer-only discount math; the result never exceeds the eligible subtotal.
     */
    public function discountFor(object $coupon, int $subtotalMinor): int
    {
        $discount = $coupon->discount_type === 'percentage'
            ? intdiv($subtotalMinor * (int) $coupon->discount_percent, 100)
            : (int) $coupon->discount_amount_minor;

        return min(max($discount, 0), $subtotalMinor);
    }

    private function coversProduct(object $coupon, ?int $productId): bool
    {
        if ($coupon->eligible_product_ids === null) {
            return true;
        }
        if ($productId === null) {
            return false;
        }

        $eligible = json_decode($coupon->eligible_product_ids, true, 512, JSON_THROW_ON_ERROR);

        return is_array($eligible) && in_array($productId, $eligible, true);
    }

    private function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }
}
