<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            // Author/audit: the admin who created the announcement.
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->string('target_audience', 16)->default('all'); // all | students
            $table->string('status', 16)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // optional end of the publish window
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32); // announcement | reward | badge | result | reminder | offer | account
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->json('data')->nullable(); // safe structured payload; never secrets/tokens/credentials
            $table->string('channel', 16)->default('in_app');
            $table->string('status', 16)->default('unread'); // unread | read
            $table->timestamp('read_at')->nullable();
            // Deduplication key: retrying a delivery with the same key must not
            // create a second user-visible row (unique per user).
            $table->string('dedup_key', 160)->nullable();
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'dedup_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            // Per-category opt-outs. Critical account/security notices ignore
            // these flags by policy (see NotificationService::notify()).
            $table->boolean('announcements')->default(true);
            $table->boolean('rewards')->default(true);
            $table->boolean('results')->default(true);
            $table->boolean('offers')->default(true);
            $table->boolean('reminders')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcements');
    }
};
