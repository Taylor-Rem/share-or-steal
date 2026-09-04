<?php

namespace Database\Factories;

use App\Models\GameSession;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Round> */
class RoundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'number' => 1,
            'anonymous' => false,
        ];
    }
}
