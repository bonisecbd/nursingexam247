<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payment.webhook_secret', 'wallet-test-webhook-secret');
    }

    public function test_wallet_endpoints_require_authentication(): void
    {
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions/1')->assertUnauthorized();
        $this->postJson('/api/wallet/topup', ['amount_minor' => 5000])->assertUnauthorized();
        $this->postJson('/api/wallet/spend', [
            'order_id' => 1,
            'idempotency_key' => 'unauthenticated-key',
        ])->assertUnauthorized();
    }

    public function test_wallet_balance_is_derived_from_the_ledger_after_a_verified_payment(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.balance_minor', 0)
            ->assertJsonPath('wallet.currency', 'BDT');

        $this->fundWallet($user, $token, 50000);

        $this->withToken($token)->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.balance_minor', 50000);

        $transactions = $this->withToken($token)->getJson('/api/wallet/transactions');
        $transactions->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'credit')
            ->assertJsonPath('data.0.amount_minor', 50000)
            ->assertJsonPath('data.0.balance_after_minor', 50000)
            ->assertJsonPath('data.0.currency', 'BDT')
            ->assertJsonPath('data.0.source_type', 'order');

        // The cached projection must equal the sum of the append-only ledger.
        $ledgerSum = (int) DB::table('wallet_transactions')->sum('amount_minor');
        $cachedBalance = (int) DB::table('wallets')->where('user_id', $user->id)->value('balance_minor');
        $this->assertSame(50000, $ledgerSum);
        $this->assertSame($ledgerSum, $cachedBalance);
    }

    public function test_wallet_transactions_are_paginated_and_owner_scoped(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [$other, $otherToken] = $this->createUserWithToken();

        $this->fundWallet($user, $token, 1000);
        $this->fundWallet($user, $token, 2000);
        $this->fundWallet($user, $token, 3000);

        $page = $this->withToken($token)->getJson('/api/wallet/transactions?per_page=2');
        $page->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2);

        $this->withToken($token)->getJson('/api/wallet/transactions?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $transactionId = $page->json('data.0.id');
        $this->withToken($token)->getJson('/api/wallet/transactions/'.$transactionId)
            ->assertOk()
            ->assertJsonPath('transaction.id', $transactionId);

        // Another user cannot read, or even confirm the existence of, that entry.
        $this->withToken($otherToken)->getJson('/api/wallet/transactions/'.$transactionId)
            ->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('wallet.balance_minor', 0);
        $this->assertNotSame($user->id, $other->id);
    }

    public function test_wallet_spend_pays_a_subscription_order_exactly_once(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $package = $this->monthlyPackageId();

        $this->fundWallet($user, $token, 50000);

        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $package,
        ])->assertCreated()->json('order.id');

        $spend = $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'spend-key-'.$orderId,
        ]);

        $spend->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('balance_minor', 40000);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid']);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
            'order_id' => $orderId,
        ]);

        // Retrying the identical request must not debit or activate twice.
        $retry = $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'spend-key-'.$orderId,
        ]);

        $retry->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('balance_minor', 40000)
            ->assertJsonPath('transaction_id', $spend->json('transaction_id'));

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('wallet_transactions', 2); // one credit, one debit
        $this->assertSame(40000, (int) DB::table('wallet_transactions')->sum('amount_minor'));

        // A different key against the already paid order is a conflict, not a second charge.
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'spend-key-other-'.$orderId,
        ])->assertStatus(409);

        $this->assertSame(40000, (int) DB::table('wallet_transactions')->sum('amount_minor'));
    }

    public function test_wallet_spend_rejects_insufficient_balance_expired_and_foreign_orders(): void
    {
        [$user, $token] = $this->createUserWithToken();
        [, $otherToken] = $this->createUserWithToken();

        $this->fundWallet($user, $token, 5000);

        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $this->monthlyPackageId(), // costs 10000 poisha
        ])->assertCreated()->json('order.id');

        // Not enough funds: nothing is debited and the order stays pending.
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'insufficient-funds-key',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('balance');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 1); // only the credit
        $this->assertDatabaseCount('subscriptions', 0);
        $this->withToken($token)->getJson('/api/wallet')
            ->assertJsonPath('wallet.balance_minor', 5000);

        // Another student cannot spend against someone else's order.
        $this->withToken($otherToken)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'foreign-order-key',
        ])->assertNotFound();

        // An expired order can no longer be paid from the wallet.
        DB::table('orders')->where('id', $orderId)->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'expired-order-key',
        ])->assertStatus(409);

        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
        ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'bad key with spaces',
        ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_wallet_topup_validation_and_unpayable_orders(): void
    {
        [$user, $token] = $this->createUserWithToken();

        $this->withToken($token)->postJson('/api/wallet/topup', ['amount_minor' => 50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');
        $this->withToken($token)->postJson('/api/wallet/topup', ['amount_minor' => 'lots'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');
        $this->withToken($token)->postJson('/api/wallet/topup', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_minor');

        // A top-up order cannot itself be paid from the wallet.
        $topupOrderId = $this->withToken($token)->postJson('/api/wallet/topup', [
            'amount_minor' => 5000,
        ])->assertCreated()->json('order.id');

        $this->fundWallet($user, $token, 20000);
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $topupOrderId,
            'idempotency_key' => 'topup-spend-key',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');

        $this->assertDatabaseHas('orders', ['id' => $topupOrderId, 'status' => 'pending']);
        $this->withToken($token)->getJson('/api/wallet')
            ->assertJsonPath('wallet.balance_minor', 20000);
    }

    public function test_wallet_spend_amount_comes_from_the_order_not_the_client(): void
    {
        [$user, $token] = $this->createUserWithToken();
        $this->fundWallet($user, $token, 50000);

        $orderId = $this->withToken($token)->postJson('/api/subscriptions/checkout', [
            'package_id' => $this->monthlyPackageId(),
        ])->assertCreated()->json('order.id');

        // The client tries to dictate a one-poisha price; it is ignored.
        $this->withToken($token)->postJson('/api/wallet/spend', [
            'order_id' => $orderId,
            'idempotency_key' => 'client-price-attempt-key',
            'amount_minor' => 1,
            'total_minor' => 1,
            'balance_minor' => 999999,
        ])
            ->assertCreated()
            ->assertJsonPath('order.total_minor', 10000)
            ->assertJsonPath('balance_minor', 40000);

        $this->assertSame(40000, (int) DB::table('wallet_transactions')->sum('amount_minor'));
        $this->assertSame(-10000, (int) DB::table('wallet_transactions')->where('type', 'debit')->value('amount_minor'));
    }

    private function monthlyPackageId(): int
    {
        return (int) DB::table('subscription_packages')->where('code', 'premium-monthly')->value('id');
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
            'provider' => 'sslcommerz',
        ])
            ->assertCreated()
            ->json('payment.reference');

        $payload = [
            'reference' => $paymentReference,
            'status' => 'paid',
            'amount_minor' => $amountMinor,
            'currency' => 'BDT',
            'provider_transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
        ];

        $this->postSignedCallback('sslcommerz', $payload)
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('idempotent', false);

        $this->assertNotNull(Payment::query()->where('reference', $paymentReference)->firstOrFail()->paid_at);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid']);
    }

    private function postSignedCallback(string $provider, array $payload, ?string $signature = null)
    {
        $signature ??= hash_hmac(
            'sha256',
            implode('|', [
                $provider,
                $payload['reference'],
                (string) $payload['amount_minor'],
                strtoupper($payload['currency']),
                $payload['status'],
                $payload['provider_transaction_id'],
            ]),
            config('services.payment.webhook_secret'),
        );

        return $this->withHeaders(['X-Payment-Signature' => $signature])
            ->postJson('/api/payments/callback/'.$provider, $payload);
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'wallet-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Wallet test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
