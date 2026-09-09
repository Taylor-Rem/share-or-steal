<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A player's picked look: an emoji and a palette colour key (config/game.php avatars).
        Schema::table('players', function (Blueprint $table) {
            $table->string('avatar_emoji', 16)->nullable()->after('username');
            $table->string('avatar_color', 16)->nullable()->after('avatar_emoji');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['avatar_emoji', 'avatar_color']);
        });
    }
};
