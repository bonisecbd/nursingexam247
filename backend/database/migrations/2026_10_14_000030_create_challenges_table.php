<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->text('description')->nullable();
            // daily | weekly | once — labels the recurrence cadence used to
            // bucket participations into occurrences (see ChallengeService).
            $table->string('period', 10);
            // Versioned test reference: the challenge reuses the Exam module
            // attempt/scoring contract against this published test.
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            // Timezone used to compute local daily/weekly occurrence windows.
            $table->string('timezone', 64)->default('Asia/Dhaka');
            // Overall availability window (UTC). Joins are only allowed inside it.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Attempt timer in minutes; a timer never outlives ends_at.
            $table->unsignedSmallInteger('duration_minutes');
            // Allowed attempts per occurrence (per day/week/whole range).
            $table->unsignedTinyInteger('attempts_allowed')->default(1);
            // Reward rule: gamification points/XP plus an optional prize in
            // integer poisha settled to the student wallet.
            $table->unsignedInteger('reward_points')->default(0);
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_wallet_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['period', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
