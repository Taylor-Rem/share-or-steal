<?php

namespace App\Events;

/** `session.paused` — the director paused. `{ paused_from, remaining_ms }` */
class SessionPaused extends SessionBroadcast
{
    public function name(): string
    {
        return 'session.paused';
    }
}
