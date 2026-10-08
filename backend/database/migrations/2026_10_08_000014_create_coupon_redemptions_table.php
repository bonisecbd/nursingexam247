<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Unique per order/coupon: one completed redemption per order and coupon.
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('status', 24)->default('reserved'); // reserved | completed | released
            $table->unsignedBigInteger('discount_minor');
            $table->char('currency', 3)->default('BDT');
            $table->dateTime('expires_at'); // unpaid reservations are released after this time
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'coupon_id']);
            $table->index(['coupon_id', 'status']);
            $table->index(['user_id', 'coupon_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
