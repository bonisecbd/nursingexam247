<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates orders and applies confirmed payments to the purchased goods.
 *
 * All money values are integer poisha. `fulfillPaidOrder` must run inside the
 * caller's transaction so that payment status, entitlement and wallet credit
 * commit or roll back together.
 */
class OrderService
{
    /** Unpaid orders (and their coupon reservations) are released after this window. */
    public const ORDER_TTL_MINUTES = 30;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly CouponService $coupons,
    ) {}

    /**
     * Build a pending subscription order with a server-calculated price quote.
     *
     * @throws ValidationException
     */
    public function createSubscriptionOrder(User $user, SubscriptionPackage $package, ?string $couponCode): Order
    {
        return DB::transaction(function () use ($user, $package, $couponCode): Order {
            $this->coupons->releaseExpiredReservations();

            $subtotal = (int) $package->price_minor;
            $quote = null;
            if ($couponCode !== null && $couponCode !== '') {
                $quote = $this->coupons->quote($user, $couponCode, $subtotal, $package->currency, $package->id);
            }

            $discount = $quote['discount_minor'] ?? 0;
            $order = Order::query()->create([
                'user_id' => $user->id,
                'purpose' => 'subscription',
                'package_id' => $package->id,
                'currency' => $package->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'total_minor' => max(0, $subtotal - $discount),
                'coupon_id' => $quote['coupon_id'] ?? null,
                'reference' => $this->newReference('ORD'),
                'status' => 'pending',
                'expires_at' => now()->addMinutes(self::ORDER_TTL_MINUTES),
            ]);

            if ($quote !== null) {
                // Re-validates under a row lock so concurrent checkouts cannot
                // over-redeem a limited coupon.
                $this->coupons->reserve(
                    $user,
                    $couponCode,
                    $order->id,
                    $subtotal,
                    $package->currency,
                    $package->id,
                );
            }

            if ($order->total_minor === 0) {
                // A fully discounted order never needs a provider payment.
                $this->fulfillPaidOrder($order, 'coupon');
            }

            return $order->refresh();
        });
    }

    /**
     * Build a pending wallet top-up order paid through the Payment module.
     *
     * @throws ValidationException
     */
    public function createTopupOrder(User $user, int $amountMinor): Order
    {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => ['The top-up amount must be a positive integer amount in poisha.'],
            ]);
        }

        return DB::transaction(function () use ($user, $amountMinor): Order {
            return Order::query()->create([
                'user_id' => $user->id,
                'purpose' => 'wallet_topup',
                'package_id' => null,
                'currency' => WalletService::CURRENCY,
                'subtotal_minor' => $amountMinor,
                'discount_minor' => 0,
                'total_minor' => $amountMinor,
                'coupon_id' => null,
                'reference' => $this->newReference('ORD'),
                'status' => 'pending',
                'expires_at' => now()->addMinutes(self::ORDER_TTL_MINUTES),
            ]);
        });
    }

    /**
     * Apply a confirmed, unpaid order: activate the subscription or credit the
     * wallet, complete the coupon redemption, then mark the order paid.
     *
     * Must be called inside an open transaction. Failures throw and roll back
     * everything the caller already wrote (including a payment status change).
     *
     * @throws \RuntimeException when the order cannot be fulfilled
     */
    public function fulfillPaidOrder(Order $order, string $source): void
    {
        if ($order->status === 'paid') {
            return;
        }
        if ($order->status !== 'pending') {
            throw new \RuntimeException('Only pending orders can be fulfilled (order '.$order->reference.' is '.$order->status.').');
        }

        if ($order->purpose === 'subscription') {
            $this->activateSubscription($order, $source);
        } elseif ($order->purpose === 'wallet_topup') {
            if ($order->total_minor > 0) {
                $this->wallets->credit(
                    $order->user_id,
                    $order->total_minor,
                    'order',
                    $order->id,
                    'order:'.$order->id.':wallet_credit',
                    'Wallet top-up for order '.$order->reference,
                );
            }
        } else {
            throw new \RuntimeException('Unsupported order purpose "'.$order->purpose.'" for order '.$order->reference.'.');
        }

        $this->coupons->completeForOrder($order->id);

        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();
    }

    public function orderData(Order $order): array
    {
        $order->loadMissing(['package:id,code,name,duration_days']);

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'purpose' => $order->purpose,
            'status' => $order->status,
            'currency' => $order->currency,
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'coupon_id' => $order->coupon_id,
            'package' => $order->package ? [
                'id' => $order->package->id,
                'code' => $order->package->code,
                'name' => $order->package->name,
                'duration_days' => $order->package->duration_days,
            ] : null,
            'paid_at' => $order->paid_at?->toISOString(),
            'expires_at' => $order->expires_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
        ];
    }

    public function newReference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.Str::upper(Str::random(16));
        } while (DB::table('orders')->where('reference', $reference)->exists());

        return $reference;
    }

    private function activateSubscription(Order $order, string $source): void
    {
        if (Subscription::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        $package = $order->package_id
            ? SubscriptionPackage::query()->find($order->package_id)
            : null;

        if (! $package) {
            throw new \RuntimeException('The package for order '.$order->reference.' is no longer available; the payment was not applied.');
        }

        $startsAt = now();
        Subscription::query()->create([
            'user_id' => $order->user_id,
            'package_id' => $package->id,
            'order_id' => $order->id,
            'status' => 'active',
            'source' => $source,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($package->duration_days),
        ]);
    }
}
