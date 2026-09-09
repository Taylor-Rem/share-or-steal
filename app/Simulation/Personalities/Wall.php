<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Always steals. Expected: The Wall; Most Ruthless. */
final class Wall extends Base
{
    public function choose(History $history): ?Choice
    {
        return Choice::Steal;
    }

    public function expectedArchetype(): ?string
    {
        return 'wall';
    }
}
