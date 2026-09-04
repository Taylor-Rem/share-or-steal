<?php

namespace Database\Factories;

use App\Enums\AwardKey;
use App\Models\Award;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Award> */
class AwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'player_id' => Player::factory(),
            'key' => $this->faker->randomElement(AwardKey::cases()),
            'place' => 1,
            'value' => null,
            'tie_break' => null,
        ];
    }
}
