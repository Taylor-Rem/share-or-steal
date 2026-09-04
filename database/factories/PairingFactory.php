<?php

namespace Database\Factories;

use App\Models\Pairing;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pairing> */
class PairingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'player_a_id' => Player::factory(),
            'player_b_id' => Player::factory(),
        ];
    }
}
