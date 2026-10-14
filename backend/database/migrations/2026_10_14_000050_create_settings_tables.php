<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            // string | integer | boolean — typed, never opaque JSON blobs.
            $table->string('type', 20);
            $table->string('group', 40)->default('site');
            $table->boolean('is_public')->default(false);
            $table->string('description');
            // Pipe-delimited Laravel validation rules applied to value updates
            // (for example "min:1|max:480" or "nullable|max:30").
            $table->string('constraints', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group', 'is_public']);
        });

        Schema::create('setting_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setting_id')->constrained('settings')->cascadeOnDelete();
            // Denormalised key so the audit history stays readable.
            $table->string('key', 120);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at');

            $table->index(['setting_id', 'created_at']);
        });

        $now = now();
        $defaults = [
            // --- Site -------------------------------------------------------
            ['site.display_name', 'NurseExam247', 'string', 'site', true, 'Application display name shown to clients.', 'min:1|max:100'],
            ['site.support_email', 'support@nurseexam247.com', 'string', 'site', true, 'Public support contact email address.', 'email|max:255'],
            ['site.support_phone', null, 'string', 'site', true, 'Public support contact phone number.', 'nullable|max:30'],
            ['site.logo_url', null, 'string', 'site', true, 'Public logo image URL.', 'nullable|max:500'],
            ['site.timezone', 'Asia/Dhaka', 'string', 'site', true, 'Default timezone for daily operations and leaderboard boundaries.', 'in:Asia/Dhaka,UTC'],
            ['site.locale', 'en', 'string', 'site', true, 'Default interface locale.', 'in:en,bn'],
            ['site.maintenance_notice', null, 'string', 'site', true, 'Optional maintenance notice shown to students.', 'nullable|max:500'],

            // --- Exam ------------------------------------------------------
            ['exam.default_duration_minutes', 30, 'integer', 'exam', true, 'Default exam duration in minutes for new tests.', 'min:1|max:480'],
            ['exam.max_question_count', 200, 'integer', 'exam', true, 'Maximum questions allowed in a single test.', 'min:1|max:1000'],
            ['exam.allow_backward_navigation', true, 'boolean', 'exam', true, 'Whether students may revisit previous questions during an exam.', null],
            ['exam.autosave_interval_seconds', 30, 'integer', 'exam', true, 'How often in-progress answers are autosaved.', 'min:5|max:300'],
            ['exam.result_release_policy', 'immediate', 'string', 'exam', true, 'When results become visible to students.', 'in:immediate,after_deadline,manual'],

            // --- Scoring ---------------------------------------------------
            ['scoring.default_total_marks', 100, 'integer', 'scoring', false, 'Default total marks for newly created tests.', 'min:1|max:10000'],
            ['scoring.pass_scale', 100, 'integer', 'scoring', true, 'Scale the passing score is expressed against (for example 100 for percentages).', 'min:1|max:1000'],
            ['scoring.passing_score', 60, 'integer', 'scoring', true, 'Passing threshold on the configured score scale; must not exceed the scale.', 'min:0|max:1000'],
            ['scoring.negative_marking_enabled', false, 'boolean', 'scoring', true, 'Whether wrong answers deduct points.', null],
            ['scoring.negative_penalty_points', 0, 'integer', 'scoring', true, 'Points deducted per wrong answer; never negative.', 'min:0|max:100'],

            // --- Leaderboard ----------------------------------------------
            ['leaderboard.default_period', 'weekly', 'string', 'leaderboard', true, 'Default leaderboard period.', 'in:daily,weekly,monthly'],
            ['leaderboard.timezone', 'Asia/Dhaka', 'string', 'leaderboard', true, 'Timezone used for leaderboard period boundaries.', 'in:Asia/Dhaka,UTC'],

            // --- Commerce --------------------------------------------------
            ['commerce.default_currency', 'BDT', 'string', 'commerce', true, 'Default transaction currency code.', 'size:3'],
            ['commerce.coupons_enabled', true, 'boolean', 'commerce', true, 'Whether coupon validation and redemption are enabled.', null],
            ['commerce.supported_payment_providers', 'bkash,nagad,sslcommerz', 'string', 'commerce', false, 'Comma-separated enabled payment provider names.', 'max:100'],
            ['commerce.refund_window_days', 7, 'integer', 'commerce', false, 'Days after payment during which a refund may be requested.', 'min:0|max:90'],
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
        Schema::dropIfExists('setting_audits');
        Schema::dropIfExists('settings');
    }
};
