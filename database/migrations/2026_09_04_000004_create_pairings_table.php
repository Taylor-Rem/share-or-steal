<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('player_a_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('player_b_id')->constrained('players')->cascadeOnDelete();
            $table->string('codename_a', 32)->nullable();   // what B sees A as, in an anonymous round
            $table->string('codename_b', 32)->nullable();   // what A sees B as
            $table->integer('points_a')->default(0);        // round total cache
            $table->integer('points_b')->default(0);
            $table->timestampsTz(3);

            $table->unique(['round_id', 'player_a_id']);
            $table->unique(['round_id', 'player_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairings');
    }
};
