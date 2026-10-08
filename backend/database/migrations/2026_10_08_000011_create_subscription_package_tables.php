<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            // Price in integer poisha (1 BDT = 100 poisha). Never a float.
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('BDT');
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('package_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('subscription_packages')->cascadeOnDelete();
            $table->string('entitlement_type', 32); // all_premium_tests | test
            $table->foreignId('test_id')->nullable()->constrained('tests')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['package_id', 'entitlement_type', 'test_id'], 'package_entitlement_unique');
            $table->index(['test_id']);
        });

        $now = now();
        // Attribute the catalogue to the first admin account when one exists; the
        // column is nullable so tests (no seeded users yet) can migrate cleanly.
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');

        if (! DB::table('subscription_packages')->where('code', 'premium-monthly')->exists()) {
            DB::table('subscription_packages')->insert([
                [
                    'name' => 'Premium Monthly',
                    'code' => 'premium-monthly',
                    'description' => 'Thirty days of premium access to all premium model tests.',
                    'price_minor' => 10000, // BDT 100.00
                    'currency' => 'BDT',
                    'duration_days' => 30,
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Premium Yearly',
                    'code' => 'premium-yearly',
                    'description' => 'One year of premium access to all premium model tests.',
                    'price_minor' => 100000, // BDT 1000.00
                    'currency' => 'BDT',
                    'duration_days' => 365,
                    'is_active' => true,
                    'sort_order' => 2,
                    'created_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $monthlyId = DB::table('subscription_packages')->where('code', 'premium-monthly')->value('id');
            $yearlyId = DB::table('subscription_packages')->where('code', 'premium-yearly')->value('id');

            DB::table('package_entitlements')->insert([
                [
                    'package_id' => $monthlyId,
                    'entitlement_type' => 'all_premium_tests',
                    'test_id' => null,
                    'created_at' => $now,
                ],
                [
                    'package_id' => $yearlyId,
                    'entitlement_type' => 'all_premium_tests',
                    'test_id' => null,
                    'created_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_entitlements');
        Schema::dropIfExists('subscription_packages');
    }
};
