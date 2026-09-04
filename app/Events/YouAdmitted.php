<?php

namespace App\Events;

/** `you.admitted` — the director let you in. `{}` */
class YouAdmitted extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.admitted';
    }
}
