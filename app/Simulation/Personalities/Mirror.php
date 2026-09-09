<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Shares first, then copies the partner's last move. Expected: The Mirror; likely Champion in a mixed room. */
final class Mirror extends Base
{
    public function choose(History $history): ?Choice
    {
        return $this->copy($history);
    }

    public function expectedArchetype(): ?string
    {
        return 'mirror';
    }
}
