<?php

namespace App\Simulation;

use App\Enums\Choice;

/**
 * One scripted player from the plan's roster: how it chooses, and how it behaves as a
 * phone (when it taps, whether it taps twice, whether it drops off). The analysis tests
 * use choose(); the simulator uses all of it.
 */
interface Strategy
{
    /** The next move, or null for "does not answer" (a timeout). */
    public function choose(History $history): ?Choice;

    /**
     * When the phone taps, in ms after `decision.opened`. Null means never; a value past
     * `$chooseMs` is a deliberately late tap.
     */
    public function tapDelayMs(History $history, int $chooseMs): ?int;

    /**
     * Submissions for one decision: usually [$choice]; the double-tapper adds a second.
     *
     * @return list<Choice>
     */
    public function submissions(Choice $choice): array;

    /** [round, first decision, last decision] the phone is offline for, or null. */
    public function offline(): ?array;

    /** What the analysis should call this player, or null when the plan makes no promise. */
    public function expectedArchetype(): ?string;
}
