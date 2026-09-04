<?php

namespace Database\Factories;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameSession> */
class GameSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fn () => GameSession::generateCode(),
            'mode' => SessionMode::Normal,
            'status' => SessionStatus::Lobby,
            'fast_mode' => false,
            'rounds_count' => config('game.rounds'),
            'decisions_per_round' => config('game.decisions_per_round'),
            'max_players' => config('game.max_players'),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(['mode' => SessionMode::Anonymous]);
    }

    public function fast(): static
    {
        return $this->state(['fast_mode' => true]);
    }

    public function status(SessionStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
