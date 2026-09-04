<?php

namespace App\Events;

/** `you.round_summary` — your round. `{ round, is_last, round_points, total_points, rank, player_count, partner, shares, steals, stolen_from }` */
class YouRoundSummary extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.round_summary';
    }
}
