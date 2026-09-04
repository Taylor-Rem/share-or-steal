<?php

namespace App\Events;

/** `director.player_updated` — a row of the player list changed. `{ player, is_admitted, kicked, last_seen_at, consecutive_timeouts, total_points }` */
class DirectorPlayerUpdated extends DirectorBroadcast
{
    public function name(): string
    {
        return 'director.player_updated';
    }
}
