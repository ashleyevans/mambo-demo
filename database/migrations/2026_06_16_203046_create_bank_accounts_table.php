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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->string('truelayer_account_id');
            $table->string('display_name')->nullable();
            $table->string('account_type')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('account_number')->nullable();
            $table->string('sort_code')->nullable();
            $table->string('iban')->nullable();
            $table->decimal('current_balance', 15, 2)->nullable();
            $table->decimal('available_balance', 15, 2)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['bank_connection_id', 'truelayer_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
