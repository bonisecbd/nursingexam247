<?php

namespace Tests\Feature;

use App\Models\ModelTest;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.webhook_secret', 'subscription-test-webhook-secret');
    }

    public function test_subscription_packages_are_public_and_only_active_packages_are_listed(): void
    {
        DB::table('subscription_packages')->insert([
            'name' => 'Retired Package',
            'code' => 'retired-package',
            'price_minor' => 5000,
            'currency' => 'BDT',
            'duration_days' => 30,
            'is_active' => false,
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/subscription-packages')->assertOk();
        $codes = array_column($response->json('data'), 'code');

        $this->assertContains('premium-monthly', $codes);
        $this->assertContains('premium-yearly', $codes);
        $this->assertNotContains('retired-package', $codes);

        $monthly = collect($response->json('data'))->firstWhere('code', 'premium-monthly');
        $this->assertSame(10000, $monthly['price_minor']);
        $this->assertSame('BDT', $monthly['currency']);
        $this->assertSame(30, $monthly['duration_days']);
        $this->assertTrue($monthly['entitlements']['all_premium_tests']);
        $this->assertSame([], $monthly['entitlements']['test_ids']);
    }

    public function test_checkout_requires_authentication_and_a_valid_available_package(): void
    {
        [, $token] = $this->createUserWithToken();

        $this->postJson('/api/subscriptions/checkout', ['package_id' => 1])->assertUnauthorized();
        $this->getJson('/api/subscriptions/me')->assertUnauthorized();
        $this->withToken($token)->postJson('/api/subscriptions/checkout', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');
        $this->withToken($token)->postJson('/api/subscriptions/checkout', ['package_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');

        DB::table('subscription_packages')->insert([
            'name' => 'Inactive Package',
            'code' => 'inactive-package',
            'price_minor' => 5000,
            'currency' => 'BDT',
            'duration_days' => 30,
            'is_active' => false,
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inactiveId = (int) DB::table('subscription_packages')->where('code', 'inactive-package')->value('id');

        $this->withToken($token)->postJson('/api/subscriptions/checkout', ['package_id' => $inactiveId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_creates_a_pending_order_with_server_calculated_totals(): void
    {
        [, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $checkout = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
        ]);

        $checkout->assertCreated()
            ->assertJsonPath('requires_payment', true)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.purpose', 'subscription')
            ->assertJsonPath('order.subtotal_minor', 10000)
            ->assertJsonPath('order.discount_minor', 0)
            ->assertJsonPath('order.total_minor', 10000)
            ->assertJsonPath('order.package.id', $packageId);

        $order = $checkout->json('order');
        $this->assertNotNull($order['reference']);
        $this->assertStringStartsWith('ORD-', $order['reference']);

        // A pending order grants nothing.
        $this->assertDatabaseCount('subscriptions', 0);
        $this->withToken($token)->getJson('/api/subscriptions/me')
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('subscriptions', []);
    }

    public function test_checkout_reserves_coupons_atomically_and_enforces_redemption_limits(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [$first, $firstToken] = $this->createUserWithToken();
        [, $secondToken] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $this->createCoupon($adminToken, [
            'code' => 'ONCEONLY',
            'discount_type' => 'percentage',
            'discount_percent' => 50,
            'global_redemption_limit' => 1,
        ]);

        $firstCheckout = $this->withToken($firstToken)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'ONCEONLY',
        ]);

        $firstCheckout->assertCreated()
            ->assertJsonPath('order.subtotal_minor', 10000)
            ->assertJsonPath('order.discount_minor', 5000)
            ->assertJsonPath('order.total_minor', 5000);

        $firstOrderId = $firstCheckout->json('order.id');
        $this->assertDatabaseHas('coupon_redemptions', [
            'order_id' => $firstOrderId,
            'user_id' => $first->id,
            'status' => 'reserved',
            'discount_minor' => 5000,
        ]);

        // The global limit is already reserved: a second student is rejected
        // and no orphan order is created.
        $ordersBefore = DB::table('orders')->count();
        $this->withToken($secondToken)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'ONCEONLY',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertSame($ordersBefore, DB::table('orders')->count());

        // A non-redeemable coupon never creates an order either.
        $this->withToken($firstToken)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'NOSUCHCODE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertSame($ordersBefore, DB::table('orders')->count());

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('coupon_redemptions', 1);
    }

    public function test_per_user_coupon_limits_apply_and_expired_reservations_are_released(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $this->createCoupon($adminToken, [
            'code' => 'PERUSER',
            'discount_type' => 'percentage',
            'discount_percent' => 20,
            'per_user_redemption_limit' => 1,
        ]);

        $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'PERUSER',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'PERUSER',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('orders', 1);

        // Once the unpaid reservation expires, the slot is released and the
        // student can redeem again.
        DB::table('coupon_redemptions')->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'PERUSER',
        ])->assertCreated();

        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(1, DB::table('coupon_redemptions')->where('status', 'released')->count());
        $this->assertSame(1, DB::table('coupon_redemptions')->where('status', 'reserved')->count());
    }

    public function test_expired_coupons_are_rejected_at_checkout_without_creating_an_order(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $this->createCoupon($adminToken, [
            'code' => 'OVERDONE',
            'discount_type' => 'percentage',
            'discount_percent' => 50,
            'ends_at' => now()->subMinute()->toISOString(),
        ]);

        $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'OVERDONE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public function test_fully_discounted_checkout_is_settled_without_a_payment(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [$user, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $this->createCoupon($adminToken, [
            'code' => 'FREE100',
            'discount_type' => 'percentage',
            'discount_percent' => 100,
        ]);

        $checkout = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
            'coupon_code' => 'FREE100',
        ]);

        $checkout->assertCreated()
            ->assertJsonPath('requires_payment', false)
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('order.discount_minor', 10000)
            ->assertJsonPath('order.total_minor', 0);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'status' => 'active']);
        $this->assertDatabaseHas('coupon_redemptions', [
            'order_id' => $checkout->json('order.id'),
            'status' => 'completed',
        ]);

        $this->withToken($token)->getJson('/api/subscriptions/me')
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('entitlements.all_premium_tests', true);
    }

    public function test_subscriptions_me_reports_active_and_expired_state_with_entitlements(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');

        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
        ])->assertCreated()->json('order.id');

        $this->fundWallet($user, $token, 20000);
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'subscription-me-key',
        ])->assertCreated();

        $active = $this->withToken($token)->getJson('/api/subscriptions/me');
        $active->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('entitlements.all_premium_tests', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('subscriptions.0.status', 'active')
            ->assertJsonPath('subscriptions.0.source', 'wallet')
            ->assertJsonPath('subscriptions.0.package.code', 'premium-monthly');

        $subscription = $active->json('subscriptions.0');
        $this->assertNotNull($subscription['starts_at']);
        $this->assertNotNull($subscription['ends_at']);
        $this->assertSame('ORD-', substr($subscription['order_reference'], 0, 4));
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            CarbonImmutable::parse($subscription['ends_at'])->timestamp,
            300,
        );

        // Expiry boundary: exactly at/after ends_at the entitlement is gone.
        DB::table('subscriptions')->update(['ends_at' => now()->toDateTimeString()]);

        $expired = $this->withToken($token)->getJson('/api/subscriptions/me');
        $expired->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('entitlements.all_premium_tests', false)
            ->assertJsonPath('subscriptions.0.status', 'expired');
    }

    public function test_test_access_gates_premium_tests_until_an_active_entitlement_exists(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $freeTest = $this->createPublishedTest('Free practice test', isPremium: false);
        $premiumTest = $this->createPublishedTest('Premium mock exam', isPremium: true);

        $this->withToken($token)->getJson('/api/tests/'.$freeTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.is_premium', false)
            ->assertJsonPath('access.accessible', true)
            ->assertJsonPath('access.reason', 'free_test');

        $this->withToken($token)->getJson('/api/tests/'.$premiumTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.is_premium', true)
            ->assertJsonPath('access.accessible', false)
            ->assertJsonPath('access.reason', 'premium_subscription_required')
            ->assertJsonPath('access.subscription', null);

        // Buy the package through the wallet.
        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $this->packageId('premium-monthly'),
        ])->assertCreated()->json('order.id');
        $this->fundWallet($user, $token, 50000);
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'premium-access-key',
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/tests/'.$premiumTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.accessible', true)
            ->assertJsonPath('access.reason', 'premium_entitlement_active')
            ->assertJsonPath('access.subscription.status', 'active');

        // Another student is not affected by someone else's subscription.
        $this->withToken($otherToken)->getJson('/api/tests/'.$premiumTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.accessible', false)
            ->assertJsonPath('access.reason', 'premium_subscription_required');

        // Once expired, access is revoked and the reason changes.
        DB::table('subscriptions')->update(['ends_at' => now()->subMinute()->toDateTimeString()]);
        $this->withToken($token)->getJson('/api/tests/'.$premiumTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.accessible', false)
            ->assertJsonPath('access.reason', 'premium_subscription_not_active');

        $draftId = $this->createPublishedTest('Draft test', isPremium: false, status: 'draft');
        $this->withToken($token)->getJson('/api/tests/'.$draftId.'/access')->assertNotFound();
        $this->withToken($token)->getJson('/api/tests/999999/access')->assertNotFound();
        $this->flushHeaders()->getJson('/api/tests/'.$premiumTest.'/access')->assertUnauthorized();
    }

    public function test_entitlements_are_package_scoped_to_the_purchasing_user(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $includedTest = $this->createPublishedTest('Included premium test', isPremium: true);
        $excludedTest = $this->createPublishedTest('Other premium test', isPremium: true);

        $packageId = $this->insertPackage('single-test-package', 2500, 14, [
            'entitlement_type' => 'test',
            'test_id' => $includedTest,
        ]);

        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId,
        ])->assertCreated()->json('order.id');

        $this->fundWallet($user, $token, 10000);
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'scoped-entitlement-key',
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/tests/'.$includedTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.accessible', true)
            ->assertJsonPath('access.entitlements.all_premium_tests', false)
            ->assertJsonPath('access.entitlements.test_ids.0', $includedTest);

        $this->withToken($token)->getJson('/api/tests/'.$excludedTest.'/access')
            ->assertOk()
            ->assertJsonPath('access.accessible', false);
    }

    private function packageId(string $code): int
    {
        return (int) DB::table('subscription_packages')->where('code', $code)->value('id');
    }

    private function insertPackage(string $code, int $priceMinor, int $durationDays, array $entitlement): int
    {
        $packageId = DB::table('subscription_packages')->insertGetId([
            'name' => ucfirst(str_replace('-', ' ', $code)),
            'code' => $code,
            'description' => 'Test package',
            'price_minor' => $priceMinor,
            'currency' => 'BDT',
            'duration_days' => $durationDays,
            'is_active' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('package_entitlements')->insert([
            'package_id' => $packageId,
            'entitlement_type' => $entitlement['entitlement_type'],
            'test_id' => $entitlement['test_id'],
            'created_at' => now(),
        ]);

        return $packageId;
    }

    private function createPublishedTest(string $title, bool $isPremium = true, string $status = 'published'): int
    {
        $subject = Subject::query()->firstOrCreate(
            ['code' => 'SUB'.substr(md5($title), 0, 6)],
            ['name' => 'Subject for '.$title],
        );

        return ModelTest::query()->create([
            'title' => $title,
            'code' => 'TST'.Str::upper(Str::random(10)),
            'subject_id' => $subject->id,
            'duration_minutes' => 10,
            'question_count' => 1,
            'total_marks' => 1,
            'passing_score' => 1,
            'is_premium' => $isPremium,
            'status' => $status,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ])->id;
    }

    private function createCoupon(string $adminToken, array $overrides = []): int
    {
        $payload = array_merge([
            'code' => 'C'.Str::upper(Str::random(8)),
            'discount_type' => 'percentage',
            'discount_percent' => 10,
        ], $overrides);

        return $this->withToken($adminToken)
            ->postJson('/api/admin/coupons', $payload)
            ->assertCreated()
            ->json('coupon.id');
    }

    /**
     * Full funding path: top-up order -> pending payment -> signed provider callback.
     */
    private function fundWallet(User $user, string $token, int $amountMinor): void
    {
        $orderId = $this->withToken($token)->postJson('/api/wallet/topup', [
            'amount_minor' => $amountMinor,
        ])->assertCreated()->json('order.id');

        $paymentReference = $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'bkash',
        ])->assertCreated()->json('payment.reference');

        $payload = [
            'reference' => $paymentReference,
            'status' => 'paid',
            'amount_minor' => $amountMinor,
            'currency' => 'BDT',
            'provider_transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
        ];

        $signature = hash_hmac('sha256', implode('|', [
            'bkash',
            $payload['reference'],
            (string) $payload['amount_minor'],
            'BDT',
            'paid',
            $payload['provider_transaction_id'],
        ]), config('services.payment.webhook_secret'));

        $this->withHeaders(['X-Payment-Signature' => $signature])
            ->postJson('/api/payments/callback/bkash', $payload)
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid');

        $this->assertNotNull(Payment::query()->where('reference', $paymentReference)->firstOrFail()->paid_at);
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'subscription-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Subscription test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
