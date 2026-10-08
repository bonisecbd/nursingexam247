<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('points_per_test')->default(5);
            $table->unsignedInteger('xp_per_test')->default(10);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('reward_type', 40);
            $table->string('idempotency_key')->unique();
            $table->integer('amount');
            $table->string('description');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'source_type', 'source_id', 'reward_type'], 'point_trans_source_reward_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('xp_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('reward_type', 40);
            $table->string('idempotency_key')->unique();
            $table->integer('amount');
            $table->string('description');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'source_type', 'source_id', 'reward_type'], 'xp_events_source_reward_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('badge_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon', 40);
            $table->string('trigger_type', 40);
            $table->unsignedInteger('threshold')->default(1);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_attempt_id')->nullable()->constrained('attempts')->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->unique(['user_id', 'badge_definition_id']);
            $table->index(['user_id', 'awarded_at']);
        });

        Schema::create('gamification_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('version');
            $table->unsignedSmallInteger('level');
            $table->string('title');
            $table->unsignedInteger('xp_required');
            $table->unique(['version', 'level']);
            $table->unique(['version', 'xp_required']);
        });

        DB::table('gamification_settings')->insert([
            'id' => 1,
            'points_per_test' => 5,
            'xp_per_test' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('badge_definitions')->insert([
            [
                'code' => 'first_test',
                'name' => 'First Step',
                'description' => 'Complete your first published model test.',
                'icon' => 'spark',
                'trigger_type' => 'tests_completed',
                'threshold' => 1,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ten_tests',
                'name' => 'Getting Consistent',
                'description' => 'Complete ten different published model tests.',
                'icon' => 'book',
                'trigger_type' => 'tests_completed',
                'threshold' => 10,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'perfect_test',
                'name' => 'Perfect Score',
                'description' => 'Score 100% on your first completion of a published test.',
                'icon' => 'trophy',
                'trigger_type' => 'perfect_test',
                'threshold' => 1,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('gamification_levels')->insert([
            ['version' => 1, 'level' => 1, 'title' => 'New Learner', 'xp_required' => 0],
            ['version' => 1, 'level' => 2, 'title' => 'Focused Learner', 'xp_required' => 100],
            ['version' => 1, 'level' => 3, 'title' => 'Steady Achiever', 'xp_required' => 300],
            ['version' => 1, 'level' => 4, 'title' => 'Skilled Nurse', 'xp_required' => 600],
            ['version' => 1, 'level' => 5, 'title' => 'Nursing Champion', 'xp_required' => 1000],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_levels');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badge_definitions');
        Schema::dropIfExists('xp_events');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('gamification_settings');
    }
};
