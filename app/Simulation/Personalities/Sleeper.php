<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Never answers. Expected: every decision a timed-out share, the still-there nudge, no Kindest. */
final class Sleeper extends Base
{
    public function choose(History $history): ?Choice
    {
        return null;
    }

    public function tapDelayMs(History $history, int $chooseMs): ?int
    {
        return null;
    }
}
