<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('subscription_packages')->restrictOnDelete();
            // One entitlement record per confirmed order; this is the idempotency
            // guard that makes duplicate payment callbacks harmless.
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->string('status', 24)->default('active'); // active | expired | cancelled | refunded
            $table->string('source', 16)->default('payment'); // payment | wallet | admin
            // Required instants use dateTime: a NOT NULL timestamp column would
            // pick up MySQL's implicit ON UPDATE CURRENT_TIMESTAMP behaviour.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
