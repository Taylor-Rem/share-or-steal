<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Submits a choice, then the opposite 50 ms later, every decision. Expected: first choice wins; the second is ignored. */
final class DoubleTapper extends Base
{
    public function choose(History $history): ?Choice
    {
        return $this->copy($history);
    }

    public function submissions(Choice $choice): array
    {
        return [$choice, $choice === Choice::Share ? Choice::Steal : Choice::Share];
    }

    public function expectedArchetype(): ?string
    {
        return 'mirror';
    }
}
