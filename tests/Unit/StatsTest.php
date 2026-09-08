<?php

use App\Analysis\Stats;

/**
 * One hand-checked round, ten decisions, from Sam's seat against Priya:
 *
 *   i:      1   2   3   4   5   6   7   8   9   10
 *   Sam:    S   S   T   S   S   T*  S   S   T   T      (* = timed out, recorded as share)
 *   Priya:  S   T   S   S   T   T   S   S   S   T
 *
 * Sam's move on 6 timed out, so it is a share for scoring and not a choice for the rates.
 */
function samsRound(): array
{
    $sam = ['share', 'share', 'steal', 'share', 'share', 'share', 'share', 'share', 'steal', 'steal'];
    $priya = ['share', 'steal', 'share', 'share', 'steal', 'steal', 'share', 'share', 'share', 'steal'];
    $payoff = ['share' => ['share' => 3, 'steal' => 0], 'steal' => ['share' => 5, 'steal' => 1]];
    $moves = [];
    foreach ($sam as $k => $me) {
        $them = $priya[$k];
        $moves[] = [
            'i' => $k + 1, 'me' => $me, 'them' => $them,
            'my_points' => $payoff[$me][$them], 'their_points' => $payoff[$them][$me],
            'timed_out' => $k === 5, 'their_timed_out' => false,
            'response_ms' => $k === 5 ? null : 1000 + $k * 100,
        ];
    }

    return [['round' => 1, 'partner_id' => 2, 'partner_is_bot' => false, 'moves' => $moves]];
}

it('computes every stat on a hand-checked round', function () {
    config(['game.analysis.endgame_from_decision' => 9, 'game.analysis.forgiveness_window' => 3]);
    $s = Stats::compute(samsRound());

    // Points: S/S 3, S/T 0, T/S 5, S/S 3, S/T 0, S*/T 0, S/S 3, S/S 3, T/S 5, T/T 1 = 23; Priya 3+5+0+3+5+5+3+3+0+1 = 28.
    expect($s['total_points'])->toBe(23)
        ->and($s['decisions_count'])->toBe(10)
        ->and($s['timeouts'])->toBe(1)
        ->and($s['shares'])->toBe(6)->and($s['steals'])->toBe(3)
        ->and($s['share_rate'])->toBe(round(6 / 9, 4))
        ->and($s['opening_move'])->toBe(1.0)
        // Stolen from at 2, 5, 6, 10. Next chosen moves: 3 -> steal, 6 -> timed out (skipped), 7 -> share; 10 has no next.
        ->and($s['retaliation'])->toBe(0.5)
        // After 2: shares at 4 (yes). After 5: window 6*,7,8 -> 7 share (yes). After 6: 7 (yes). After 10: nothing following.
        ->and($s['forgiveness'])->toBe(1.0)
        // Steals at 3 (prev 2: S/T, not mutual share; Priya stole -> no exploitation), 9 (prev 8: S/S -> betrayal + exploitation), 10 (prev 9: T/S -> exploitation).
        ->and($s['betrayals'])->toBe(1)
        ->and($s['exploitation'])->toBe(2)
        ->and($s['exploitation_rate'])->toBe(round(2 / 3, 4))
        // Early (1-8, chosen): 6 shares of 7; late (9-10): 0 of 2.
        ->and($s['early_share_rate'])->toBe(round(6 / 7, 4))
        ->and($s['late_share_rate'])->toBe(0.0)
        ->and($s['endgame_shift'])->toBe(round(0 - 6 / 7, 4))
        // Partner earned 28 over 10 decisions.
        ->and($s['partner_yield'])->toBe(2.8)
        // Chose to share and was stolen from: 2, 5 (6 was a timeout, not a choice).
        ->and($s['sucker_count'])->toBe(2)
        ->and($s['times_stolen_from'])->toBe(4)
        // Match my move to Priya's previous: 2 S=S y, 3 T=T y, 4 S=S y, 5 S=S y, 7 S vs T n, 8 S=S y, 9 T vs S n, 10 T vs S n -> 5/8.
        ->and($s['match_rate'])->toBe(0.625)
        // After the first steal against me (2): chosen moves 3..10 minus 6 -> 7 choices, shares at 4,5,7,8 -> 4/7.
        ->and($s['post_steal_share_rate'])->toBe(round(4 / 7, 4))
        // ...of which Priya had just shared before 4 S, 5 S, 8 S, 9 T, 10 T -> 3/5.
        ->and($s['olive_branch_share_rate'])->toBe(0.6)
        // Contexts (my last | their last -> my move): S|S: 3->? no wait: i2 ctx S|S->S, i3 S|T->T, i4 T|S->S, i5 S|S->S, i7 S|T->S, i8 S|S->S, i9 S|S->T, i10 T|S->T.
        // S|S: S,S,S,T (best 3 of 4); S|T: T,S (1 of 2); T|S: S,T (1 of 2) => 5/8.
        ->and($s['predictability'])->toBe(0.625)
        ->and($s['avg_response_ms'])->toBe((int) round((1000 + 1100 + 1200 + 1300 + 1400 + 1600 + 1700 + 1800 + 1900) / 9));
});

it('returns null rates for a player who never chose', function () {
    $moves = array_map(fn ($i) => ['i' => $i, 'me' => 'share', 'them' => 'steal', 'my_points' => 0, 'their_points' => 5, 'timed_out' => true, 'their_timed_out' => false, 'response_ms' => null], range(1, 3));
    $s = Stats::compute([['round' => 1, 'partner_id' => 9, 'partner_is_bot' => false, 'moves' => $moves]]);

    expect($s['share_rate'])->toBeNull()
        ->and($s['opening_move'])->toBeNull()
        ->and($s['retaliation'])->toBeNull()
        ->and($s['forgiveness'])->toBeNull()
        ->and($s['predictability'])->toBeNull()
        ->and($s['endgame_shift'])->toBeNull()
        ->and($s['avg_response_ms'])->toBeNull()
        ->and($s['exploitation_rate'])->toBeNull()
        ->and($s['timeouts'])->toBe(3)
        ->and($s['sucker_count'])->toBe(0)
        ->and($s['times_stolen_from'])->toBe(3)
        ->and($s['partner_yield'])->toBe(5.0);
});

it('handles an empty history', function () {
    $s = Stats::compute([]);
    expect($s['decisions_count'])->toBe(0)->and($s['share_rate'])->toBeNull()->and($s['partner_yield'])->toBeNull();
});
