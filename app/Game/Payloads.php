<?php

namespace App\Game;

use App\Enums\Choice;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The CONTRACT.md § 8 shapes, built from models. Shared by the engine (events) and
 * the `me` endpoint (reconnect snapshot) so a phone sees one shape everywhere.
 */
final class Payloads
{
    public static function otherSeat(string $seat): string
    {
        return $seat === 'a' ? 'b' : 'a';
    }

    /** The Partner shape as seen from `$seat`. The Machine keeps its name even in an anonymous round. */
    public static function partner(Pairing $pairing, string $seat, bool $anonymous): array
    {
        $other = self::otherSeat($seat);
        $player = $pairing->{$other === 'a' ? 'playerA' : 'playerB'};
        $codename = $anonymous && ! $player->is_bot;

        return [
            'id' => $player->id,
            'display_name' => $codename ? (string) $pairing->{"codename_$other"} : $player->username,
            'is_bot' => (bool) $player->is_bot,
            'is_codename' => $codename,
        ];
    }

    public static function seatResult(Decision $decision, Pairing $pairing, string $seat): array
    {
        $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};

        return [
            'player_id' => $player->id,
            'choice' => $decision->{"choice_$seat"}->value,
            'points' => (int) $decision->{"points_$seat"},
            'round_total' => (int) $pairing->{"points_$seat"},
            'total' => (int) $player->total_points,
            'timed_out' => (bool) $decision->{"timed_out_$seat"},
        ];
    }

    public static function outcome(Choice $you, Choice $them): string
    {
        return match (true) {
            $you === Choice::Share && $them === Choice::Share => 'mutual_share',
            $you === Choice::Steal && $them === Choice::Steal => 'mutual_steal',
            $you === Choice::Share => 'betrayed',
            default => 'betrayer',
        };
    }

    /** The `you.revealed` payload for `$seat` of a scored decision. */
    public static function youRevealed(Decision $decision, Pairing $pairing, string $seat, int $round, ?Carbon $nextAt, bool $isLast): array
    {
        $other = self::otherSeat($seat);
        $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};

        return [
            'round' => $round,
            'decision' => (int) $decision->index,
            'next_at' => GameSession::iso($nextAt),
            'is_last' => $isLast,
            'you' => [
                'choice' => $decision->{"choice_$seat"}->value,
                'points' => (int) $decision->{"points_$seat"},
                'timed_out' => (bool) $decision->{"timed_out_$seat"},
                'response_ms' => $decision->{"response_ms_$seat"} === null ? null : (int) $decision->{"response_ms_$seat"},
            ],
            'partner' => [
                'choice' => $decision->{"choice_$other"}->value,
                'points' => (int) $decision->{"points_$other"},
                'timed_out' => (bool) $decision->{"timed_out_$other"},
            ],
            'round_total' => ['you' => (int) $pairing->{"points_$seat"}, 'partner' => (int) $pairing->{"points_$other"}],
            'total_points' => (int) $player->total_points,
            'outcome' => self::outcome($decision->{"choice_$seat"}, $decision->{"choice_$other"}),
        ];
    }

    /**
     * Room-wide counts for one decision index across the given pairings (bot seats included).
     *
     * @param  Collection<int, Decision>  $decisions
     */
    public static function aggregate(Collection $decisions): array
    {
        $shares = $steals = $mutualShare = $mutualSteal = $betrayals = 0;
        foreach ($decisions as $d) {
            foreach (['a', 'b'] as $seat) {
                $d->{"choice_$seat"} === Choice::Share ? $shares++ : $steals++;
            }
            match (self::outcome($d->choice_a, $d->choice_b)) {
                'mutual_share' => $mutualShare++,
                'mutual_steal' => $mutualSteal++,
                default => $betrayals++,
            };
        }

        return [
            'shares' => $shares,
            'steals' => $steals,
            'mutual_share' => $mutualShare,
            'mutual_steal' => $mutualSteal,
            'betrayals' => $betrayals,
            'share_rate' => $shares + $steals > 0 ? round($shares / ($shares + $steals), 4) : null,
        ];
    }

    /**
     * LeaderboardEntry[] for the humans in the game, with movement since the previous round.
     *
     * @param  Collection<int, Player>  $players  the participants to rank
     * @param  Collection<int, Pairing>  $pairings  this round's pairings, with round totals up to date
     * @return list<array<string, mixed>>
     */
    public static function leaderboard(Collection $players, Collection $pairings, Round $round): array
    {
        $roundPoints = [];
        foreach ($pairings as $pairing) {
            $roundPoints[$pairing->player_a_id] = (int) $pairing->points_a;
            $roundPoints[$pairing->player_b_id] = (int) $pairing->points_b;
        }

        $totals = $players->mapWithKeys(fn (Player $p) => [$p->id => (int) $p->total_points])->all();
        $previous = $players->mapWithKeys(fn (Player $p) => [$p->id => (int) $p->total_points - ($roundPoints[$p->id] ?? 0)])->all();
        $ranks = Ranking::ranks($totals);
        $previousRanks = Ranking::ranks($previous);

        return $players
            ->sortBy([fn (Player $x, Player $y) => $ranks[$x->id] <=> $ranks[$y->id] ?: strcasecmp($x->username, $y->username) ?: $x->id <=> $y->id])
            ->values()
            ->map(fn (Player $p) => [
                'rank' => $ranks[$p->id],
                'player' => $p->toPublicArray(),
                'total_points' => $totals[$p->id],
                'round_points' => $roundPoints[$p->id] ?? 0,
                'movement' => $round->number <= 1 ? 0 : $previousRanks[$p->id] - $ranks[$p->id],
            ])
            ->all();
    }
}
