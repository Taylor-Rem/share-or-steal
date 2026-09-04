<?php

namespace App\Events;

/** `you.revealed` — your side of a scored decision. `{ round, decision, next_at, is_last, you, partner, round_total, total_points, outcome }` */
class YouRevealed extends PlayerBroadcast
{
    public function name(): string
    {
        return 'you.revealed';
    }
}
