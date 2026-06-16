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
        Schema::create('beacon_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            // Dwell time in seconds, set when the visit is closed by an exit.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            // Speeds up finding the open visit for a beacon (one row per beacon
            // with a null exited_at at any time; enforced in the application).
            $table->index(['major', 'minor', 'exited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beacon_visits');
    }
};
