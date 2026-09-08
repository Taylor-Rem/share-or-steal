<?php

use App\Enums\SessionStatus;
use App\Models\GameSession;

beforeEach(fn () => fakeGameEvents());

it('returns the lobby snapshot', function () {
    [$session, $players] = lobby(2, ['code' => 'ROOM']);

    asPlayer($players[0])->getJson('/api/sessions/room/me')
        ->assertOk()
        ->assertJsonPath('state.status', 'lobby')
        ->assertJsonPath('player.id', $players[0]->id)
        ->assertJsonPath('is_admitted', true)
        ->assertJsonPath('kicked', false)
        ->assertJsonPath('total_points', 0)
        ->assertJsonPath('round', null)
        ->assertJsonPath('decision', null)
        ->assertJsonPath('last_reveal', null)
        ->assertJsonPath('card', null)
        ->assertJsonStructure(['server_time', 'state', 'player', 'is_admitted', 'kicked', 'total_points', 'round', 'decision', 'last_reveal', 'card']);
});

it('returns the open decision, the choice once made, and the last reveal', function () {
    [$session, $players] = lobby(2, ['code' => 'ROOM']);
    engine()->start($session);

    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')
        ->assertJsonPath('round.number', 1)
        ->assertJsonPath('round.partner.id', $players[1]->id)
        ->assertJsonPath('round.partner.display_name', 'Player2')
        ->assertJsonPath('round.partner.is_codename', false)
        ->assertJsonPath('round.round_total.you', 0)
        ->assertJsonPath('decision', null);

    tickUntil($session, SessionStatus::Deciding);
    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')
        ->assertJsonPath('decision.index', 1)
        ->assertJsonPath('decision.chosen', false)
        ->assertJsonPath('decision.your_choice', null)
        ->assertJsonPath('decision.deadline_at', $session->refresh()->toStateArray()['phase_ends_at'])
        ->assertJsonPath('last_reveal', null);

    choose($players[0], $session, 'steal')->assertOk();
    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')
        ->assertJsonPath('decision.chosen', true)
        ->assertJsonPath('decision.your_choice', 'steal');

    tickUntil($session, SessionStatus::Revealing);
    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')
        ->assertJsonPath('total_points', 5)
        ->assertJsonPath('last_reveal.decision', 1)
        ->assertJsonPath('last_reveal.outcome', 'betrayer')
        ->assertJsonPath('last_reveal.you.points', 5)
        ->assertJsonPath('last_reveal.partner.timed_out', true)
        ->assertJsonPath('last_reveal.next_at', $session->refresh()->toStateArray()['phase_ends_at'])
        ->assertJsonPath('round.round_total.you', 5);
});

it('shows the codename in an anonymous round', function () {
    [$session, $players] = lobby(2, ['code' => 'ANON', 'mode' => 'anonymous']);
    engine()->start($session);

    $me = asPlayer($players[0])->getJson('/api/sessions/ANON/me')->assertJsonPath('round.partner.is_codename', true)->json('round.partner.display_name');
    expect($me)->not->toBe('Player2')->toMatch('/^\w+ \w+$/');
});

it('returns the latest card in the analysis', function () {
    [$session, $players] = lobby(2, ['code' => 'ROOM', 'rounds_count' => 1, 'decisions_per_round' => 1]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Analysis);

    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')->assertJsonPath('card', null);

    $last = count($session->refresh()->analysis_beats) - 1;
    while ($session->refresh()->analysis_beat < $last) {
        engine()->next($session); // to the podium: everyone has a card
    }
    asPlayer($players[0])->getJson('/api/sessions/ROOM/me')
        ->assertJsonPath('card.type', 'podium')
        ->assertJsonPath('card.index', $last)
        ->assertJsonPath('card.payload.rank', 1);
});

it('serves a late joiner and a kicked player, but not a stranger', function () {
    [$session, $players] = lobby(2, ['code' => 'ROOM']);
    engine()->start($session);
    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Late', 'device_token' => 'tok-late']);
    engine()->kick($session, $players[1]);

    asPlayer('tok-late')->getJson('/api/sessions/ROOM/me')->assertOk()->assertJsonPath('is_admitted', false)->assertJsonPath('round', null);
    asPlayer($players[1])->getJson('/api/sessions/ROOM/me')->assertOk()->assertJsonPath('kicked', true);
    api()->getJson('/api/sessions/ROOM/me')->assertUnauthorized();
    api(['X-Device-Token' => 'nobody'])->getJson('/api/sessions/ROOM/me')->assertUnauthorized();

    GameSession::factory()->create(['code' => 'ELSE']);
    asPlayer($players[0])->getJson('/api/sessions/ELSE/me')->assertUnauthorized();
});
