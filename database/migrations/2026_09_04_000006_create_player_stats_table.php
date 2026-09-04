<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per player per session, computed in one pass when round N ends (Session 5).
        // Rates are fractions 0..1 and are null when the denominator is zero.
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedSmallInteger('decisions_count')->default(0);
            $table->unsignedSmallInteger('timeouts')->default(0);
            $table->decimal('share_rate', 6, 4)->nullable();
            $table->decimal('opening_move', 6, 4)->nullable();
            $table->decimal('retaliation', 6, 4)->nullable();
            $table->decimal('forgiveness', 6, 4)->nullable();
            $table->unsignedSmallInteger('betrayals')->default(0);
            $table->unsignedSmallInteger('exploitation')->default(0);
            $table->decimal('exploitation_rate', 6, 4)->nullable();   // exploitation / steals
            $table->decimal('endgame_shift', 6, 4)->nullable();       // -1..1
            $table->decimal('predictability', 6, 4)->nullable();
            $table->decimal('partner_yield', 8, 4)->nullable();       // avg points partners earned per decision vs you
            $table->unsignedSmallInteger('sucker_count')->default(0);
            $table->unsignedSmallInteger('times_stolen_from')->default(0);
            $table->decimal('match_rate', 6, 4)->nullable();          // your move == partner's previous move
            $table->decimal('post_steal_share_rate', 6, 4)->nullable();
            $table->unsignedInteger('avg_response_ms')->nullable();
            $table->string('archetype', 24)->nullable();              // App\Enums\Archetype; null for bots
            $table->timestampsTz(3);

            $table->unique(['game_session_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
