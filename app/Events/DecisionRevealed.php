<?php

namespace App\Events;

/** `decision.revealed` — a decision was scored. `{ round, decision, next_at, is_last, results, aggregate, moments, leaderboard }` */
class DecisionRevealed extends SessionBroadcast
{
    public function name(): string
    {
        return 'decision.revealed';
    }
}
