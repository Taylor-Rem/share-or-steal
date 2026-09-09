<?php

namespace App\Events;

/** `player.updated` — a player changed their look. `{ player: PublicPlayer }`. Not sent in anonymous mode. */
class PlayerUpdated extends SessionBroadcast
{
    public function name(): string
    {
        return 'player.updated';
    }
}
