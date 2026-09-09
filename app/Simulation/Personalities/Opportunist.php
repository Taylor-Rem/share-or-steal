<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Steals whenever the partner shared last time, shares otherwise. Expected: The Opportunist; Cold Blooded. */
final class Opportunist extends Base
{
    public function choose(History $history): ?Choice
    {
        $last = $history->last();

        return $last !== null && $last['them'] === 'share' ? Choice::Steal : Choice::Share;
    }

    public function expectedArchetype(): ?string
    {
        return 'opportunist';
    }
}
