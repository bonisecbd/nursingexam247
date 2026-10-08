<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Provider adapter boundary for bKash/Nagad/SSLCommerz style gateways.
 *
 * IMPORTANT: no real gateway credentials are configured or verified in this
 * environment. The callback endpoint implements the documented signature
 * scheme (HMAC-SHA256 over a canonical string with PAYMENT_WEBHOOK_SECRET) as
 * a stub of the provider protocol. External provider integration is NOT
 * verified end to end and must not be described as working. Browser redirects
 * never mark a payment paid; only the signed server-to-server callback does.
 */
class PaymentService
{
    public const PROVIDERS = ['bkash', 'nagad', 'sslcommerz'];

    public const SIGNATURE_HEADER = 'X-Payment-Signature';

    public function __construct(private readonly OrderService $orders) {}

    /**
     * Create (or return the already pending) provider transaction for an order.
     *
     * @throws ValidationException
     */
    public function checkout(User $user, int $orderId, string $provider): array
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw ValidationException::withMessages([
                'provider' => ['The selected payment provider is not supported.'],
            ]);
        }

        return DB::transaction(function () use ($user, $orderId, $provider): array {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === 'paid') {
                abort(409, 'This order has already been paid.');
            }
            if ($order->status !== 'pending') {
                abort(409, 'This order is not awaiting payment.');
            }
            if ($order->expires_at !== null && ! $order->expires_at->isFuture()) {
                abort(409, 'This order has expired. Start a new checkout to continue.');
            }
            if ($order->total_minor === 0) {
                abort(409, 'This order does not require a payment.');
            }

            $existing = Payment::query()
                ->where('order_id', $order->id)
                ->where('provider', $provider)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return [
                    'message' => 'The pending payment for this order and provider was returned unchanged.',
                    'duplicate' => true,
                    'payment' => $this->paymentData($existing),
                ];
            }

            $payment = Payment::query()->create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'provider' => $provider,
                'reference' => $this->newPaymentReference(),
                'status' => 'pending',
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'provider_transaction_id' => null,
                'provider_action' => $this->providerAction(),
            ]);

            $this->recordEvent($payment->id, 'checkout_created', [
                'provider' => $provider,
                'order_reference' => $order->reference,
            ], 'pending');

            return [
                'message' => 'Pending payment created. It becomes paid only after a verified provider callback.',
                'duplicate' => false,
                'payment' => $this->paymentData($payment),
            ];
        });
    }

    /**
     * Ownership-scoped read; another user's payment is a 404.
     */
    public function show(User $user, int $paymentId): Payment
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->findOrFail($paymentId);
    }

    /**
     * Server-side verification poll. With no gateway credentials configured it
     * records the request and reports the truth: unverified and still pending.
     */
    public function verify(User $user, int $paymentId): array
    {
        $payment = $this->show($user, $paymentId);

        if ($payment->status !== 'pending') {
            return [
                'payment' => $this->paymentData($payment),
                'provider_verified' => $payment->verified_at !== null,
                'message' => 'The payment is already in a final state.',
            ];
        }

        $this->recordEvent($payment->id, 'verification_requested', [
            'provider' => $payment->provider,
        ], $payment->status);

        return [
            'payment' => $this->paymentData($payment),
            'provider_verified' => false,
            'message' => 'Provider verification is unavailable because gateway credentials are not configured. The payment stays pending.',
        ];
    }

    /**
     * Provider-to-server callback. Signature-verified, amount-verified,
     * idempotent, and transactional: entitlement or wallet credit commits only
     * with the paid status change.
     */
    public function handleCallback(string $provider, array $payload, ?string $signature): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return response()->json(['message' => 'Unsupported payment provider.'], 422);
        }

        $secret = $this->webhookSecret();
        if ($secret === null) {
            return response()->json([
                'message' => 'Payment callback verification is not configured on this server.',
            ], 503);
        }

        $validator = Validator::make($payload, [
            'reference' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'status' => ['required', 'string', Rule::in(['paid', 'failed'])],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'currency' => ['required', 'string', 'size:3', 'in:BDT'],
            'provider_transaction_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The payment callback payload is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $expected = hash_hmac('sha256', static::canonicalString($provider, $data), $secret);
        if (! is_string($signature) || $signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid payment callback signature.'], 403);
        }

        $payment = Payment::query()
            ->where('reference', $data['reference'])
            ->where('provider', $provider)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        if ((int) $data['amount_minor'] !== (int) $payment->amount_minor
            || strtoupper($data['currency']) !== strtoupper($payment->currency)) {
            $this->recordEvent($payment->id, 'callback_amount_mismatch', [
                'expected_amount_minor' => (int) $payment->amount_minor,
                'received_amount_minor' => (int) $data['amount_minor'],
            ], $payment->status);

            return response()->json([
                'message' => 'The callback amount or currency does not match this payment.',
            ], 422);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'Payment already processed.',
                'idempotent' => true,
                'payment' => $this->paymentData($payment),
            ]);
        }

        try {
            DB::transaction(function () use ($payment, $data): void {
                $fresh = Payment::query()->where('id', $payment->id)->lockForUpdate()->firstOrFail();

                if ($fresh->status === 'paid') {
                    return;
                }
                if ($fresh->status !== 'pending') {
                    abort(409, 'A '.$fresh->status.' payment cannot be moved to '.$data['status'].'.');
                }

                $order = Order::query()->where('id', $fresh->order_id)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'pending') {
                    // A competing provider confirmation already settled this
                    // order; never apply the goods (or wallet credit) twice.
                    abort(409, 'The order for this payment has already been settled.');
                }

                $fresh->provider_transaction_id = $data['provider_transaction_id'];
                $fresh->metadata = [
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'amount_minor' => (int) $data['amount_minor'],
                    'currency' => $data['currency'],
                    'verified_by' => 'callback_signature',
                ];

                if ($data['status'] === 'failed') {
                    $fresh->status = 'failed';
                    $fresh->failed_at = now();
                    $fresh->save();
                    $this->recordEvent($fresh->id, 'callback_failed', [
                        'provider_transaction_id' => $data['provider_transaction_id'],
                    ], 'failed');

                    return;
                }

                $fresh->status = 'paid';
                $fresh->paid_at = now();
                $fresh->verified_at = now();
                $fresh->save();

                // Same transaction as the paid status change: a failure here
                // rolls the payment back to pending.
                $this->orders->fulfillPaidOrder($order, 'payment');

                $this->recordEvent($fresh->id, 'callback_paid', [
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'order_reference' => $order->reference,
                ], 'paid');
            });
        } catch (HttpExceptionInterface $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $conflicting = Payment::query()
                ->where('provider_transaction_id', $data['provider_transaction_id'])
                ->where('id', '!=', $payment->id)
                ->exists();

            $this->recordEvent($payment->id, 'callback_rejected', [
                'reason' => $conflicting ? 'provider_transaction_id_conflict' : 'fulfillment_failed',
            ], $payment->status);

            if ($conflicting) {
                return response()->json([
                    'message' => 'This provider transaction reference has already been processed.',
                ], 409);
            }

            return response()->json([
                'message' => 'The payment confirmation could not be applied. No balance or entitlement change was made.',
            ], 502);
        }

        $payment->refresh();

        return response()->json([
            'message' => 'Payment processed.',
            'idempotent' => false,
            'payment' => $this->paymentData($payment),
        ]);
    }

    public function paymentData(Payment $payment): array
    {
        $payment->loadMissing('order:id,reference,purpose,status,total_minor,currency');

        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'provider_action' => $payment->provider_action,
            'provider_verified' => $payment->verified_at !== null,
            'order' => $payment->order ? [
                'id' => $payment->order->id,
                'reference' => $payment->order->reference,
                'purpose' => $payment->order->purpose,
                'status' => $payment->order->status,
                'total_minor' => $payment->order->total_minor,
                'currency' => $payment->order->currency,
            ] : null,
            'paid_at' => $payment->paid_at?->toISOString(),
            'failed_at' => $payment->failed_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    /**
     * The exact string providers sign. Kept public so the scheme can be
     * documented and reproduced by a real gateway adapter.
     */
    public static function canonicalString(string $provider, array $data): string
    {
        return implode('|', [
            $provider,
            (string) ($data['reference'] ?? ''),
            (string) ($data['amount_minor'] ?? ''),
            strtoupper((string) ($data['currency'] ?? '')),
            (string) ($data['status'] ?? ''),
            (string) ($data['provider_transaction_id'] ?? ''),
        ]);
    }

    public function webhookSecret(): ?string
    {
        $secret = config('services.payment.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            $secret = env('PAYMENT_WEBHOOK_SECRET');
        }

        return is_string($secret) && trim($secret) !== '' ? trim($secret) : null;
    }

    public function recordEvent(int $paymentId, string $event, ?array $payload = null, ?string $status = null): void
    {
        PaymentEvent::query()->create([
            'payment_id' => $paymentId,
            'event' => $event,
            'status' => $status,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Honest stub action data: never a fake redirect that implies success.
     *
     * @return array<string, mixed>
     */
    private function providerAction(): array
    {
        return [
            'type' => 'redirect',
            'url' => null,
            'state' => 'requires_provider_configuration',
            'instructions' => 'Gateway credentials are not configured in this environment, so no redirect can be issued yet. The payment stays pending until a signed server-to-server callback confirms it.',
        ];
    }

    private function newPaymentReference(): string
    {
        do {
            $reference = 'PAY-'.Str::upper(Str::random(16));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
