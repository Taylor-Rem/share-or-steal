<?php

namespace Database\Factories;

use App\Enums\Archetype;
use App\Models\GameSession;
use App\Models\Player;
use App\Models\PlayerStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerStat> */
class PlayerStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'player_id' => Player::factory(),
            'total_points' => $this->faker->numberBetween(80, 180),
            'decisions_count' => 50,
            'timeouts' => 0,
            'share_rate' => $this->faker->randomFloat(4, 0.2, 0.95),
            'opening_move' => $this->faker->randomFloat(4, 0, 1),
            'retaliation' => $this->faker->randomFloat(4, 0, 1),
            'forgiveness' => $this->faker->randomFloat(4, 0, 1),
            'betrayals' => $this->faker->numberBetween(0, 6),
            'exploitation' => $this->faker->numberBetween(0, 10),
            'exploitation_rate' => $this->faker->randomFloat(4, 0, 1),
            'endgame_shift' => $this->faker->randomFloat(4, -0.6, 0.2),
            'predictability' => $this->faker->randomFloat(4, 0.3, 0.95),
            'partner_yield' => $this->faker->randomFloat(4, 1, 3.5),
            'sucker_count' => $this->faker->numberBetween(0, 12),
            'times_stolen_from' => $this->faker->numberBetween(0, 15),
            'match_rate' => $this->faker->randomFloat(4, 0.3, 0.95),
            'post_steal_share_rate' => $this->faker->randomFloat(4, 0, 1),
            'avg_response_ms' => $this->faker->numberBetween(600, 3500),
            'archetype' => $this->faker->randomElement(Archetype::cases()),
        ];
    }
}
