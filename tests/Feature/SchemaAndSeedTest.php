<?php

use App\Enums\Archetype;
use App\Enums\SessionStatus;
use App\Models\GameSession;
use Database\Seeders\DemoSessionSeeder;

it('seeds a finished two-round demo game in the contract shape', function () {
    $this->seed(DemoSessionSeeder::class);

    $session = GameSession::where('code', 'DEMO')->firstOrFail();

    expect($session->status)->toBe(SessionStatus::Analysis)
        ->and($session->players)->toHaveCount(6)
        ->and($session->rounds)->toHaveCount(2)
        ->and($session->stats)->toHaveCount(6)
        ->and($session->awards->where('key.value', 'champion'))->toHaveCount(3)
        ->and($session->awards->where('key.value', 'kindest')->first()->player->username)->toBe('Jordan')
        ->and($session->awards->where('key.value', 'most_ruthless')->first()->player->username)->toBe('Priya');

    $decisions = $session->rounds->flatMap->pairings->flatMap->decisions;
    expect($decisions)->toHaveCount(60)
        ->and($decisions->every(fn ($d) => $d->isRevealed()))->toBeTrue()
        ->and($decisions->where('timed_out_b', true))->toHaveCount(1);

    // Cached totals agree with the decision log.
    foreach ($session->players as $player) {
        $fromLog = $decisions->sum(fn ($d) => match ($player->id) {
            $d->pairing->player_a_id => $d->points_a,
            $d->pairing->player_b_id => $d->points_b,
            default => 0,
        });
        expect($player->total_points)->toBe($fromLog);
    }

    $beats = $session->analysis_beats;
    expect($beats)->toBeArray()
        ->and($beats[0]['type'])->toBe('room_share_rate')
        ->and(collect($beats)->last()['type'])->toBe('podium')
        ->and(collect($beats)->where('type', 'archetype_reveal'))->toHaveCount(6);

    expect($session->stats->firstWhere('player.username', 'Jordan')->archetype)->toBe(Archetype::Saint);
});

it('reports state in the contract shape', function () {
    $this->seed(DemoSessionSeeder::class);

    $state = GameSession::where('code', 'DEMO')->first()->toStateArray();

    expect($state)->toHaveKeys([
        'code', 'mode', 'status', 'fast_mode', 'paused', 'rounds_count', 'decisions_per_round',
        'round', 'decision', 'analysis_beat', 'analysis_beat_count', 'phase_ends_at', 'player_count',
    ])->and($state['player_count'])->toBe(6)
        ->and($state['analysis_beat_count'])->toBeGreaterThan(10);
});

it('formats timestamps as ISO-8601 UTC with milliseconds', function () {
    expect(GameSession::iso(now()->parse('2026-09-04 17:02:11.250', 'America/Denver')))
        ->toBe('2026-09-04T23:02:11.250Z');
});
