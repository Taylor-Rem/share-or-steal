<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named game_sessions, not sessions, so it never collides with Laravel's own session table.
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('mode', 16)->default('normal');       // App\Enums\SessionMode
            $table->string('status', 24)->default('lobby');      // App\Enums\SessionStatus
            $table->boolean('fast_mode')->default(false);
            $table->unsignedSmallInteger('rounds_count');
            $table->unsignedSmallInteger('decisions_per_round');
            $table->unsignedSmallInteger('max_players');

            // Where the game is right now. Owned by the game:run loop.
            $table->unsignedSmallInteger('current_round')->nullable();
            $table->unsignedSmallInteger('current_decision')->nullable();
            $table->timestampTz('phase_ends_at', 3)->nullable();     // deadline of the current timed phase

            // Pause overlay.
            $table->timestampTz('paused_at', 3)->nullable();
            $table->string('paused_from_status', 24)->nullable();
            $table->unsignedInteger('paused_remaining_ms')->nullable();

            // Analysis, computed once by Session 5 and then paced by the director.
            $table->unsignedSmallInteger('analysis_beat')->nullable();  // index of the beat currently on screen, 0-based
            $table->json('analysis_beats')->nullable();                 // the full beat sequence as data

            $table->json('settings')->nullable();                       // anything else, e.g. clocks snapshot
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('ended_at', 3)->nullable();
            $table->timestampsTz(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
