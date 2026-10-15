<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defaults = [
            // --- Referral ---------------------------------------------------
            ['referral.enabled', true, 'boolean', 'referral', true, 'Whether new referral codes are accepted for registration attribution and redemption.', null],
            ['referral.onboarding_window_hours', 24, 'integer', 'referral', true, 'Hours after registration during which a student may still attribute a referral code.', 'min:1|max:720'],
            ['referral.code_length', 16, 'integer', 'referral', false, 'Character length of newly generated referral codes; existing codes are unaffected.', 'min:8|max:32'],
            ['referral.reward_points', 100, 'integer', 'referral', true, 'Points reward per qualified invite; issued once the purchase policy is active.', 'min:0|max:100000'],
            ['referral.reward_wallet_minor', 5000, 'integer', 'referral', true, 'Wallet credit in minor units per qualified invite; issued once the purchase policy is active.', 'min:0|max:10000000'],

            // --- Gamification ----------------------------------------------
            ['gamification.enabled', true, 'boolean', 'gamification', true, 'Master switch for test-completion points, XP and badge awards.', null],
            ['gamification.badges_enabled', true, 'boolean', 'gamification', true, 'Whether eligible badges are awarded together with completion rewards.', null],
            ['gamification.perfect_test_bonus_points', 0, 'integer', 'gamification', true, 'Extra points awarded on top of the reward rules for a 100% score.', 'min:0|max:1000'],
        ];

        foreach ($defaults as [$key, $value, $type, $group, $isPublic, $description, $constraints]) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value === null ? null : (string) $value,
                    'type' => $type,
                    'group' => $group,
                    'is_public' => $isPublic,
                    'description' => $description,
                    'constraints' => $constraints,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'referral.enabled',
            'referral.onboarding_window_hours',
            'referral.code_length',
            'referral.reward_points',
            'referral.reward_wallet_minor',
            'gamification.enabled',
            'gamification.badges_enabled',
            'gamification.perfect_test_bonus_points',
        ])->delete();
    }
};
