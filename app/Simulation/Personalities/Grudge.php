<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Shares until stolen from once, then steals for the rest of the round. Expected: The Grudge. */
final class Grudge extends Base
{
    public function choose(History $history): ?Choice
    {
        return in_array('steal', $history->theirs(), true) ? Choice::Steal : Choice::Share;
    }

    public function expectedArchetype(): ?string
    {
        return 'grudge';
    }
}
