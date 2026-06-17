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
            // The end user's IP captured at consent, forwarded as X-PSU-IP on
            // demo-mode background syncs to access the customer-present rate limit.
            $table->string('psu_ip', 45)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_connections', function (Blueprint $table) {
            $table->dropColumn('psu_ip');
        });
    }
};
