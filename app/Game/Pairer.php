<?php

namespace App\Game;

/**
 * Random partners for one round. Pure: player ids in, pairs out.
 *
 * Rules, in order of how hard they are:
 *   1. Everyone gets exactly one partner (The Machine's id is just another node).
 *   2. Nobody draws The Machine twice, if that can be arranged.
 *   3. Nobody repeats a partner, when `$avoidRepeats` is on (the engine turns it on at or
 *      above `game.avoid_repeat_partners_from` players).
 *
 * The search is a backtracking perfect matching over the allowed-partner graph, tried on a
 * few random orderings. If no matching exists under a rule, that rule is relaxed, hardest
 * last: repeats are allowed before The Machine is allowed to revisit someone.
 */
final class Pairer
{
    private const ATTEMPTS = 8;

    private const BUDGET = 20_000;

    /**
     * @param  list<int>  $players  ids to pair this round, The Machine included when it plays
     * @param  array<int, list<int>>  $previousPartners  player id => ids they have already partnered
     * @return list<array{0: int, 1: int}> pairs as [seat a, seat b]
     */
    public function pair(array $players, ?int $botId, array $previousPartners, bool $avoidRepeats): array
    {
        $players = array_values(array_unique($players));

        if (count($players) % 2 !== 0) {
            throw new \InvalidArgumentException('Pairer needs an even number of players; add The Machine first.');
        }

        $botEdges = [];
        $repeatEdges = [];
        foreach ($previousPartners as $id => $partners) {
            foreach ($partners as $partner) {
                $edge = self::edge((int) $id, (int) $partner);
                if ($botId !== null && ((int) $id === $botId || (int) $partner === $botId)) {
                    $botEdges[$edge] = true;
                } else {
                    $repeatEdges[$edge] = true;
                }
            }
        }

        $tiers = $avoidRepeats
            ? [$botEdges + $repeatEdges, $botEdges, []]
            : [$botEdges, []];

        foreach ($tiers as $forbidden) {
            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                shuffle($players);
                $budget = self::BUDGET;
                $pairs = $this->match($players, $forbidden, $budget);
                if ($pairs !== null) {
                    return array_map(fn (array $pair) => random_int(0, 1) ? [$pair[1], $pair[0]] : $pair, $pairs);
                }
            }
        }

        // Unreachable: the last tier forbids nothing, so any even set matches on the first try.
        throw new \LogicException('Pairer could not build a matching.');
    }

    /**
     * @param  list<int>  $nodes
     * @param  array<string, true>  $forbidden
     * @return list<array{0: int, 1: int}>|null
     */
    private function match(array $nodes, array $forbidden, int &$budget): ?array
    {
        if ($nodes === []) {
            return [];
        }

        $first = array_shift($nodes);

        foreach ($nodes as $i => $candidate) {
            if (--$budget < 0) {
                return null;
            }
            if (isset($forbidden[self::edge($first, $candidate)])) {
                continue;
            }

            $rest = $nodes;
            unset($rest[$i]);
            $sub = $this->match(array_values($rest), $forbidden, $budget);

            if ($sub !== null) {
                $sub[] = [$first, $candidate];

                return $sub;
            }
        }

        return null;
    }

    public static function edge(int $a, int $b): string
    {
        return $a < $b ? "$a-$b" : "$b-$a";
    }
}
