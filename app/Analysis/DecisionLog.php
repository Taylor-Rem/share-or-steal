<?php

namespace App\Analysis;

use App\Enums\Choice;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * The decision log of one session, arranged per player: for every round they played,
 * their partner and the ordered list of moves from their seat's point of view. Nothing is
 * computed here; this is just the shape the stats read.
 *
 * history = [
 *   ['partner_id' => 7, 'partner_is_bot' => false, 'moves' => [
 *       ['i' => 1, 'me' => 'share', 'them' => 'steal', 'my_points' => 0, 'their_points' => 5,
 *        'timed_out' => false, 'their_timed_out' => false, 'response_ms' => 1240], ...
 *   ]], ...
 * ]
 */
final class DecisionLog
{
    /** @param  Collection<int, Player>  $players */
    private function __construct(
        public readonly Collection $players,
        /** @var array<int, list<array<string, mixed>>> player id => history */
        public readonly array $histories,
    ) {}

    public static function for(GameSession $session): self
    {
        $players = $session->players()->where('is_admitted', true)->orderBy('id')->get()->keyBy('id');
        $histories = $players->map(fn () => [])->all();

        $rounds = $session->rounds()->with(['pairings.decisions'])->get();
        foreach ($rounds as $round) {
            foreach ($round->pairings as $pairing) {
                $decisions = $pairing->decisions->filter->isRevealed()->sortBy('index')->values();
                if ($decisions->isEmpty()) {
                    continue;
                }
                foreach (['a', 'b'] as $seat) {
                    $other = $seat === 'a' ? 'b' : 'a';
                    $me = $pairing->{"player_{$seat}_id"};
                    $them = $pairing->{"player_{$other}_id"};
                    if (! isset($histories[$me])) {
                        continue;
                    }
                    $histories[$me][] = [
                        'round' => $round->number,
                        'partner_id' => $them,
                        'partner_is_bot' => (bool) ($players[$them]->is_bot ?? false),
                        'moves' => $decisions->map(fn ($d) => [
                            'i' => (int) $d->index,
                            'me' => $d->{"choice_$seat"} instanceof Choice ? $d->{"choice_$seat"}->value : $d->{"choice_$seat"},
                            'them' => $d->{"choice_$other"} instanceof Choice ? $d->{"choice_$other"}->value : $d->{"choice_$other"},
                            'my_points' => (int) $d->{"points_$seat"},
                            'their_points' => (int) $d->{"points_$other"},
                            'timed_out' => (bool) $d->{"timed_out_$seat"},
                            'their_timed_out' => (bool) $d->{"timed_out_$other"},
                            'response_ms' => $d->{"response_ms_$seat"} === null ? null : (int) $d->{"response_ms_$seat"},
                        ])->all(),
                    ];
                }
            }
        }

        return new self($players, $histories);
    }
}
