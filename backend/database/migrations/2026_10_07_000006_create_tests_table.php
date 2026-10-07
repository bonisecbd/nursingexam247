<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('question_count');
            $table->decimal('total_marks', 8, 2);
            $table->decimal('passing_score', 8, 2);
            $table->decimal('negative_marking', 8, 2)->default(0);
            $table->boolean('is_negative_marking_enabled')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
