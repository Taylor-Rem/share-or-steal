<?php

use App\Enums\SessionStatus;
use App\Events\DecisionRevealed;
use App\Events\YouRevealed;
use App\Models\Decision;

beforeEach(fn () => fakeGameEvents());

it('scores a decision from the payoff matrix', function (string $a, string $b, int $pointsA, int $pointsB, string $outcomeA, string $outcomeB) {
    [$session, $players] = lobby(2, ['rounds_count' => 1, 'decisions_per_round' => 1]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    choose($players[0], $session, $a)->assertOk();
    choose($players[1], $session, $b)->assertOk();
    tickUntil($session, SessionStatus::Revealing);

    $decision = Decision::sole();
    $pairing = $decision->pairing;
    $seatOfFirst = $pairing->seatOf($players[0]);
    [$first, $second] = $seatOfFirst === 'a' ? ['a', 'b'] : ['b', 'a'];

    expect($decision->{"points_$first"})->toBe($pointsA)
        ->and($decision->{"points_$second"})->toBe($pointsB)
        ->and($decision->timed_out_a)->toBeFalse()
        ->and($decision->timed_out_b)->toBeFalse()
        ->and($players[0]->refresh()->total_points)->toBe($pointsA)
        ->and($players[1]->refresh()->total_points)->toBe($pointsB);

    $reveals = payloadsOf(YouRevealed::class)->keyBy(fn ($p, $i) => eventsOf(YouRevealed::class)[$i]->playerId);
    expect($reveals[$players[0]->id]['outcome'])->toBe($outcomeA)
        ->and($reveals[$players[0]->id]['you']['points'])->toBe($pointsA)
        ->and($reveals[$players[1]->id]['outcome'])->toBe($outcomeB);

    $aggregate = payloadsOf(DecisionRevealed::class)->first()['aggregate'];
    expect($aggregate['shares'] + $aggregate['steals'])->toBe(2);
})->with([
    'both share' => ['share', 'share', 3, 3, 'mutual_share', 'mutual_share'],
    'you share, they steal' => ['share', 'steal', 0, 5, 'betrayed', 'betrayer'],
    'you steal, they share' => ['steal', 'share', 5, 0, 'betrayer', 'betrayed'],
    'both steal' => ['steal', 'steal', 1, 1, 'mutual_steal', 'mutual_steal'],
]);

it('turns a missing choice into a flagged timeout share', function () {
    [$session, $players] = lobby(2, ['rounds_count' => 1, 'decisions_per_round' => 1]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    choose($players[0], $session, 'steal')->assertOk();
    tickUntil($session, SessionStatus::Revealing);

    $decision = Decision::sole();
    $seat = $decision->pairing->seatOf($players[1]);

    expect($decision->{"choice_$seat"}->value)->toBe(config('game.timeout_choice'))
        ->and($decision->{"timed_out_$seat"})->toBeTrue()
        ->and($decision->{"response_ms_$seat"})->toBeNull()
        ->and($players[1]->refresh()->consecutive_timeouts)->toBe(1)
        ->and($players[0]->refresh()->total_points)->toBe(5);
});
