<?php

namespace App\Analysis;

use App\Enums\Archetype;
use App\Enums\AwardKey;
use App\Enums\Choice;
use App\Game\Ranking;
use App\Models\Award;
use App\Models\GameSession;
use App\Models\PlayerStat;
use Illuminate\Support\Collection;

/**
 * Enough analysis for a game to finish end to end: total points, rank, a handful of
 * counts, and a two-beat sequence (`room_share_rate`, `podium`). Everyone is a
 * Pragmatist. Session 5 replaces this with the real stats, ladder and awards.
 */
class StubAnalyzer implements Analyzer
{
    public function analyze(GameSession $session): void
    {
        $session->stats()->delete();
        $session->awards()->delete();

        $players = $session->players()->where('is_bot', false)->where('is_admitted', true)->orderBy('id')->get();
        $decisions = $session->rounds()->with('pairings.decisions')->get()
            ->flatMap(fn ($round) => $round->pairings->flatMap(fn ($pairing) => $pairing->decisions->map(fn ($d) => [$pairing, $d])));

        $rows = [];
        foreach ($players as $player) {
            $mine = $decisions->filter(fn ($pair) => $pair[0]->seatOf($player) !== null && $pair[1]->isRevealed());
            $points = 0;
            $shares = $steals = $timeouts = 0;
            $responses = [];
            foreach ($mine as [$pairing, $decision]) {
                $seat = $pairing->seatOf($player);
                $points += (int) $decision->{"points_$seat"};
                if ($decision->{"timed_out_$seat"}) {
                    $timeouts++;
                } else {
                    $decision->{"choice_$seat"} === Choice::Share ? $shares++ : $steals++;
                    $responses[] = (int) $decision->{"response_ms_$seat"};
                }
            }
            $rows[$player->id] = [
                'game_session_id' => $session->id,
                'player_id' => $player->id,
                'total_points' => $points,
                'decisions_count' => $mine->count(),
                'timeouts' => $timeouts,
                'share_rate' => $shares + $steals > 0 ? round($shares / ($shares + $steals), 4) : null,
                'avg_response_ms' => $responses !== [] ? (int) round(array_sum($responses) / count($responses)) : null,
                'archetype' => Archetype::Pragmatist,
            ];
        }

        $ranks = Ranking::ranks(array_map(fn ($row) => $row['total_points'], $rows));
        foreach ($rows as $id => $row) {
            PlayerStat::create($row + ['rank' => $ranks[$id]]);
        }

        $stats = $session->stats()->with('player')->orderBy('rank')->orderBy('player_id')->get();
        $places = (int) config('game.awards.podium_places');
        foreach ($stats->take($places)->values() as $i => $stat) {
            Award::create([
                'game_session_id' => $session->id,
                'player_id' => $stat->player_id,
                'key' => AwardKey::Champion,
                'place' => $i + 1,
                'value' => $stat->total_points,
                'tie_break' => null,
            ]);
        }

        $session->analysis_beats = $this->beats($session, $stats);
        $session->save();
    }

    /**
     * @param  Collection<int, PlayerStat>  $stats
     * @return list<array<string, mixed>>
     */
    private function beats(GameSession $session, $stats): array
    {
        $shares = $stats->sum(fn ($s) => $s->share_rate === null ? 0 : $s->share_rate * ($s->decisions_count - $s->timeouts));
        $chosen = $stats->sum(fn ($s) => $s->decisions_count - $s->timeouts);
        $awards = $session->awards()->with('player')->orderBy('place')->get();

        $beats = [[
            'type' => 'room_share_rate',
            'screen' => [
                'share_rate' => $chosen > 0 ? round($shares / $chosen, 4) : null,
                'total_points' => $stats->sum('total_points'),
                'max_cooperative_points' => $stats->count() * $session->rounds_count * $session->decisions_per_round
                    * (int) config('game.analysis.cooperative_points_per_decision'),
            ],
        ]];

        $private = [];
        foreach ($stats as $stat) {
            $private[$stat->player_id] = [
                'rank' => $stat->rank,
                'total_points' => $stat->total_points,
                'archetype' => $stat->archetype?->toArray(),
                'awards' => $awards->where('player_id', $stat->player_id)->map(fn ($a) => $a->key->toArray())->values()->all(),
            ];
        }

        $totals = $stats->pluck('total_points')->sort()->values();
        $screen = $session->isAnonymous()
            ? [
                'distribution' => [
                    'min' => $totals->min(),
                    'max' => $totals->max(),
                    'median' => $totals->median(),
                ],
                'top_scores' => $totals->sortDesc()->take((int) config('game.awards.podium_places'))->values()->all(),
            ]
            : [
                'places' => $awards->map(fn ($a) => [
                    'place' => $a->place,
                    'player' => $a->player->toPublicArray(),
                    'total_points' => (int) $a->value,
                    'archetype' => $stats->firstWhere('player_id', $a->player_id)?->archetype?->toArray(),
                ])->values()->all(),
            ];

        $beats[] = ['type' => 'podium', 'screen' => $screen, 'private' => $private];

        return $beats;
    }
}
