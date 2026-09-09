<?php

namespace App\Simulation;

use Random\Randomizer;

/**
 * What a personality sees when it chooses: this round's revealed moves so far from its own
 * seat, where it is in the round and the game, and a seeded source of randomness.
 */
final class History
{
    /**
     * @param  list<array{me: string, them: string}>  $round  revealed moves this round, oldest first
     */
    public function __construct(
        public readonly array $round,
        public readonly int $index,
        public readonly int $roundNumber,
        public readonly int $decisionsPerRound,
        public readonly Randomizer $rng,
    ) {}

    /** @return array{me: string, them: string}|null */
    public function last(): ?array
    {
        return $this->round === [] ? null : $this->round[array_key_last($this->round)];
    }

    /** @return list<string> the partner's moves this round */
    public function theirs(): array
    {
        return array_column($this->round, 'them');
    }

    /** @return list<string> my moves this round */
    public function mine(): array
    {
        return array_column($this->round, 'me');
    }
}
