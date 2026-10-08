<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('discount_type', 16);
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedBigInteger('discount_amount_minor')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('global_redemption_limit')->nullable();
            $table->unsignedInteger('per_user_redemption_limit')->nullable();
            $table->unsignedBigInteger('minimum_order_amount_minor')->default(0);
            $table->json('eligible_product_ids')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
