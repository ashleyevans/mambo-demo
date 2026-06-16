<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();

            // TrueLayer transaction fields
            $table->string('truelayer_transaction_id');
            $table->string('normalised_provider_transaction_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_category')->nullable();
            $table->json('transaction_classification')->nullable();
            $table->string('merchant_name')->nullable();
            $table->decimal('running_balance', 15, 2)->nullable();
            $table->string('running_balance_currency', 3)->nullable();
            $table->json('meta')->nullable();

            // Full untouched payload so every available field is preserved
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->unique(['bank_account_id', 'truelayer_transaction_id'], 'bank_transactions_account_tl_txn_unique');
            $table->index(['user_id', 'booked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
