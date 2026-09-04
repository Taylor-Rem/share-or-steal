<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;

/**
 * An event on `session.{code}`, the public channel phones and the screen both hear.
 * Concrete events name themselves; the payload is the contract's event fields.
 */
abstract class SessionBroadcast extends GameBroadcast
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
        return [self::sessionChannel($this->session)];
    }
}
