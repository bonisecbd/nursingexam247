<?php

namespace App\Services;

use App\Models\ModelTest;
use App\Models\PackageEntitlement;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Time-bound premium packages and server-side entitlement checks.
 *
 * A subscription grants access only when it is `active` and its UTC window
 * contains the current instant. Purchases never silently extend another
 * subscription: every confirmed order creates its own record.
 */
class SubscriptionService
{
    public function __construct(private readonly OrderService $orders) {}

    public function packages(): array
    {
        $packages = SubscriptionPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $entitlements = PackageEntitlement::query()
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->groupBy('package_id');

        return [
            'data' => $packages->map(fn (SubscriptionPackage $package): array => $this->packageData(
                $package,
                $entitlements->get($package->id) ?? collect(),
            ))->values()->all(),
        ];
    }

    /**
     * Create a pending order for a package, optionally applying a coupon.
     *
     * @throws ValidationException
     */
    public function checkout(User $user, int $packageId, ?string $couponCode): array
    {
        $package = SubscriptionPackage::query()->find($packageId);
        if (! $package || ! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => ['The selected package is not available.'],
            ]);
        }

        $order = $this->orders->createSubscriptionOrder($user, $package, $couponCode);

        return [
            'message' => $order->status === 'paid'
                ? 'Order settled without a provider payment because the payable total is zero.'
                : 'Order created. Complete the payment to activate the subscription.',
            'order' => $this->orders->orderData($order),
            'requires_payment' => $order->status !== 'paid',
            'package' => $this->packageData($package),
        ];
    }

    public function me(User $user, int $perPage): array
    {
        $subscriptions = Subscription::query()
            ->with(['package:id,code,name,duration_days', 'order:id,reference'])
            ->where('user_id', $user->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($subscriptions->items())
            ->map(fn (Subscription $subscription): array => $this->subscriptionData($subscription))
            ->all();

        $active = $this->activeSubscriptions($user);

        return [
            'subscriptions' => $items,
            'active' => $active->isNotEmpty(),
            'entitlements' => $active->isEmpty()
                ? ['all_premium_tests' => false, 'test_ids' => []]
                : $this->entitlementsFor($active),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ];
    }

    /**
     * Explain whether the authenticated user may access a published test.
     */
    public function testAccess(User $user, ModelTest $test): array
    {
        $active = $this->activeSubscriptions($user);

        if (! $test->is_premium) {
            return [
                'test_id' => $test->id,
                'is_premium' => false,
                'accessible' => true,
                'reason' => 'free_test',
                'subscription' => null,
                'entitlements' => null,
            ];
        }

        $entitlements = $active->isEmpty() ? null : $this->entitlementsFor($active);
        $granted = $entitlements !== null
            && ($entitlements['all_premium_tests'] || in_array($test->id, $entitlements['test_ids'], true));

        if ($granted) {
            return [
                'test_id' => $test->id,
                'is_premium' => true,
                'accessible' => true,
                'reason' => 'premium_entitlement_active',
                'subscription' => $this->subscriptionData($active->first()),
                'entitlements' => $entitlements,
            ];
        }

        $hasAny = Subscription::query()->where('user_id', $user->id)->exists();

        return [
            'test_id' => $test->id,
            'is_premium' => true,
            'accessible' => false,
            'reason' => $hasAny ? 'premium_subscription_not_active' : 'premium_subscription_required',
            'subscription' => null,
            'entitlements' => null,
        ];
    }

    /**
     * Server-side gate for every premium read/start/attempt endpoint.
     */
    public function canAccessTest(User $user, ModelTest $test): bool
    {
        if (! $test->is_premium) {
            return true;
        }

        $active = $this->activeSubscriptions($user);
        if ($active->isEmpty()) {
            return false;
        }

        $entitlements = $this->entitlementsFor($active);

        return $entitlements['all_premium_tests']
            || in_array($test->id, $entitlements['test_ids'], true);
    }

    /**
     * True while the user holds any unexpired premium entitlement.
     */
    public function hasActiveEntitlement(User $user): bool
    {
        return $this->activeSubscriptions($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function activeSubscriptions(User $user): Collection
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderByDesc('ends_at')
            ->get();
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return array{all_premium_tests: bool, test_ids: list<int>}
     */
    private function entitlementsFor(Collection $subscriptions): array
    {
        $rows = PackageEntitlement::query()
            ->whereIn('package_id', $subscriptions->pluck('package_id')->unique()->values())
            ->get();

        return [
            'all_premium_tests' => $rows->contains('entitlement_type', 'all_premium_tests'),
            'test_ids' => $rows
                ->where('entitlement_type', 'test')
                ->pluck('test_id')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function packageData(SubscriptionPackage $package, ?Collection $entitlements = null): array
    {
        $entitlements ??= PackageEntitlement::query()
            ->where('package_id', $package->id)
            ->get();

        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => $package->name,
            'description' => $package->description,
            'price_minor' => $package->price_minor,
            'currency' => $package->currency,
            'duration_days' => $package->duration_days,
            'is_active' => $package->is_active,
            'entitlements' => [
                'all_premium_tests' => $entitlements->contains('entitlement_type', 'all_premium_tests'),
                'test_ids' => $entitlements
                    ->where('entitlement_type', 'test')
                    ->pluck('test_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function subscriptionData(Subscription $subscription): array
    {
        $isActive = $subscription->status === 'active'
            && $subscription->starts_at !== null
            && $subscription->starts_at->lessThanOrEqualTo(now())
            && $subscription->ends_at !== null
            && $subscription->ends_at->greaterThan(now());

        return [
            'id' => $subscription->id,
            'status' => $isActive ? 'active' : ($subscription->status === 'active' ? 'expired' : $subscription->status),
            'source' => $subscription->source,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'package' => $subscription->package ? [
                'id' => $subscription->package->id,
                'code' => $subscription->package->code,
                'name' => $subscription->package->name,
                'duration_days' => $subscription->package->duration_days,
            ] : null,
            'order_reference' => $subscription->order?->reference,
        ];
    }
}
