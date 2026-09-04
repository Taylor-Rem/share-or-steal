<?php

namespace App\Events;

/** `session.resumed` — the director resumed. `{ status, phase_ends_at }` */
class SessionResumed extends SessionBroadcast
{
    public function name(): string
    {
        return 'session.resumed';
    }
}
