<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;

/** Sends every choice 100 ms after the deadline. Expected: all rejected, all recorded as timeouts. */
final class Straggler extends Base
{
    public function choose(History $history): ?Choice
    {
        return $this->copy($history);
    }

    public function tapDelayMs(History $history, int $chooseMs): ?int
    {
        return $chooseMs + 100;
    }
}
