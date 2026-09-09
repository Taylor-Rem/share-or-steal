<?php

namespace App\Simulation\Personalities;

use App\Enums\Choice;
use App\Simulation\History;
use App\Simulation\Strategy;

/** A normal thumb: taps somewhere in the first 45% of the window, once, and stays online. */
abstract class Base implements Strategy
{
    public function tapDelayMs(History $history, int $chooseMs): ?int
    {
        return $history->rng->getInt(150, max(150, (int) ($chooseMs * 0.45)));
    }

    public function submissions(Choice $choice): array
    {
        return [$choice];
    }

    public function offline(): ?array
    {
        return null;
    }

    public function expectedArchetype(): ?string
    {
        return null;
    }

    /** Tit-for-tat: share first, then copy the partner's last move. */
    protected function copy(History $history): Choice
    {
        $last = $history->last();

        return $last === null ? Choice::Share : Choice::from($last['them']);
    }
}
