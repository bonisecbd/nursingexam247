<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'payment-test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.webhook_secret', self::WEBHOOK_SECRET);
    }

    public function test_payment_endpoints_require_authentication(): void
    {
        $this->postJson('/api/payments/checkout', ['order_id' => 1, 'provider' => 'bkash'])
            ->assertUnauthorized();
        $this->getJson('/api/payments/1')->assertUnauthorized();
        $this->postJson('/api/payments/1/verify')->assertUnauthorized();
    }

    public function test_payment_checkout_creates_a_pending_provider_transaction_and_is_idempotent(): void
    {
        [, $token] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);

        $checkout = $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'bkash',
        ]);

        $checkout->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.provider', 'bkash')
            ->assertJsonPath('payment.amount_minor', 10000)
            ->assertJsonPath('payment.currency', 'BDT')
            ->assertJsonPath('payment.provider_verified', false)
            ->assertJsonPath('payment.provider_action.state', 'requires_provider_configuration')
            ->assertJsonPath('payment.provider_action.url', null);

        $reference = $checkout->json('payment.reference');
        $this->assertStringStartsWith('PAY-', $reference);
        $this->assertSame(1, DB::table('payments')->count());

        $retry = $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'bkash',
        ]);
        $retry->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('payment.reference', $reference);
        $this->assertSame(1, DB::table('payments')->count());

        // Switching gateway creates a separate pending transaction.
        $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'nagad',
        ])->assertCreated();
        $this->assertSame(2, DB::table('payments')->count());

        $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'paypal',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');

        $this->withToken($token)->postJson('/api/payments/checkout', ['order_id' => $orderId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider');
    }

    public function test_payment_checkout_and_detail_are_owner_scoped(): void
    {
        [$owner, $token] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);

        $paymentId = $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'sslcommerz',
        ])->assertCreated()->json('payment.id');

        $this->withToken($token)->getJson('/api/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('payment.id', $paymentId)
            ->assertJsonPath('payment.order.id', $orderId)
            ->assertJsonMissingPath('payment.metadata')
            ->assertJsonMissingPath('payment.secret')
            ->assertJsonMissingPath('payment.provider_secret');

        // Another student cannot read, or even confirm the payment exists.
        $this->withToken($otherToken)->getJson('/api/payments/'.$paymentId)->assertNotFound();
        $this->withToken($otherToken)->postJson('/api/payments/'.$paymentId.'/verify')->assertNotFound();

        // And cannot open a payment against someone else's order.
        $this->withToken($otherToken)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => 'bkash',
        ])->assertNotFound();

        $this->assertNotNull($owner->id);
        $this->assertSame(1, DB::table('payments')->count());
    }

    public function test_signed_callback_marks_the_payment_paid_and_settles_the_order_atomically(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'sslcommerz');

        $payload = $this->payloadFor($payment);

        $response = $this->postCallback('sslcommerz', $payload);
        $response->assertOk()
            ->assertJsonPath('idempotent', false)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.provider_verified', true)
            ->assertJsonPath('payment.order.status', 'paid');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame($payload['provider_transaction_id'], $payment->provider_transaction_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->verified_at);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid']);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'status' => 'active',
            'source' => 'payment',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'event' => 'callback_paid',
            'status' => 'paid',
        ]);

        $subscription = DB::table('subscriptions')->where('order_id', $orderId)->first();
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            strtotime($subscription->ends_at),
            300,
        );

        // Duplicate callbacks are processed once and never double-activate.
        $this->postCallback('sslcommerz', $payload)
            ->assertOk()
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('payment.status', 'paid');

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(1, DB::table('payment_events')->where('event', 'callback_paid')->count());
    }

    public function test_callback_rejects_bad_signatures_amount_mismatches_and_unknown_references(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'sslcommerz');

        // No signature at all.
        $this->postJson('/api/payments/callback/sslcommerz', $this->payloadFor($payment))
            ->assertForbidden();

        // Signature computed over a different payload.
        $tampered = $this->payloadFor($payment, ['amount_minor' => 1]);
        $this->postCallback('sslcommerz', $this->payloadFor($payment), $this->sign('sslcommerz', $tampered))
            ->assertForbidden();

        // Correct signature, wrong amount: rejected without any state change.
        $wrongAmount = $this->payloadFor($payment, ['amount_minor' => 9999]);
        $this->postCallback('sslcommerz', $wrongAmount)->assertStatus(422);

        // Correct signature, wrong currency.
        $wrongCurrency = $this->payloadFor($payment, ['currency' => 'USD']);
        $this->postCallback('sslcommerz', $wrongCurrency)->assertStatus(422);

        // Unknown payment reference.
        $this->postCallback('sslcommerz', $this->payloadFor($payment, [
            'reference' => 'PAY-NOT-AREALPAYMENT',
        ]))->assertNotFound();

        // Unknown provider enum.
        $this->postJson('/api/payments/callback/paypal', $this->payloadFor($payment))
            ->assertUnprocessable();

        // Malformed payload.
        $this->postCallback('sslcommerz', ['reference' => $payment->reference])
            ->assertStatus(422);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->provider_transaction_id);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertSame(0, DB::table('payment_events')->where('event', 'callback_paid')->count());
        $this->assertNotNull($user->id);
    }

    public function test_callback_without_a_configured_webhook_secret_is_refused(): void
    {
        [, $token] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'bkash');

        config()->set('services.payment.webhook_secret', null);

        $this->postCallback('bkash', $this->payloadFor($payment))
            ->assertStatus(503);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_failed_payment_callback_never_grants_an_entitlement(): void
    {
        [, $token] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'nagad');

        $failedPayload = $this->payloadFor($payment, ['status' => 'failed']);
        $this->postCallback('nagad', $failedPayload)
            ->assertOk()
            ->assertJsonPath('payment.status', 'failed');

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'event' => 'callback_failed',
        ]);

        // pending -> failed is terminal: a later "paid" callback is rejected.
        $this->postCallback('nagad', $this->payloadFor($payment, [
            'provider_transaction_id' => 'TXN-LATE-PAID',
        ]))->assertStatus(409);

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
    }

    public function test_fulfillment_failure_rolls_back_the_paid_status(): void
    {
        [, $token] = $this->createUserWithToken();
        $packageId = $this->packageId('premium-monthly');
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'sslcommerz');

        // The package is withdrawn while the payment is in flight, so the
        // order can no longer be fulfilled.
        DB::table('subscription_packages')->where('id', $packageId)->delete();

        $this->postCallback('sslcommerz', $this->payloadFor($payment))
            ->assertStatus(502);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->provider_transaction_id);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'event' => 'callback_rejected',
        ]);
        $this->assertSame(0, DB::table('payment_events')->where('event', 'callback_paid')->count());
    }

    public function test_duplicate_provider_transaction_reference_is_applied_once(): void
    {
        [, $token] = $this->createUserWithToken();
        $firstOrderId = $this->createOrder($token);
        $secondOrderId = $this->createOrder($token);
        $firstPayment = $this->createPayment($token, $firstOrderId, 'sslcommerz');
        $secondPayment = $this->createPayment($token, $secondOrderId, 'sslcommerz');

        $sharedTransactionId = 'TXN-SHARED-'.Str::upper(Str::random(8));

        $this->postCallback('sslcommerz', $this->payloadFor($firstPayment, [
            'provider_transaction_id' => $sharedTransactionId,
        ]))->assertOk()->assertJsonPath('payment.status', 'paid');

        $this->postCallback('sslcommerz', $this->payloadFor($secondPayment, [
            'provider_transaction_id' => $sharedTransactionId,
        ]))->assertStatus(409);

        $firstPayment->refresh();
        $secondPayment->refresh();
        $this->assertSame('paid', $firstPayment->status);
        $this->assertSame('pending', $secondPayment->status);
        $this->assertDatabaseHas('orders', ['id' => $firstOrderId, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $secondOrderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $secondPayment->id,
            'event' => 'callback_rejected',
            'status' => 'pending',
        ]);
    }

    public function test_verify_endpoint_never_marks_a_pending_payment_paid(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();
        $orderId = $this->createOrder($token);
        $payment = $this->createPayment($token, $orderId, 'bkash');

        $verify = $this->withToken($token)->postJson('/api/payments/'.$payment->id.'/verify');
        $verify->assertOk()
            ->assertJsonPath('provider_verified', false)
            ->assertJsonPath('payment.status', 'pending');

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->verified_at);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'event' => 'verification_requested',
        ]);

        // After a verified callback the poll reports the final state.
        $this->postCallback('bkash', $this->payloadFor($payment))
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid');

        $this->withToken($token)->postJson('/api/payments/'.$payment->id.'/verify')
            ->assertOk()
            ->assertJsonPath('provider_verified', true)
            ->assertJsonPath('payment.status', 'paid');

        $this->withToken($otherToken)->postJson('/api/payments/'.$payment->id.'/verify')
            ->assertNotFound();

        $this->assertNotNull($user->id);
    }

    private function packageId(string $code): int
    {
        return (int) DB::table('subscription_packages')->where('code', $code)->value('id');
    }

    private function createOrder(string $token, ?int $packageId = null): int
    {
        return $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $packageId ?? $this->packageId('premium-monthly'),
        ])->assertCreated()->json('order.id');
    }

    private function createPayment(string $token, int $orderId, string $provider): Payment
    {
        $paymentId = $this->withToken($token)->postJson('/api/payments/checkout', [
            'order_id' => $orderId,
            'provider' => $provider,
        ])->assertCreated()->json('payment.id');

        return Payment::query()->findOrFail($paymentId);
    }

    private function payloadFor(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'reference' => $payment->reference,
            'status' => 'paid',
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'provider_transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
        ], $overrides);
    }

    private function sign(string $provider, array $payload): string
    {
        return hash_hmac('sha256', implode('|', [
            $provider,
            (string) ($payload['reference'] ?? ''),
            (string) ($payload['amount_minor'] ?? ''),
            strtoupper((string) ($payload['currency'] ?? '')),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['provider_transaction_id'] ?? ''),
        ]), self::WEBHOOK_SECRET);
    }

    private function postCallback(string $provider, array $payload, ?string $signature = null)
    {
        return $this->withHeaders(['X-Payment-Signature' => $signature ?? $this->sign($provider, $payload)])
            ->postJson('/api/payments/callback/'.$provider, $payload);
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'payment-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Payment test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
