<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;

/**
 * An event on `player.{id}`, private to one phone. In anonymous mode this is the
 * only place an individual result ever goes.
 */
abstract class PlayerBroadcast extends GameBroadcast
{
    /** @param array<string, mixed> $data */
    public function __construct(GameSession $session, public int $playerId, protected array $data = [])
    {
        parent::__construct($session);
    }

    public function payload(): array
    {
        return $this->data;
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [self::playerChannel($this->playerId)];
    }
}
