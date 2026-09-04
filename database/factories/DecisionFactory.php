<?php

namespace Database\Factories;

use App\Enums\Choice;
use App\Models\Decision;
use App\Models\Pairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Decision> */
class DecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pairing_id' => Pairing::factory(),
            'index' => 1,
        ];
    }

    /** A scored decision with both choices, points from the payoff matrix, and realistic timings. */
    public function scored(Choice $a, Choice $b, bool $timedOutA = false, bool $timedOutB = false): static
    {
        return $this->state(function (array $attributes) use ($a, $b, $timedOutA, $timedOutB) {
            $opened = now()->subSeconds(10);
            $choose = (int) config('game.durations.normal.choose');

            return [
                'choice_a' => $a,
                'choice_b' => $b,
                'points_a' => $a->pointsAgainst($b),
                'points_b' => $b->pointsAgainst($a),
                'timed_out_a' => $timedOutA,
                'timed_out_b' => $timedOutB,
                'response_ms_a' => $timedOutA ? null : $this->faker->numberBetween(300, $choose - 200),
                'response_ms_b' => $timedOutB ? null : $this->faker->numberBetween(300, $choose - 200),
                'opened_at' => $opened,
                'deadline_at' => $opened->copy()->addMilliseconds($choose),
                'revealed_at' => $opened->copy()->addMilliseconds($choose + 50),
            ];
        });
    }
}
