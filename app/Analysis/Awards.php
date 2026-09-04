<?php

namespace App\Analysis;

use App\Enums\AwardKey;
use Random\Randomizer;

/**
 * Who wins what (docs/PLAN.md "Awards", CONTRACT.md § 11.2). Each award ranks one stat in
 * one direction over the eligible players; ties break on total points, then on a coin flip
 * that is recorded so the screen can show it. The Machine and kicked players never win.
 *
 * Count awards are withheld when the best count is zero (nobody was Cold Blooded if
 * nobody betrayed anyone), and Endgame Assassin when nobody's share rate dropped. A
 * player who never chose anything (every decision a timeout) wins nothing.
 */
final class Awards
{
    public function __construct(private ?Randomizer $rng = null) {}

    /**
     * @param  array<int, array<string, mixed>>  $stats  player id => Stats::compute() + ['eligible' => bool]
     * @return list<array{key: AwardKey, player_id: int, place: int, value: float|int|null, tie_break: string|null}>
     */
    public function pick(array $stats): array
    {
        $rules = config('game.awards');
        $eligible = array_filter($stats, fn ($s) => $s['eligible'] && ! ($rules['all_timeouts_win_nothing'] && $s['shares'] + $s['steals'] === 0));
        $out = [];

        foreach (AwardKey::cases() as $key) {
            if ($key === AwardKey::Champion) {
                foreach ($this->podium($eligible, (int) $rules['podium_places']) as $place => $winner) {
                    $out[] = ['key' => $key, 'player_id' => $winner['id'], 'place' => $place + 1, 'value' => $winner['value'], 'tie_break' => $winner['tie_break']];
                }

                continue;
            }

            $candidates = array_filter($eligible, fn ($s) => $this->qualifies($key, $s, $rules) && $s[$key->stat()] !== null);
            if ($candidates === []) {
                continue;
            }
            $best = $this->best($candidates, $key->stat(), $key->direction() === 'lowest');
            if ($this->withheld($key, $best['value'])) {
                continue;
            }
            $out[] = ['key' => $key, 'player_id' => $best['id'], 'place' => 1, 'value' => $best['value'], 'tie_break' => $best['tie_break']];
        }

        return $out;
    }

    private function qualifies(AwardKey $key, array $s, array $rules): bool
    {
        return match ($key) {
            AwardKey::MostForgiving => $s['times_stolen_from'] >= (int) $rules['most_forgiving_min_times_stolen_from'],
            AwardKey::Kindest => ! ($rules['kindest_excludes_all_timeouts'] && $s['shares'] === 0),
            default => true,
        };
    }

    private function withheld(AwardKey $key, float|int|null $value): bool
    {
        return match ($key) {
            AwardKey::MostBetrayed, AwardKey::ColdBlooded => (float) $value <= 0,
            AwardKey::EndgameAssassin => (float) $value >= 0,
            default => false,
        };
    }

    /**
     * The best of the candidates on one stat: value, then points, then a coin flip.
     *
     * @return array{id: int, value: float|int|null, tie_break: string|null}
     */
    private function best(array $candidates, string $stat, bool $lowest): array
    {
        $ordered = $this->order($candidates, $stat, $lowest);

        return $this->settle($ordered, $stat);
    }

    /** Sort candidates by stat (direction) then points, keeping ids. */
    private function order(array $candidates, string $stat, bool $lowest): array
    {
        uksort($candidates, function ($a, $b) use ($candidates, $stat, $lowest) {
            $va = $candidates[$a][$stat];
            $vb = $candidates[$b][$stat];
            if ($va != $vb) {
                return $lowest ? $va <=> $vb : $vb <=> $va;
            }

            return $candidates[$b]['total_points'] <=> $candidates[$a]['total_points'] ?: $a <=> $b;
        });

        return $candidates;
    }

    /** Given an ordered list, decide the winner among those tied at the top and name the tie-break. */
    private function settle(array $ordered, string $stat): array
    {
        $ids = array_keys($ordered);
        $top = $ordered[$ids[0]];
        $tiedOnStat = array_filter($ids, fn ($id) => $ordered[$id][$stat] == $top[$stat]);
        if (count($tiedOnStat) === 1) {
            return ['id' => $ids[0], 'value' => $top[$stat], 'tie_break' => null];
        }
        $tiedOnPoints = array_filter($tiedOnStat, fn ($id) => $ordered[$id]['total_points'] === $top['total_points']);
        if (count($tiedOnPoints) === 1) {
            return ['id' => $ids[0], 'value' => $top[$stat], 'tie_break' => 'points'];
        }
        $tiedOnPoints = array_values($tiedOnPoints);
        $winner = $tiedOnPoints[$this->rng()->getInt(0, count($tiedOnPoints) - 1)];

        return ['id' => $winner, 'value' => $top[$stat], 'tie_break' => 'coin_flip'];
    }

    /**
     * Places 1..n by total points. Equal points at a boundary are settled by a coin flip.
     *
     * @return list<array{id: int, value: int, tie_break: string|null}>
     */
    private function podium(array $eligible, int $places): array
    {
        $pool = $eligible;
        $out = [];
        while ($pool !== [] && count($out) < $places) {
            $ordered = $this->order($pool, 'total_points', false);
            $ids = array_keys($ordered);
            $top = $ordered[$ids[0]]['total_points'];
            $tied = array_values(array_filter($ids, fn ($id) => $ordered[$id]['total_points'] === $top));
            $winner = count($tied) === 1 ? $tied[0] : $tied[$this->rng()->getInt(0, count($tied) - 1)];
            $out[] = ['id' => $winner, 'value' => $top, 'tie_break' => count($tied) === 1 ? null : 'coin_flip'];
            unset($pool[$winner]);
        }

        return $out;
    }

    private function rng(): Randomizer
    {
        return $this->rng ??= new Randomizer;
    }
}
