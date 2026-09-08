<?php

namespace App\Analysis;

use App\Enums\Archetype;
use App\Enums\AwardKey;
use App\Models\Award;
use App\Models\GameSession;
use App\Models\PlayerStat;
use Illuminate\Support\Collection;

/**
 * The analysis beat sequence (CONTRACT.md § 11.3) as data: each beat a `screen` half for
 * `analysis.beat` and an optional `private` map of player id => `you.card` payload.
 * Normal and anonymous sessions get different sequences; the director's Next just walks
 * the array.
 */
final class Beats
{
    /**
     * @param  Collection<int, PlayerStat>  $stats  human rows with `player` loaded, ranked
     * @param  Collection<int, Award>  $awards  with `player` loaded
     * @param  array<string, mixed>  $room  ['share_rate' => ?float, 'series' => list, 'total_points' => int, 'max_cooperative_points' => int]
     * @param  array<string, mixed>|null  $comparison  ['screen' => ..., 'private' => [...]] or null
     * @return list<array<string, mixed>>
     */
    public static function build(GameSession $session, Collection $stats, Collection $awards, array $room, ?array $comparison): array
    {
        $anonymous = $session->isAnonymous();
        $beats = [];

        $beats[] = ['type' => 'room_share_rate', 'screen' => [
            'share_rate' => $room['share_rate'],
            'total_points' => $room['total_points'],
            'max_cooperative_points' => $room['max_cooperative_points'],
        ]];
        $beats[] = ['type' => 'share_rate_by_decision', 'screen' => ['series' => $room['series']]];

        // Archetypes: one card flip per player from the back of the field to the champion,
        // or one beat that sends every card to the phones in anonymous mode.
        $revealOrder = $stats->sortBy([['rank', 'desc'], ['player_id', 'asc']])->values();
        if ($anonymous) {
            $beats[] = [
                'type' => 'archetype_cards',
                'screen' => ['count' => $stats->count()],
                'private' => $stats->mapWithKeys(fn (PlayerStat $s) => [$s->player_id => $s->toContractArray()])->all(),
            ];
        } else {
            foreach ($revealOrder as $s) {
                $beats[] = ['type' => 'archetype_reveal', 'screen' => $s->toContractArray(), 'private' => [$s->player_id => $s->toContractArray()]];
            }
        }

        $beats[] = ['type' => 'archetype_census', 'screen' => [
            'counts' => $stats->filter(fn ($s) => $s->archetype !== null)
                ->groupBy(fn ($s) => $s->archetype->value)
                ->map(fn ($group, $key) => ['archetype' => Archetype::from($key)->toArray(), 'count' => $group->count()])
                ->sortByDesc('count')->values()->all(),
        ]];

        $beats[] = ['type' => 'stat_leaders', 'screen' => ['leaders' => self::leaders($stats, $anonymous)]];

        foreach (AwardKey::cases() as $key) {
            if ($key === AwardKey::Champion) {
                continue;
            }
            $award = $awards->first(fn (Award $a) => $a->key === $key);
            if (! $award) {
                continue;
            }
            $full = ['award' => $award->toContractArray()];
            $beats[] = [
                'type' => 'award',
                'screen' => $anonymous ? ['award' => $key->toArray()] : $full,
                'private' => [$award->player_id => $full],
            ];
        }

        $podium = $awards->filter(fn (Award $a) => $a->key === AwardKey::Champion)->sortBy('place')->values();
        $totals = $stats->pluck('total_points')->sort()->values();
        $beats[] = [
            'type' => 'podium',
            'screen' => $anonymous
                ? [
                    'distribution' => ['min' => $totals->min(), 'max' => $totals->max(), 'median' => $totals->median()],
                    'top_scores' => $podium->map(fn (Award $a) => (int) $a->value)->all(),
                ]
                : [
                    'places' => $podium->map(fn (Award $a) => [
                        'place' => (int) $a->place,
                        'player' => $a->player->toPublicArray(),
                        'total_points' => (int) $a->value,
                        'archetype' => $stats->firstWhere('player_id', $a->player_id)?->archetype?->toArray(),
                    ])->all(),
                ],
            'private' => $stats->mapWithKeys(fn (PlayerStat $s) => [$s->player_id => [
                'rank' => $s->rank,
                'total_points' => $s->total_points,
                'archetype' => $s->archetype?->toArray(),
                'awards' => $awards->where('player_id', $s->player_id)->map(fn (Award $a) => $a->key->toArray())->values()->all(),
            ]])->all(),
        ];

        if ($comparison !== null) {
            $beats[] = ['type' => 'comparison', 'screen' => $comparison['screen'], 'private' => $comparison['private']];
        }

        return $beats;
    }

    /**
     * One leader per stat that has one. Normal mode names the player; anonymous mode does not.
     *
     * @return list<array<string, mixed>>
     */
    private static function leaders(Collection $stats, bool $anonymous): array
    {
        $board = [
            ['share_rate', 'Share rate', 'highest', 'pct'],
            ['opening_move', 'Opening move', 'highest', 'pct'],
            ['forgiveness', 'Forgiveness', 'highest', 'pct'],
            ['retaliation', 'Retaliation', 'highest', 'pct'],
            ['betrayals', 'Betrayals', 'highest', 'count'],
            ['exploitation', 'Exploitation', 'highest', 'count'],
            ['endgame_shift', 'Endgame shift', 'lowest', 'signed_pct'],
            ['predictability', 'Predictability', 'lowest', 'pct'],
            ['partner_yield', 'Partner yield', 'highest', 'pts'],
            ['sucker_count', 'Sucker count', 'highest', 'count'],
            ['times_stolen_from', 'Times stolen from', 'highest', 'count'],
            ['avg_response_ms', 'Fastest thumb', 'lowest', 'ms'],
        ];

        $out = [];
        foreach ($board as [$stat, $label, $direction, $format]) {
            $candidates = $stats->filter(fn ($s) => $s->$stat !== null);
            if ($candidates->isEmpty()) {
                continue;
            }
            $leader = ($direction === 'lowest' ? $candidates->sortBy([[$stat, 'asc'], ['total_points', 'desc']]) : $candidates->sortBy([[$stat, 'desc'], ['total_points', 'desc']]))->first();
            $value = $leader->$stat;
            if (in_array($format, ['count'], true) && (float) $value <= 0) {
                continue;
            }
            $entry = ['stat' => $stat, 'label' => $label, 'value' => $value, 'value_label' => self::format($value, $format)];
            if (! $anonymous) {
                $entry = ['stat' => $stat, 'label' => $label, 'player' => $leader->player->toPublicArray()] + $entry;
            }
            $out[] = $entry;
        }

        return $out;
    }

    public static function format(float|int|null $value, string $format): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($format) {
            'pct' => round($value * 100).'%',
            'signed_pct' => ($value > 0 ? '+' : '').round($value * 100).'%',
            'pts' => number_format((float) $value, 1).' pts',
            'ms' => (int) $value.' ms',
            default => (string) (int) $value,
        };
    }
}
