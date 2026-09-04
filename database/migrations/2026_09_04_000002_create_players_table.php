<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->string('device_token', 64)->nullable();   // null only for The Machine; links a person across sessions
            $table->string('username', 24);
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_admitted')->default(true);    // false for a late joiner waiting on the director
            $table->timestampTz('kicked_at', 3)->nullable();
            $table->timestampTz('last_seen_at', 3)->nullable();
            $table->integer('total_points')->default(0);      // cache; recomputable from decisions
            $table->unsignedSmallInteger('consecutive_timeouts')->default(0);
            $table->timestampsTz(3);

            $table->unique(['game_session_id', 'device_token']);
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
