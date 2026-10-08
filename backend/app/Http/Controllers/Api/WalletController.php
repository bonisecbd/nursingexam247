<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets): JsonResponse
    {
        return response()->json($wallets->summary($request->user()));
    }

    public function transactions(Request $request, WalletService $wallets): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($wallets->transactions($request->user(), $validated['per_page'] ?? 20));
    }

    public function transaction(Request $request, int $transaction, WalletService $wallets): JsonResponse
    {
        return response()->json($wallets->transaction($request->user(), $transaction));
    }

    /**
     * Create a pending top-up order; the wallet is credited only after a
     * verified payment callback, never from this request.
     */
    public function topup(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:100', 'max:1000000000'],
        ]);

        $order = $orders->createTopupOrder($request->user(), $validated['amount_minor']);

        return response()->json([
            'message' => 'Top-up order created. Complete the payment to credit your wallet.',
            'order' => $orders->orderData($order),
        ], 201);
    }

    /**
     * Pay a wallet-payable order. The amount always comes from the order.
     */
    public function spend(Request $request, WalletService $wallets, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:96', 'regex:/^[A-Za-z0-9:_-]+$/'],
        ]);

        $result = $wallets->spend(
            $request->user(),
            $validated['order_id'],
            $validated['idempotency_key'],
            $orders,
        );

        return response()->json([
            'message' => $result['duplicate']
                ? 'This spend request was already processed; no additional amount was deducted.'
                : 'Order paid from your wallet.',
            'duplicate' => $result['duplicate'],
            'order' => $result['order'],
            'transaction_id' => $result['transaction_id'],
            'balance_minor' => $result['balance_minor'],
        ], $result['duplicate'] ? 200 : 201);
    }
}
