<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;

/**
 * `game.ping` — a test event sent by `php artisan game:ping {code}` to every
 * channel of a session, so we can prove a broadcast reaches a phone.
 */
class GamePing extends GameBroadcast
{
    /** @param array<int, int> $playerIds */
    public function __construct(GameSession $session, public string $message, public array $playerIds = [])
    {
        parent::__construct($session);
    }

    public function name(): string
    {
        return 'game.ping';
    }

    public function payload(): array
    {
        return ['message' => $this->message];
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            self::sessionChannel($this->session),
            self::screenChannel($this->session),
            self::directorChannel($this->session),
            ...array_map(fn (int $id) => self::playerChannel($id), $this->playerIds),
        ];
    }
}
