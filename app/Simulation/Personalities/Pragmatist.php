<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Shares 70% of the time, steals if the partner has stolen twice in a row. Expected: The Pragmatist, the largest group. */
final class Pragmatist extends Base
{
    public function choose(History $history): ?Choice
    {
        $theirs = $history->theirs();
        if (count($theirs) >= 2 && array_slice($theirs, -2) === ['steal', 'steal']) {
            return Choice::Steal;
        }

        return $history->rng->getFloat(0, 1) < 0.7 ? Choice::Share : Choice::Steal;
    }

    public function expectedArchetype(): ?string
    {
        return 'pragmatist';
    }
}
