<?php

namespace App\Events;

/** `player.left` — a player was kicked. `{ player_id, reason, player_count }` */
class PlayerLeft extends SessionBroadcast
{
    public function name(): string
    {
        return 'player.left';
    }
}
