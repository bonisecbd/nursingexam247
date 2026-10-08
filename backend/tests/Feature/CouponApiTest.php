<?php

namespace Tests\Feature;

use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_validation_requires_authentication(): void
    {
        $package = SubscriptionPackage::query()->where('code', 'premium-monthly')->firstOrFail();

        $this->postJson('/api/coupons/validate', [
            'code' => 'NURSE10',
            'product_id' => $package->id,
        ])->assertUnauthorized();
    }

    public function test_admin_can_manage_coupons_while_students_are_denied(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');

        $this->withToken($studentToken)->getJson('/api/admin/coupons')->assertForbidden();
        $this->withToken($studentToken)->postJson('/api/admin/coupons', [
            'code' => 'STUDENT1',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
        ])->assertForbidden();
        $this->withToken($studentToken)->patchJson('/api/admin/coupons/1', [
            'code' => 'STUDENT1',
            'discount_type' => 'percentage',
            'discount_percent' => 20,
        ])->assertForbidden();
        $this->withToken($studentToken)->postJson('/api/admin/coupons/1/disable')->assertForbidden();

        $couponId = $this->createCoupon($adminToken, [
            'code' => 'NURSE10',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
        ]);

        $this->withToken($adminToken)->getJson('/api/admin/coupons')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'NURSE10')
            ->assertJsonPath('data.0.discount_percent', 10)
            ->assertJsonPath('data.0.status', 'active');

        $this->withToken($adminToken)->getJson('/api/admin/coupons?status=disabled')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($adminToken)->patchJson('/api/admin/coupons/'.$couponId, [
            'code' => 'NURSE10',
            'discount_type' => 'percentage',
            'discount_percent' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('coupon.discount_percent', 15);

        $this->withToken($adminToken)->postJson('/api/admin/coupons/'.$couponId.'/disable')
            ->assertOk()
            ->assertJsonPath('coupon.is_active', false)
            ->assertJsonPath('coupon.status', 'disabled');

        $this->withToken($adminToken)->getJson('/api/admin/coupons?status=disabled&search=nurse')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'NURSE10');

        $this->withToken($adminToken)->patchJson('/api/admin/coupons/999999', [
            'code' => 'NURSE10',
            'discount_type' => 'percentage',
            'discount_percent' => 15,
        ])->assertNotFound();
    }

    public function test_admin_coupon_creation_validates_discount_fields_and_code_format(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->postJson('/api/admin/coupons', [
            'code' => 'BAD DISCOUNT',
            'discount_type' => 'percentage',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'discount_percent']);

        $this->withToken($adminToken)->postJson('/api/admin/coupons', [
            'code' => 'ZERO',
            'discount_type' => 'percentage',
            'discount_percent' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_percent');

        $this->withToken($adminToken)->postJson('/api/admin/coupons', [
            'code' => 'FIXEDONLY',
            'discount_type' => 'fixed',
            'discount_amount_minor' => -50,
        ])->assertUnprocessable()->assertJsonValidationErrors('discount_amount_minor');

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_student_can_validate_a_coupon_and_the_server_computes_the_discount(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');
        $package = SubscriptionPackage::query()->where('code', 'premium-monthly')->firstOrFail();

        $this->createCoupon($adminToken, [
            'code' => 'NURSE10',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
        ]);
        $this->createCoupon($adminToken, [
            'code' => 'TAKA5',
            'discount_type' => 'fixed',
            'discount_amount_minor' => 500,
        ]);

        $percentage = $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'nurse10',
            'product_id' => $package->id,
        ]);

        $percentage->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('coupon.code', 'NURSE10')
            ->assertJsonPath('quote.subtotal_minor', 10000)
            ->assertJsonPath('quote.discount_minor', 1000)
            ->assertJsonPath('quote.payable_minor', 9000)
            ->assertJsonPath('quote.currency', 'BDT');

        $fixed = $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'TAKA5',
            'product_id' => $package->id,
        ]);

        $fixed->assertOk()
            ->assertJsonPath('quote.discount_minor', 500)
            ->assertJsonPath('quote.payable_minor', 9500);

        // A quote is a read-only check: it must not reserve or redeem anything.
        $this->assertDatabaseCount('coupon_redemptions', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_coupon_validation_rejects_unknown_disabled_and_out_of_window_codes(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');
        $package = SubscriptionPackage::query()->where('code', 'premium-monthly')->firstOrFail();

        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'DOESNOTEXIST',
            'product_id' => $package->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $disabled = $this->createCoupon($adminToken, ['code' => 'DISABLED1']);
        $this->withToken($adminToken)->postJson('/api/admin/coupons/'.$disabled.'/disable')->assertOk();
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'DISABLED1',
            'product_id' => $package->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->createCoupon($adminToken, [
            'code' => 'FUTURE10',
            'starts_at' => now()->addDay()->toISOString(),
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'FUTURE10',
            'product_id' => $package->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->createCoupon($adminToken, [
            'code' => 'EXPIRED10',
            'ends_at' => now()->subMinute()->toISOString(),
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'EXPIRED10',
            'product_id' => $package->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        // Expiry boundary: a coupon ending one minute from now is still usable.
        $this->createCoupon($adminToken, [
            'code' => 'ENDING10',
            'starts_at' => now()->subHour()->toISOString(),
            'ends_at' => now()->addMinute()->toISOString(),
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'ENDING10',
            'product_id' => $package->id,
        ])->assertOk()->assertJsonPath('valid', true);
    }

    public function test_coupon_validation_enforces_product_minimum_and_currency_rules(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');
        $monthly = SubscriptionPackage::query()->where('code', 'premium-monthly')->firstOrFail();
        $yearly = SubscriptionPackage::query()->where('code', 'premium-yearly')->firstOrFail();

        $this->createCoupon($adminToken, [
            'code' => 'ONLYYEAR',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
            'eligible_product_ids' => [$yearly->id],
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'ONLYYEAR',
            'product_id' => $monthly->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'ONLYYEAR',
            'product_id' => $yearly->id,
        ])->assertOk();

        $this->createCoupon($adminToken, [
            'code' => 'MINIMUM',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
            'minimum_order_amount_minor' => 50000,
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'MINIMUM',
            'product_id' => $monthly->id, // costs 10000 poisha
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'MINIMUM',
            'product_id' => $yearly->id, // costs 100000 poisha
        ])->assertOk();

        // Currency is checked server-side even for records created outside the admin API.
        DB::table('coupons')->insert([
            'code' => 'USDONLY',
            'discount_type' => 'percentage',
            'discount_percent' => 10,
            'currency' => 'USD',
            'is_active' => true,
            'minimum_order_amount_minor' => 0,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'USDONLY',
            'product_id' => $monthly->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'MINIMUM',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'MINIMUM',
            'product_id' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_discount_is_capped_at_the_subtotal_so_payable_is_never_negative(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');
        [, $studentToken] = $this->createUserWithToken('student');
        $package = SubscriptionPackage::query()->where('code', 'premium-monthly')->firstOrFail();

        $this->createCoupon($adminToken, [
            'code' => 'HUGEFIX',
            'discount_type' => 'fixed',
            'discount_amount_minor' => 999999,
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'HUGEFIX',
            'product_id' => $package->id,
        ])
            ->assertOk()
            ->assertJsonPath('quote.discount_minor', 10000)
            ->assertJsonPath('quote.payable_minor', 0);

        $this->createCoupon($adminToken, [
            'code' => 'FULL100',
            'discount_type' => 'percentage',
            'discount_percent' => 100,
        ]);
        $this->withToken($studentToken)->postJson('/api/coupons/validate', [
            'code' => 'FULL100',
            'product_id' => $package->id,
        ])
            ->assertOk()
            ->assertJsonPath('quote.discount_minor', 10000)
            ->assertJsonPath('quote.payable_minor', 0);
    }

    private function createCoupon(string $adminToken, array $overrides = []): int
    {
        $payload = array_merge([
            'code' => 'C'.Str::upper(Str::random(8)),
            'discount_type' => 'percentage',
            'discount_percent' => 10,
        ], $overrides);

        if ($payload['discount_type'] === 'fixed') {
            unset($payload['discount_percent']);
            $payload += ['discount_amount_minor' => 500];
        }

        return $this->withToken($adminToken)
            ->postJson('/api/admin/coupons', $payload)
            ->assertCreated()
            ->json('coupon.id');
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'coupon-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Coupon test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
