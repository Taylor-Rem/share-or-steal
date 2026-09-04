<?php

namespace App\Events;

/** `you.nudged` — still there? `{ consecutive_timeouts }` */
class YouNudged extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.nudged';
    }
}
