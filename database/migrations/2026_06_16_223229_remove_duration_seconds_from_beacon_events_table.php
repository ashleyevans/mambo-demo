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
        // Dwell time now lives on beacon_visits; the raw event log is just a feed.
        if (Schema::hasColumn('beacon_events', 'duration_seconds')) {
            Schema::table('beacon_events', function (Blueprint $table) {
                $table->dropColumn('duration_seconds');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('beacon_events', 'duration_seconds')) {
            Schema::table('beacon_events', function (Blueprint $table) {
                $table->unsignedInteger('duration_seconds')->nullable()->after('type');
            });
        }
    }
};
