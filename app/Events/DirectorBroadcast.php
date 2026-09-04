<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;

/** An event on `director.{code}`: things the room shouldn't see. */
abstract class DirectorBroadcast extends GameBroadcast
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
        return [self::directorChannel($this->session)];
    }
}
