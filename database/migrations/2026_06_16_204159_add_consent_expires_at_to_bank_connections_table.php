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
        Schema::table('bank_connections', function (Blueprint $table) {
            $table->timestamp('consent_expires_at')->nullable()->after('token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_connections', function (Blueprint $table) {
            $table->dropColumn('consent_expires_at');
        });
    }
};
