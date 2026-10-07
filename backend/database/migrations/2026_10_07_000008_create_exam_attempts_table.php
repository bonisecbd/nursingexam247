<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('total_marks', 10, 2);
            $table->decimal('percentage', 7, 2)->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('incorrect_count')->default(0);
            $table->unsignedSmallInteger('unanswered_count')->default(0);
            $table->boolean('passed')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'test_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
