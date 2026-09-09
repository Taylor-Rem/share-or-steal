<?php

namespace App\Simulation;

use Random\Randomizer;

/**
 * The scripted roster from docs/PLAN.md "Testing", by name. Each case is one Strategy in
 * App\Simulation\Personalities; this enum is the index the analysis tests and the
 * simulator share. `choose()` here is the array-based convenience the tests use.
 */
enum Personality: string
{
    case Saint = 'saint';
    case Wall = 'wall';
    case Mirror = 'mirror';
    case Grudge = 'grudge';
    case Diplomat = 'diplomat';
    case Backstabber = 'backstabber';
    case Opportunist = 'opportunist';
    case Wildcard = 'wildcard';
    case Pragmatist = 'pragmatist';
    case Sleeper = 'sleeper';
    case Ghost = 'ghost';
    case Straggler = 'straggler';
    case Speedster = 'speedster';
    case DoubleTapper = 'double_tapper';

    /** The thirty-player roster and what each one should become. */
    public const ROSTER = [
        ['saint', 2], ['wall', 2], ['mirror', 4], ['grudge', 2], ['diplomat', 2], ['backstabber', 2],
        ['opportunist', 3], ['wildcard', 2], ['pragmatist', 5], ['sleeper', 2], ['ghost', 1],
        ['straggler', 1], ['speedster', 1], ['double_tapper', 1],
    ];

    public function strategy(): Strategy
    {
        return match ($this) {
            self::Saint => new Personalities\Saint,
            self::Wall => new Personalities\Wall,
            self::Mirror => new Personalities\Mirror,
            self::Grudge => new Personalities\Grudge,
            self::Diplomat => new Personalities\Diplomat,
            self::Backstabber => new Personalities\Backstabber,
            self::Opportunist => new Personalities\Opportunist,
            self::Wildcard => new Personalities\Wildcard,
            self::Pragmatist => new Personalities\Pragmatist,
            self::Sleeper => new Personalities\Sleeper,
            self::Ghost => new Personalities\Ghost,
            self::Straggler => new Personalities\Straggler,
            self::Speedster => new Personalities\Speedster,
            self::DoubleTapper => new Personalities\DoubleTapper,
        };
    }

    /**
     * The move a scripted decision log records: null when the phone would not answer in
     * time (never taps, taps after the window, or is offline), as the server would record it.
     *
     * @param  list<array{me: string, them: string}>  $round  this round's revealed moves so far, from my seat
     * @param  int  $index  the decision about to be made, 1-based
     */
    public function choose(array $round, int $index, int $roundNumber, int $decisionsPerRound, Randomizer $rng): ?string
    {
        $strategy = $this->strategy();
        $offline = $strategy->offline();
        if ($offline !== null && $roundNumber === $offline[0] && $index >= $offline[1] && $index <= $offline[2]) {
            return null;
        }
        $history = new History($round, $index, $roundNumber, $decisionsPerRound, $rng);
        $chooseMs = (int) config('game.durations.normal.choose');
        $delay = $strategy->tapDelayMs($history, $chooseMs);
        if ($delay === null || $delay > $chooseMs) {
            return null;
        }

        return $strategy->choose($history)?->value;
    }

    /** A plausible tap time for a scripted decision log. */
    public function responseMs(Randomizer $rng): int
    {
        return match ($this) {
            self::Speedster => 200,
            default => $rng->getInt(600, 3200),
        };
    }

    public function expectedArchetype(): ?string
    {
        return $this->strategy()->expectedArchetype();
    }

    /**
     * The roster trimmed to `$count` players: Pragmatists go first, then the largest
     * groups shrink, so every personality stays represented as long as it can.
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function roster(int $count): array
    {
        $roster = self::ROSTER;
        while (array_sum(array_column($roster, 1)) > $count) {
            $kinds = array_column($roster, 0);
            $counts = array_column($roster, 1);
            $pragmatist = array_search('pragmatist', $kinds, true);
            if ($pragmatist !== false && $counts[$pragmatist] > 1) {
                $roster[$pragmatist][1]--;

                continue;
            }
            $largest = array_search(max($counts), $counts, true);
            if ($counts[$largest] > 1) {
                $roster[$largest][1]--;
            } else {
                array_pop($roster);
            }
        }

        return $roster;
    }
}
