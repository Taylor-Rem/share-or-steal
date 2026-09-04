<?php

namespace App\Analysis;

/**
 * Every per-player stat from docs/PLAN.md "Per-player stats" and CONTRACT.md § 11.1, as a
 * pure function of one player's history (see DecisionLog). Timed-out moves are not choices:
 * every rate about what a player *chose* leaves them out of both numerator and denominator.
 * Outcome counts (points, times_stolen_from) include everything, because the points were
 * real either way; sucker_count needs a chosen share, so a sleeper is never Most Betrayed.
 * Every rate is null when its denominator is zero.
 */
final class Stats
{
    /**
     * @param  list<array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public static function compute(array $history): array
    {
        $endgameFrom = (int) config('game.analysis.endgame_from_decision');
        $window = (int) config('game.analysis.forgiveness_window');

        $decisions = $timeouts = $shares = $steals = 0;
        $points = $partnerPoints = 0;
        $responses = [];
        $opening = [0, 0];      // [shares, chosen] on decision 1
        $retaliation = [0, 0];  // [stole next, times stolen from with a next choice]
        $forgiveness = [0, 0];  // [returned to share within the window, times stolen from with a following choice]
        $early = [0, 0];
        $late = [0, 0];
        $match = [0, 0];        // [my move == their previous move, moves with a previous]
        $postSteal = [0, 0];    // [shares, choices] after the first steal against me in a round
        $olive = [0, 0];        // [shares, choices] of those where the partner had just shared again
        $betrayals = $exploitation = $sucker = $stolenFrom = 0;
        $contexts = [];         // "myLast|theirLast" => ['share' => n, 'steal' => n]

        foreach ($history as $round) {
            $moves = array_values($round['moves']);
            $firstStealAgainstMe = null;

            foreach ($moves as $k => $m) {
                $chosen = ! $m['timed_out'];
                $decisions++;
                $points += $m['my_points'];
                $partnerPoints += $m['their_points'];

                if ($chosen) {
                    $m['me'] === 'share' ? $shares++ : $steals++;
                    if ($m['response_ms'] !== null) {
                        $responses[] = $m['response_ms'];
                    }
                } else {
                    $timeouts++;
                }

                if ($m['them'] === 'steal') {
                    $stolenFrom++;
                    // A sucker chose to share and got stolen from; a timeout is not a choice.
                    if ($chosen && $m['me'] === 'share') {
                        $sucker++;
                    }
                }

                if ($chosen) {
                    if ($m['i'] === 1) {
                        $opening[1]++;
                        $opening[0] += $m['me'] === 'share' ? 1 : 0;
                    }
                    $bucket = $m['i'] >= $endgameFrom ? 'late' : 'early';
                    ${$bucket}[1]++;
                    ${$bucket}[0] += $m['me'] === 'share' ? 1 : 0;

                    // The Grudge stats: once this partner has stolen from me, do I ever share
                    // again, and do I take their olive branches? A partner who never shares
                    // again (a Wall) makes me look like a grudge on the first count alone.
                    if ($firstStealAgainstMe !== null) {
                        $postSteal[1]++;
                        $postSteal[0] += $m['me'] === 'share' ? 1 : 0;
                        if ($k > 0 && $moves[$k - 1]['them'] === 'share') {
                            $olive[1]++;
                            $olive[0] += $m['me'] === 'share' ? 1 : 0;
                        }
                    }
                }

                $prev = $k > 0 ? $moves[$k - 1] : null;
                if ($prev !== null && $chosen) {
                    if ($prev['them'] === 'steal') {
                        $retaliation[1]++;
                        $retaliation[0] += $m['me'] === 'steal' ? 1 : 0;
                    }
                    if ($m['me'] === 'steal' && $prev['me'] === 'share' && $prev['them'] === 'share') {
                        $betrayals++;
                    }
                    if ($m['me'] === 'steal' && $prev['them'] === 'share') {
                        $exploitation++;
                    }
                    $match[1]++;
                    $match[0] += $m['me'] === $prev['them'] ? 1 : 0;

                    $key = $prev['me'].'|'.$prev['them'];
                    $contexts[$key][$m['me']] = ($contexts[$key][$m['me']] ?? 0) + 1;
                }

                if ($m['them'] === 'steal' && $firstStealAgainstMe === null) {
                    $firstStealAgainstMe = $k;
                }
            }

            // Forgiveness: after each steal against me, did I share again within the window?
            foreach ($moves as $k => $m) {
                if ($m['them'] !== 'steal') {
                    continue;
                }
                $following = array_filter(array_slice($moves, $k + 1, $window), fn ($n) => ! $n['timed_out']);
                if ($following === []) {
                    continue;
                }
                $forgiveness[1]++;
                foreach ($following as $n) {
                    if ($n['me'] === 'share') {
                        $forgiveness[0]++;
                        break;
                    }
                }
            }
        }

        $observed = 0;
        $predicted = 0;
        foreach ($contexts as $counts) {
            $observed += array_sum($counts);
            $predicted += max($counts);
        }

        $earlyRate = self::rate($early[0], $early[1]);
        $lateRate = self::rate($late[0], $late[1]);

        return [
            'total_points' => $points,
            'decisions_count' => $decisions,
            'timeouts' => $timeouts,
            'shares' => $shares,
            'steals' => $steals,
            'share_rate' => self::rate($shares, $shares + $steals),
            'opening_move' => self::rate($opening[0], $opening[1]),
            'retaliation' => self::rate($retaliation[0], $retaliation[1]),
            'forgiveness' => self::rate($forgiveness[0], $forgiveness[1]),
            'betrayals' => $betrayals,
            'exploitation' => $exploitation,
            'exploitation_rate' => self::rate($exploitation, $steals),
            'endgame_shift' => $earlyRate === null || $lateRate === null ? null : round($lateRate - $earlyRate, 4),
            'early_share_rate' => $earlyRate,
            'late_share_rate' => $lateRate,
            'predictability' => self::rate($predicted, $observed),
            'partner_yield' => $decisions > 0 ? round($partnerPoints / $decisions, 4) : null,
            'sucker_count' => $sucker,
            'times_stolen_from' => $stolenFrom,
            'match_rate' => self::rate($match[0], $match[1]),
            'post_steal_share_rate' => self::rate($postSteal[0], $postSteal[1]),
            'olive_branch_share_rate' => self::rate($olive[0], $olive[1]),
            'avg_response_ms' => $responses === [] ? null : (int) round(array_sum($responses) / count($responses)),
        ];
    }

    public static function rate(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : null;
    }
}
