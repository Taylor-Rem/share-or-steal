<?php

namespace App\Events;

/** `screen.reload` — the director asks the projector to reload. `{}` */
class ScreenReload extends ScreenBroadcast
{
    public function name(): string
    {
        return 'screen.reload';
    }
}
