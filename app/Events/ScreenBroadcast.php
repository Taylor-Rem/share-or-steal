<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;

/** An event on `screen.{code}`: control signals for the projector only. */
abstract class ScreenBroadcast extends GameBroadcast
{
    /** @param array<string, mixed> $data */
    public function __construct(GameSession $session, protected array $data = [])
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
        return [self::screenChannel($this->session)];
    }
}
