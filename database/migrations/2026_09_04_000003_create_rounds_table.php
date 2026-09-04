<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->boolean('anonymous')->default(false);   // a flag on the round, so mixed mode is possible later
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('ended_at', 3)->nullable();
            $table->timestampsTz(3);

            $table->unique(['game_session_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
