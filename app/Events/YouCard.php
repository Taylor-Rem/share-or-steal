<?php

namespace App\Events;

/** `you.card` — the private half of an analysis beat. `{ index, type, payload }` */
class YouCard extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.card';
    }
}
