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
        Schema::create('beacon_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->string('type'); // enter | exit
            $table->timestamps();

            $table->index(['major', 'minor']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beacon_events');
    }
};
