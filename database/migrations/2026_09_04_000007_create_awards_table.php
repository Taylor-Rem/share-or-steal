<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('key', 32);                          // App\Enums\AwardKey
            $table->unsignedSmallInteger('place')->default(1);  // 1 for every award; champion also records 2 and 3 for the podium
            $table->decimal('value', 10, 4)->nullable();        // the winning stat value
            $table->string('tie_break', 16)->nullable();        // null | points | coin_flip
            $table->timestampsTz(3);

            $table->unique(['game_session_id', 'key', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
