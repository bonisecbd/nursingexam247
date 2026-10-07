<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('points', 8, 2);
            $table->timestamps();

            $table->unique(['test_id', 'question_id']);
            $table->unique(['test_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};
