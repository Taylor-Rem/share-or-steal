<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Player> */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'device_token' => (string) Str::uuid(),
            'username' => Str::limit($this->faker->unique()->firstName(), 24, ''),
            'is_bot' => false,
            'is_admitted' => true,
            'last_seen_at' => now(),
        ];
    }

    public function bot(): static
    {
        return $this->state([
            'device_token' => null,
            'username' => config('game.bot.name'),
            'is_bot' => true,
        ]);
    }
}
