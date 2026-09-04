<?php

namespace App\Events;

/** `game.started` — the lobby locked. `{ rounds_count, decisions_per_round, player_count, has_bot }` */
class GameStarted extends SessionBroadcast
{
    public function name(): string
    {
        return 'game.started';
    }
}
