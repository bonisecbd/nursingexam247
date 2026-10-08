<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Create (or return the existing) pending provider transaction for an order.
     */
    public function checkout(Request $request, PaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'provider' => ['required', 'string', Rule::in(PaymentService::PROVIDERS)],
        ]);

        $result = $payments->checkout(
            $request->user(),
            $validated['order_id'],
            $validated['provider'],
        );

        return response()->json($result, $result['duplicate'] ? 200 : 201);
    }

    public function show(Request $request, int $payment, PaymentService $payments): JsonResponse
    {
        $payment = $payments->show($request->user(), $payment);

        return response()->json(['payment' => $payments->paymentData($payment)]);
    }

    public function verify(Request $request, int $payment, PaymentService $payments): JsonResponse
    {
        return response()->json($payments->verify($request->user(), $payment));
    }

    /**
     * Provider-to-server webhook. Public by necessity: authentication comes
     * from the HMAC signature, not from a student bearer token.
     */
    public function callback(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        $signature = $request->header(PaymentService::SIGNATURE_HEADER) ?? $request->input('signature');

        return $payments->handleCallback($provider, $request->all(), is_string($signature) ? $signature : null);
    }
}
