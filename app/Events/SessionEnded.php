<?php

namespace App\Events;

/** `session.ended` — the game is over. `{ reason }` is `completed` or `ended_by_director`. */
class SessionEnded extends SessionBroadcast
{
    public function name(): string
    {
        return 'session.ended';
    }
}
