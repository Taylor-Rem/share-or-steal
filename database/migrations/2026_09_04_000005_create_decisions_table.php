<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per decision per pairing. Everything in the analysis is derived from this table.
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pairing_id')->constrained('pairings')->cascadeOnDelete();
            $table->unsignedSmallInteger('index');           // 1..decisions_per_round
            $table->string('choice_a', 8)->nullable();       // App\Enums\Choice, null until scored
            $table->string('choice_b', 8)->nullable();
            $table->unsignedSmallInteger('points_a')->nullable();
            $table->unsignedSmallInteger('points_b')->nullable();
            $table->boolean('timed_out_a')->default(false);
            $table->boolean('timed_out_b')->default(false);
            $table->unsignedInteger('response_ms_a')->nullable();  // opened_at -> choice received
            $table->unsignedInteger('response_ms_b')->nullable();
            $table->timestampTz('opened_at', 3)->nullable();
            $table->timestampTz('deadline_at', 3)->nullable();
            $table->timestampTz('revealed_at', 3)->nullable();
            $table->timestampsTz(3);

            $table->unique(['pairing_id', 'index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
