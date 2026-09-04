<?php

use App\Enums\SessionStatus;
use App\Events\DecisionRevealed;
use App\Events\RoundSummary;
use App\Models\Player;

beforeEach(fn () => fakeGameEvents());

/** Reverb and Pusher refuse a message over 10 KB; the biggest events must clear it with room to spare. */
it('keeps the biggest broadcasts under the message size limit with a full room', function () {
    [$session, $players] = lobby(30, ['rounds_count' => 1, 'decisions_per_round' => 3]);
    Player::query()->update(['username' => str_repeat('W', 24)]); // longest names allowed
    foreach ($players as $i => $p) {
        $p->update(['username' => str_repeat('W', 22).sprintf('%02d', $i)]);
    }
    engine()->start($session);

    for ($d = 1; $d <= 3; $d++) {
        tickUntil($session, fn ($s) => $s->status === SessionStatus::Deciding && $s->current_decision === $d);
        foreach ($players as $i => $p) {
            // Alternate so every kind of outcome, betrayal and comeback shows up.
            choose($p, $session, ($i + $d) % 3 === 0 ? 'share' : 'steal');
        }
    }
    tickUntil($session, SessionStatus::RoundSummary);

    $limit = 9_000;
    foreach (payloadsOf(DecisionRevealed::class) as $payload) {
        expect(strlen(json_encode($payload)))->toBeLessThan($limit)
            ->and($payload['leaderboard'])->toHaveCount(config('game.leaderboard_size'))
            ->and(count($payload['moments']))->toBeLessThanOrEqual(config('game.moments.max_per_decision'))
            ->and($payload['results'])->toHaveCount(15);
    }
    $summary = payloadsOf(RoundSummary::class)->sole();
    expect(strlen(json_encode($summary)))->toBeLessThan($limit)
        ->and($summary['leaderboard'])->toHaveCount(30);
});
