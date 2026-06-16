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
        Schema::table('beacon_events', function (Blueprint $table) {
            // Seconds the device was in range, set on an exit that pairs with a
            // preceding enter for the same beacon. Null for enters and for
            // unmatched exits.
            $table->unsignedInteger('duration_seconds')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beacon_events', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
