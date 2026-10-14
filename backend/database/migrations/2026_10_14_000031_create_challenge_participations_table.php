<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // One participation links to exactly one normal exam attempt, which
            // stays the authoritative record for answers, timer, and scoring.
            $table->foreignId('attempt_id')->unique()->constrained('attempts')->cascadeOnDelete();
            // Occurrence bucket: daily:2026-10-14 | weekly:2026-W42 | once.
            $table->string('occurrence_key', 32);
            $table->unsignedTinyInteger('attempt_index');
            // Settlement outcome (idempotent, written at most once).
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unsignedInteger('wallet_minor_awarded')->default(0);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            // True for the single participation that holds the occurrence reward.
            $table->boolean('rewarded')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            // Short explicit name: MySQL caps identifier names at 64 chars.
            $table->unique(['challenge_id', 'user_id', 'occurrence_key', 'attempt_index'], 'challenge_part_occurrence_attempt_unique');
            $table->index(['challenge_id', 'settled_at']);
            $table->index(['user_id', 'challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_participations');
    }
};
