<?php

namespace App\Events;

/** `round.summary` — the round scoreboard. `{ round, is_last, ends_at, leaderboard, biggest_betrayal, most_cooperative_pair, aggregate }` */
class RoundSummary extends SessionBroadcast
{
    public function name(): string
    {
        return 'round.summary';
    }
}
