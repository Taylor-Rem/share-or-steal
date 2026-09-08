<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Shares through decision 8, steals 9 and 10. Expected: The Backstabber; Endgame Assassin. */
final class Backstabber extends Base
{
    public function choose(History $history): ?Choice
    {
        return $history->index >= $history->decisionsPerRound - 1 ? Choice::Steal : Choice::Share;
    }

    public function expectedArchetype(): ?string
    {
        return 'backstabber';
    }
}
