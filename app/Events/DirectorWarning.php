<?php

namespace App\Events;

/** `director.warning` — something the director should glance at. `{ message }` */
class DirectorWarning extends DirectorBroadcast
{
    public function name(): string
    {
        return 'director.warning';
    }
}
