<?php

namespace App\Events;

/** `you.paired` — your partner for the round. `{ round, anonymous, decisions_per_round, seat, partner }` */
class YouPaired extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.paired';
    }
}
