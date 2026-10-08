<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Auditable wallet over an append-only ledger.
 *
 * All amounts are integer poisha (minor units). The balance shown to a client
 * is always derived from the ledger; the wallets.balance_minor column is only
 * a cached projection written in the same transaction as its ledger entry.
 */
class WalletService
{
    public const CURRENCY = 'BDT';

    public function summary(User $user): array
    {
        $wallet = $this->walletRow($user->id);
        $balance = $this->balanceMinor($user->id, $wallet);

        return [
            'wallet' => [
                'currency' => $wallet->currency,
                'balance_minor' => $balance,
            ],
        ];
    }

    public function transactions(User $user, int $perPage): array
    {
        $transactions = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => $transactions->getCollection()->map(
                fn (WalletTransaction $transaction): array => $this->transactionData($transaction)
            )->all(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ];
    }

    /**
     * Ownership-scoped detail; another user's transaction is a 404, never a 403 probe.
     */
    public function transaction(User $user, int $transactionId): array
    {
        $transaction = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->findOrFail($transactionId);

        return ['transaction' => $this->transactionData($transaction)];
    }

    /**
     * Append a credit entry. Idempotent on the idempotency key.
     *
     * @return array{duplicate: bool, transaction_id: int, balance_minor: int}
     */
    public function credit(
        int $userId,
        int $amountMinor,
        string $sourceType,
        ?int $sourceId,
        string $idempotencyKey,
        string $description,
    ): array {
        return $this->appendEntry($userId, $amountMinor, 'credit', $sourceType, $sourceId, $idempotencyKey, $description);
    }

    /**
     * Append a debit entry. Never allowed to drive the balance negative.
     *
     * @return array{duplicate: bool, transaction_id: int, balance_minor: int}
     *
     * @throws ValidationException
     */
    public function debit(
        int $userId,
        int $amountMinor,
        string $sourceType,
        ?int $sourceId,
        string $idempotencyKey,
        string $description,
    ): array {
        return $this->appendEntry($userId, $amountMinor, 'debit', $sourceType, $sourceId, $idempotencyKey, $description);
    }

    /**
     * Pay a wallet-payable order from the caller's balance.
     *
     * Debit and order fulfillment commit or roll back together.
     *
     * @throws ValidationException
     */
    public function spend(User $user, int $orderId, string $idempotencyKey, OrderService $orders): array
    {
        try {
            return DB::transaction(function () use ($user, $orderId, $idempotencyKey, $orders): array {
                $order = Order::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->findOrFail($orderId);

                // Replay detection runs before any state check so a retried
                // request always returns the original result.
                $existing = DB::table('wallet_transactions')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    if ((int) $existing->user_id !== (int) $user->id
                        || $existing->source_type !== 'order'
                        || (int) $existing->source_id !== (int) $order->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['This idempotency key was already used for a different operation.'],
                        ]);
                    }

                    return $this->spendResult($user, $order, (int) $existing->id, $orders, duplicate: true);
                }

                if ($order->status !== 'pending') {
                    abort(409, 'This order is not awaiting payment.');
                }
                if ($order->expires_at !== null && ! $order->expires_at->isFuture()) {
                    abort(409, 'This order has expired. Create a new checkout to continue.');
                }
                if ($order->purpose !== 'subscription') {
                    throw ValidationException::withMessages([
                        'order_id' => ['This order cannot be paid from the wallet.'],
                    ]);
                }

                $transactionId = null;
                if ($order->total_minor > 0) {
                    $entry = $this->appendEntry(
                        $user->id,
                        $order->total_minor,
                        'debit',
                        'order',
                        $order->id,
                        $idempotencyKey,
                        'Payment for order '.$order->reference,
                    );
                    $transactionId = $entry['transaction_id'];
                }

                $orders->fulfillPaidOrder($order, 'wallet');

                return $this->spendResult($user, $order->refresh(), $transactionId, $orders, duplicate: false);
            });
        } catch (QueryException $exception) {
            // A competing request inserted the same idempotency key first: the
            // transaction rolled back completely, so report the stored result.
            $existing = DB::table('wallet_transactions')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && (int) $existing->user_id === (int) $user->id) {
                $order = Order::query()->where('user_id', $user->id)->find($orderId);

                return $order
                    ? $this->spendResult($user, $order, (int) $existing->id, $orders, duplicate: true)
                    : [
                        'duplicate' => true,
                        'transaction_id' => (int) $existing->id,
                        'balance_minor' => $this->balanceMinor($user->id),
                        'order' => null,
                    ];
            }

            throw $exception;
        }
    }

    public function balanceMinor(int $userId, ?object $wallet = null): int
    {
        $wallet ??= $this->walletRow($userId);
        $balance = (int) DB::table('wallet_transactions')
            ->where('wallet_id', $wallet->id)
            ->sum('amount_minor');

        if ((int) $wallet->balance_minor !== $balance) {
            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance_minor' => $balance,
                'balance_updated_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $balance;
    }

    public function walletRow(int $userId): object
    {
        $wallet = DB::table('wallets')->where('user_id', $userId)->first();
        if ($wallet) {
            return $wallet;
        }

        DB::table('wallets')->insertOrIgnore([
            'user_id' => $userId,
            'currency' => self::CURRENCY,
            'balance_minor' => 0,
            'balance_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('wallets')->where('user_id', $userId)->firstOrFail();
    }

    /**
     * @return array{duplicate: bool, transaction_id: int, balance_minor: int}
     */
    private function appendEntry(
        int $userId,
        int $amountMinor,
        string $type,
        string $sourceType,
        ?int $sourceId,
        string $idempotencyKey,
        string $description,
    ): array {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Wallet amounts must be positive integer minor units.');
        }

        $signedAmount = $type === 'credit' ? $amountMinor : -$amountMinor;

        try {
            return DB::transaction(function () use ($userId, $signedAmount, $type, $sourceType, $sourceId, $idempotencyKey, $description): array {
                $existing = DB::table('wallet_transactions')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    if ((int) $existing->user_id !== $userId
                        || $existing->source_type !== $sourceType
                        || (int) $existing->source_id !== (int) $sourceId
                        || (int) $existing->amount_minor !== $signedAmount) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['This idempotency key was already used for a different operation.'],
                        ]);
                    }

                    return [
                        'duplicate' => true,
                        'transaction_id' => (int) $existing->id,
                        'balance_minor' => $this->balanceMinor($userId),
                    ];
                }

                $wallet = DB::table('wallets')->where('user_id', $userId)->lockForUpdate()->first() ?? $this->lockedWallet($userId);
                $balance = (int) DB::table('wallet_transactions')
                    ->where('wallet_id', $wallet->id)
                    ->sum('amount_minor');
                $newBalance = $balance + $signedAmount;

                if ($newBalance < 0) {
                    throw ValidationException::withMessages([
                        'balance' => ['Your wallet balance is insufficient for this payment.'],
                    ]);
                }

                $transactionId = DB::table('wallet_transactions')->insertGetId([
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'type' => $type,
                    'amount_minor' => $signedAmount,
                    'currency' => $wallet->currency,
                    'balance_after_minor' => $newBalance,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'status' => 'completed',
                    'idempotency_key' => $idempotencyKey,
                    'description' => $description,
                    'created_at' => now(),
                ]);

                DB::table('wallets')->where('id', $wallet->id)->update([
                    'balance_minor' => $newBalance,
                    'balance_updated_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'duplicate' => false,
                    'transaction_id' => $transactionId,
                    'balance_minor' => $newBalance,
                ];
            });
        } catch (QueryException $exception) {
            $existing = DB::table('wallet_transactions')->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && (int) $existing->user_id === $userId) {
                return [
                    'duplicate' => true,
                    'transaction_id' => (int) $existing->id,
                    'balance_minor' => $this->balanceMinor($userId),
                ];
            }

            throw $exception;
        }
    }

    private function lockedWallet(int $userId): object
    {
        DB::table('wallets')->insertOrIgnore([
            'user_id' => $userId,
            'currency' => self::CURRENCY,
            'balance_minor' => 0,
            'balance_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('wallets')->where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    private function spendResult(User $user, Order $order, ?int $transactionId, OrderService $orders, bool $duplicate): array
    {
        return [
            'duplicate' => $duplicate,
            'transaction_id' => $transactionId,
            'balance_minor' => $this->balanceMinor($user->id),
            'order' => $orders->orderData($order),
        ];
    }

    private function transactionData(WalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'amount_minor' => $transaction->amount_minor,
            'balance_after_minor' => $transaction->balance_after_minor,
            'currency' => $transaction->currency,
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'status' => $transaction->status,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }
}
