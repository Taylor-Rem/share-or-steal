<?php

namespace App\Events;

/** `decision.opened` — phones may choose until `deadline_at`. `{ round, decision, opened_at, deadline_at, choose_ms }` */
class DecisionOpened extends SessionBroadcast
{
    public function name(): string
    {
        return 'decision.opened';
    }
}
