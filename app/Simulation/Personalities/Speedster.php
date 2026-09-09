<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Mirror, but answers in 200 ms. Expected: Fastest Thumb. */
final class Speedster extends Base
{
    public function choose(History $history): ?Choice
    {
        return $this->copy($history);
    }

    public function tapDelayMs(History $history, int $chooseMs): ?int
    {
        return 200;
    }

    public function expectedArchetype(): ?string
    {
        return 'mirror';
    }
}
