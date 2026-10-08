<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->char('currency', 3)->default('BDT');
            // Cached projection of the append-only ledger. It is only ever written
            // inside a transaction that also appends the matching ledger entry.
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamp('balance_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16); // credit | debit
            // Signed integer minor units (poisha): credits are positive, debits negative.
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            // Signed ledger balance immediately after this entry was appended.
            $table->bigInteger('balance_after_minor');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 24)->default('completed'); // completed | pending | reversed
            $table->string('idempotency_key', 96)->unique();
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('description');
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
