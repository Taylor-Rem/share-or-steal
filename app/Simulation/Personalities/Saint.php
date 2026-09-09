<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Always shares. Expected: The Saint; one wins Kindest, one Most Betrayed. */
final class Saint extends Base
{
    public function choose(History $history): ?Choice
    {
        return Choice::Share;
    }

    public function expectedArchetype(): ?string
    {
        return 'saint';
    }
}
