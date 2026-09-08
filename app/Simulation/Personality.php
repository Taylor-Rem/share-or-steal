<?php

namespace App\Simulation;

use Random\Randomizer;

/**
 * The scripted roster from docs/PLAN.md "Testing": every personality is a rule for the
 * next move given this round's history so far. Session 5 plays them into decision logs to
 * pin the archetype ladder down; Session 7's simulator plays them against the real server.
 *
 * `choose()` returns 'share', 'steal', or null for "does not answer" (a timeout).
 * `responseMs()` is how long the thumb takes when it does answer.
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

    /**
     * @param  list<array{me: string, them: string}>  $round  this round's revealed moves so far, from my seat
     * @param  int  $index  the decision about to be made, 1-based
     * @param  int  $roundNumber  1-based
     */
    public function choose(array $round, int $index, int $roundNumber, int $decisionsPerRound, Randomizer $rng): ?string
    {
        $last = $round === [] ? null : $round[array_key_last($round)];
        $theirs = array_column($round, 'them');
        $mine = array_column($round, 'me');

        return match ($this) {
            self::Saint => 'share',
            self::Wall => 'steal',
            self::Mirror, self::Speedster, self::DoubleTapper => $last ? $last['them'] : 'share',
            self::Grudge => in_array('steal', $theirs, true) ? 'steal' : 'share',
            // Mirror, but after one retaliation offers a share again.
            self::Diplomat => $last === null ? 'share'
                : ($last['them'] === 'share' ? 'share' : ($last['me'] === 'steal' ? 'share' : 'steal')),
            self::Backstabber => $index >= $decisionsPerRound - 1 ? 'steal' : 'share',
            self::Opportunist => $last && $last['them'] === 'share' ? 'steal' : 'share',
            self::Wildcard => $rng->getInt(0, 1) ? 'share' : 'steal',
            self::Pragmatist => (count($theirs) >= 2 && array_slice($theirs, -2) === ['steal', 'steal'])
                ? 'steal' : ($rng->getFloat(0, 1) < 0.7 ? 'share' : 'steal'),
            self::Sleeper, self::Straggler => null,
            // Drops mid-round 3 and is back for the last few decisions.
            self::Ghost => $roundNumber === 3 && $index >= 4 && $index <= 6 ? null : ($last ? $last['them'] : 'share'),
        };
    }

    public function responseMs(Randomizer $rng): int
    {
        return match ($this) {
            self::Speedster => 200,
            default => $rng->getInt(600, 3200),
        };
    }

    public function expectedArchetype(): ?string
    {
        return match ($this) {
            self::Saint => 'saint',
            self::Wall => 'wall',
            self::Mirror, self::Speedster, self::DoubleTapper, self::Ghost => 'mirror',
            self::Grudge => 'grudge',
            self::Diplomat => 'diplomat',
            self::Backstabber => 'backstabber',
            self::Opportunist => 'opportunist',
            self::Wildcard => 'wildcard',
            self::Pragmatist => 'pragmatist',
            default => null,
        };
    }
}
