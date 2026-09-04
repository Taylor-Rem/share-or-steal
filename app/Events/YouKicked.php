<?php

namespace App\Events;

/** `you.kicked` — the director removed you. `{ reason }` */
class YouKicked extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.kicked';
    }
}
