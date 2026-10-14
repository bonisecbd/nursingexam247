<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_are_readable_without_authentication(): void
    {
        $response = $this->getJson('/api/settings/public')->assertOk();

        $settings = $response->json('settings');

        $this->assertSame('NurseExam247', $settings['site.display_name']);
        $this->assertSame('Asia/Dhaka', $settings['site.timezone']);
        $this->assertSame(30, $settings['exam.default_duration_minutes']);
        $this->assertSame(true, $settings['exam.allow_backward_navigation']);
        $this->assertSame(60, $settings['scoring.passing_score']);
        $this->assertSame(false, $settings['scoring.negative_marking_enabled']);
        $this->assertSame('weekly', $settings['leaderboard.default_period']);
        $this->assertSame(true, $settings['commerce.coupons_enabled']);
    }

    public function test_public_settings_never_expose_private_operational_settings(): void
    {
        $settings = $this->getJson('/api/settings/public')
            ->assertOk()
            ->json('settings');

        foreach ([
            'scoring.default_total_marks',
            'commerce.supported_payment_providers',
            'commerce.refund_window_days',
        ] as $privateKey) {
            $this->assertArrayNotHasKey($privateKey, $settings, "Private setting [$privateKey] must not be public.");
        }
    }

    public function test_admin_settings_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->patchJson('/api/admin/settings', [
            'settings' => ['site.locale' => 'bn'],
            'reason' => 'Switch locale',
        ])->assertUnauthorized();
    }

    public function test_students_are_forbidden_from_admin_settings(): void
    {
        [, $studentToken] = $this->createUserWithToken('student');

        $this->withToken($studentToken)->getJson('/api/admin/settings')->assertForbidden();
        $this->withToken($studentToken)->patchJson('/api/admin/settings', [
            'settings' => ['site.locale' => 'bn'],
            'reason' => 'Switch locale',
        ])->assertForbidden();
    }

    public function test_admin_can_list_all_settings_with_metadata(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $settings = $this->withToken($adminToken)->getJson('/api/admin/settings')
            ->assertOk()
            ->json('settings');

        $this->assertCount(23, $settings);

        $byKey = collect($settings)->keyBy('key');

        $private = $byKey->get('commerce.supported_payment_providers');
        $this->assertFalse($private['is_public']);
        $this->assertSame('string', $private['type']);
        $this->assertSame('commerce', $private['group']);
        $this->assertNotEmpty($private['description']);

        $duration = $byKey->get('exam.default_duration_minutes');
        $this->assertSame('integer', $duration['type']);
        $this->assertSame(30, $duration['value']);
        $this->assertSame('min:1|max:480', $duration['constraints']);
    }

    public function test_admin_can_update_settings_and_the_change_is_audited(): void
    {
        [$admin, $adminToken] = $this->createUserWithToken('admin');

        $response = $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => [
                'site.locale' => 'bn',
                'exam.default_duration_minutes' => 45,
            ],
            'reason' => 'Regional rollout to Bangla medium students.',
        ])->assertOk();

        $this->assertSame('Settings updated.', $response->json('message'));

        $changed = collect($response->json('changed'))->keyBy('key');
        $this->assertSame(['key' => 'site.locale', 'old' => 'en', 'new' => 'bn'], $changed->get('site.locale'));
        $this->assertSame(
            ['key' => 'exam.default_duration_minutes', 'old' => 30, 'new' => 45],
            $changed->get('exam.default_duration_minutes')
        );

        // Persisted values are visible to the public endpoint too.
        $public = $this->getJson('/api/settings/public')->assertOk()->json('settings');
        $this->assertSame('bn', $public['site.locale']);
        $this->assertSame(45, $public['exam.default_duration_minutes']);

        $this->assertDatabaseHas('settings', [
            'key' => 'site.locale',
            'value' => 'bn',
            'updated_by' => $admin->id,
        ]);

        $audit = DB::table('setting_audits')->where('key', 'site.locale')->firstOrFail();
        $this->assertSame($admin->id, (int) $audit->actor_id);
        $this->assertSame('en', $audit->old_value);
        $this->assertSame('bn', $audit->new_value);
        $this->assertSame('Regional rollout to Bangla medium students.', $audit->reason);
        $this->assertNotNull($audit->created_at);

        $durationAudit = DB::table('setting_audits')->where('key', 'exam.default_duration_minutes')->firstOrFail();
        $this->assertSame('30', $durationAudit->old_value);
        $this->assertSame('45', $durationAudit->new_value);
    }

    public function test_updating_a_setting_to_its_current_value_is_a_no_op(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['site.locale' => 'en'],
            'reason' => 'Trying to re-set the same value.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'No settings changed.')
            ->assertJsonPath('changed', []);

        $this->assertDatabaseCount('setting_audits', 0);
    }

    public function test_admin_update_rejects_unknown_keys(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['payments.secret_key' => 'sk_live_xxx'],
            'reason' => 'Attempting to smuggle a secret.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.payments.secret_key');

        $this->assertDatabaseCount('setting_audits', 0);
    }

    public function test_admin_update_validates_types_and_constraints(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        // Duration must be a positive integer within bounds.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['exam.default_duration_minutes' => 0],
            'reason' => 'Zero-minute exam.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.exam.default_duration_minutes');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['exam.default_duration_minutes' => 'long'],
            'reason' => 'Not a number.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.exam.default_duration_minutes');

        // Enum-constrained string.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['exam.result_release_policy' => 'whenever'],
            'reason' => 'Invalid policy.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.exam.result_release_policy');

        // Boolean flag.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['scoring.negative_marking_enabled' => 'maybe'],
            'reason' => 'Invalid flag.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.scoring.negative_marking_enabled');

        // Failed validation writes nothing.
        $this->assertDatabaseHas('settings', ['key' => 'exam.default_duration_minutes', 'value' => '30']);
        $this->assertDatabaseCount('setting_audits', 0);
    }

    public function test_cross_field_invariants_are_validated_together(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        // Passing score above the scale is rejected.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => [
                'scoring.pass_scale' => 50,
                'scoring.passing_score' => 80,
            ],
            'reason' => 'Impossible pass mark.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.scoring.passing_score');

        // Passing score equal to the scale is fine.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => [
                'scoring.pass_scale' => 50,
                'scoring.passing_score' => 50,
            ],
            'reason' => 'Pass mark equals the scale.',
        ])->assertOk();

        // The invariant also guards the pre-existing state, not just the payload.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['scoring.pass_scale' => 40],
            'reason' => 'Shrink the scale below the pass mark.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.scoring.passing_score');

        // Negative penalties are rejected (non-negative invariant).
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['scoring.negative_penalty_points' => -1],
            'reason' => 'Negative penalty.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.scoring.negative_penalty_points');

        $this->assertDatabaseHas('settings', ['key' => 'scoring.pass_scale', 'value' => '50']);
    }

    public function test_setting_reads_are_cached_and_invalidated_on_update(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        // Warm the cache through the public endpoint.
        $public = $this->getJson('/api/settings/public')->assertOk()->json('settings');
        $this->assertSame('NurseExam247', $public['site.display_name']);

        // An out-of-band change is invisible while the cache is warm.
        DB::table('settings')->where('key', 'site.display_name')->update(['value' => 'Changed Directly']);

        $public = $this->getJson('/api/settings/public')->assertOk()->json('settings');
        $this->assertSame('NurseExam247', $public['site.display_name']);

        // A successful admin update invalidates the cache.
        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['site.display_name' => 'NurseExam247 BD'],
            'reason' => 'Rebrand.',
        ])->assertOk();

        $public = $this->getJson('/api/settings/public')->assertOk()->json('settings');
        $this->assertSame('NurseExam247 BD', $public['site.display_name']);
    }

    public function test_admin_update_requires_a_reason_and_a_non_empty_settings_map(): void
    {
        [, $adminToken] = $this->createUserWithToken('admin');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['site.locale' => 'bn'],
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => [],
            'reason' => 'Nothing to change.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => ['site.locale' => 'bn'],
            'reason' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->withToken($adminToken)->patchJson('/api/admin/settings', [
            'settings' => $this->tooManySettings(),
            'reason' => 'Flooding the settings endpoint.',
        ])->assertUnprocessable()->assertJsonValidationErrors('settings');
    }

    private function tooManySettings(): array
    {
        $payload = [];
        for ($i = 0; $i < 51; $i++) {
            $payload['junk.key_'.$i] = 'x';
        }

        return $payload;
    }

    private function createUserWithToken(string $role = 'student'): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = 'settings-token-'.$user->id.'-'.Str::lower(Str::random(8));
        $user->apiTokens()->create([
            'name' => 'Settings test token',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }
}
