<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Coin flip every decision. Expected: The Wildcard; Unreadable. */
final class Wildcard extends Base
{
    public function choose(History $history): ?Choice
    {
        return $history->rng->getInt(0, 1) ? Choice::Share : Choice::Steal;
    }

    public function expectedArchetype(): ?string
    {
        return 'wildcard';
    }
}
