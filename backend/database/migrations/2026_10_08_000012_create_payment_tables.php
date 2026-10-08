<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32); // subscription | wallet_topup
            $table->foreignId('package_id')->nullable()->constrained('subscription_packages')->nullOnDelete();
            $table->char('currency', 3)->default('BDT');
            // Server-calculated price breakdown in integer poisha.
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('reference', 32)->unique(); // internal order reference
            $table->string('status', 24)->default('pending'); // pending | paid | cancelled | expired
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['purpose', 'status']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider', 24); // bkash | nagad | sslcommerz
            $table->string('reference', 40)->unique(); // internal payment reference
            $table->string('status', 16)->default('pending'); // pending | paid | failed | cancelled
            // Exact integer poisha amount required from the provider; never client supplied.
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('provider_transaction_id', 128)->nullable()->unique();
            $table->json('provider_action')->nullable(); // sanitized redirect/action data for the client
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable(); // sanitized provider payload only, never credentials
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('event', 48);
            $table->string('status', 16)->nullable(); // payment status after the event
            $table->json('payload')->nullable(); // sanitized audit data, no provider secrets
            $table->timestamp('created_at');

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
    }
};
