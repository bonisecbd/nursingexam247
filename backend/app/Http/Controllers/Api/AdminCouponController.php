<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['all', 'active', 'scheduled', 'expired', 'disabled'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DB::table('coupons')
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('code', 'like', '%'.strtoupper($search).'%'))
            ->when(($validated['status'] ?? 'all') === 'disabled', fn ($query) => $query->where('is_active', false))
            ->when(($validated['status'] ?? null) === 'active', function ($query): void {
                $query->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
            })
            ->when(($validated['status'] ?? null) === 'scheduled', fn ($query) => $query
                ->where('is_active', true)
                ->where('starts_at', '>', now()))
            ->when(($validated['status'] ?? null) === 'expired', fn ($query) => $query
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now()));

        $coupons = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => collect($coupons->items())->map(fn (object $coupon): array => $this->couponData($coupon))->values(),
            'meta' => [
                'total' => $coupons->total(),
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $this->validateCoupon($request);
        $this->validateDateRange($validated);
        $now = now();
        $id = DB::table('coupons')->insertGetId($this->couponValues($validated) + [
            'created_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'message' => 'Coupon created. Checkout validation and redemption will be available with the purchase module.',
            'coupon' => $this->couponData(DB::table('coupons')->where('id', $id)->firstOrFail()),
        ], 201);
    }

    public function update(Request $request, int $coupon): JsonResponse
    {
        $this->authorizeAdmin($request);
        $existing = DB::table('coupons')->where('id', $coupon)->firstOrFail();
        $validated = $this->validateCoupon($request, (int) $existing->id);
        $this->validateDateRange($validated);
        DB::table('coupons')->where('id', $coupon)->update($this->couponValues($validated) + [
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Coupon updated.',
            'coupon' => $this->couponData(DB::table('coupons')->where('id', $coupon)->firstOrFail()),
        ]);
    }

    public function disable(Request $request, int $coupon): JsonResponse
    {
        $this->authorizeAdmin($request);
        $existing = DB::table('coupons')->where('id', $coupon)->firstOrFail();
        DB::table('coupons')->where('id', $existing->id)->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Coupon disabled.',
            'coupon' => $this->couponData(DB::table('coupons')->where('id', $coupon)->firstOrFail()),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    private function validateCoupon(Request $request, ?int $couponId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_percent' => ['required_if:discount_type,percentage', 'nullable', 'integer', 'min:1', 'max:100', 'prohibited_if:discount_type,fixed'],
            'discount_amount_minor' => ['required_if:discount_type,fixed', 'nullable', 'integer', 'min:1', 'max:1000000000', 'prohibited_if:discount_type,percentage'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:BDT'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'global_redemption_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000000'],
            'per_user_redemption_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'minimum_order_amount_minor' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'eligible_product_ids' => ['sometimes', 'nullable', 'array', 'max:100'],
            'eligible_product_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'is_stackable' => ['sometimes', 'boolean'],
        ]);

        if ($request->isMethod('patch')) {
            foreach (['discount_type', 'discount_percent', 'discount_amount_minor', 'currency'] as $required) {
                if (! array_key_exists($required, $validated)) {
                    $validated[$required] = $request->input($required);
                }
            }
            $validator = Validator::make($validated, [
                'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
                'discount_percent' => ['required_if:discount_type,percentage', 'nullable', 'integer', 'min:1', 'max:100', 'prohibited_if:discount_type,fixed'],
                'discount_amount_minor' => ['required_if:discount_type,fixed', 'nullable', 'integer', 'min:1', 'max:1000000000', 'prohibited_if:discount_type,percentage'],
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }

        return $validated;
    }

    private function validateDateRange(array $validated): void
    {
        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])
            && CarbonImmutable::parse($validated['ends_at'])->lessThanOrEqualTo(CarbonImmutable::parse($validated['starts_at']))) {
            throw ValidationException::withMessages([
                'ends_at' => ['The end date must be later than the start date.'],
            ]);
        }
    }

    private function couponValues(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'discount_type' => $validated['discount_type'],
            'discount_percent' => $validated['discount_type'] === 'percentage' ? $validated['discount_percent'] : null,
            'discount_amount_minor' => $validated['discount_type'] === 'fixed' ? $validated['discount_amount_minor'] : null,
            'currency' => strtoupper($validated['currency'] ?? 'BDT'),
            'starts_at' => isset($validated['starts_at']) ? CarbonImmutable::parse($validated['starts_at'])->utc() : null,
            'ends_at' => isset($validated['ends_at']) ? CarbonImmutable::parse($validated['ends_at'])->utc() : null,
            'is_active' => $validated['is_active'] ?? true,
            'global_redemption_limit' => $validated['global_redemption_limit'] ?? null,
            'per_user_redemption_limit' => $validated['per_user_redemption_limit'] ?? null,
            'minimum_order_amount_minor' => $validated['minimum_order_amount_minor'] ?? 0,
            'eligible_product_ids' => isset($validated['eligible_product_ids'])
                ? json_encode(array_values(array_unique($validated['eligible_product_ids'])), JSON_THROW_ON_ERROR)
                : null,
            'is_stackable' => $validated['is_stackable'] ?? false,
        ];
    }

    private function couponData(object $coupon): array
    {
        return [
            'id' => (int) $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_percent' => $coupon->discount_percent === null ? null : (int) $coupon->discount_percent,
            'discount_amount_minor' => $coupon->discount_amount_minor === null ? null : (int) $coupon->discount_amount_minor,
            'currency' => $coupon->currency,
            'starts_at' => $coupon->starts_at ? CarbonImmutable::parse($coupon->starts_at, 'UTC')->toISOString() : null,
            'ends_at' => $coupon->ends_at ? CarbonImmutable::parse($coupon->ends_at, 'UTC')->toISOString() : null,
            'status' => $this->status($coupon),
            'is_active' => (bool) $coupon->is_active,
            'global_redemption_limit' => $coupon->global_redemption_limit === null ? null : (int) $coupon->global_redemption_limit,
            'per_user_redemption_limit' => $coupon->per_user_redemption_limit === null ? null : (int) $coupon->per_user_redemption_limit,
            'minimum_order_amount_minor' => (int) $coupon->minimum_order_amount_minor,
            'eligible_product_ids' => $coupon->eligible_product_ids === null ? null : json_decode($coupon->eligible_product_ids, true, 512, JSON_THROW_ON_ERROR),
            'is_stackable' => (bool) $coupon->is_stackable,
            'created_at' => CarbonImmutable::parse($coupon->created_at, 'UTC')->toISOString(),
            'updated_at' => CarbonImmutable::parse($coupon->updated_at, 'UTC')->toISOString(),
        ];
    }

    private function status(object $coupon): string
    {
        if (! $coupon->is_active) {
            return 'disabled';
        }
        if ($coupon->starts_at && CarbonImmutable::parse($coupon->starts_at, 'UTC')->isFuture()) {
            return 'scheduled';
        }
        if ($coupon->ends_at && CarbonImmutable::parse($coupon->ends_at, 'UTC')->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
