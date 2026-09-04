<?php

use App\Enums\SessionStatus;
use App\Models\Decision;

beforeEach(fn () => fakeGameEvents());

function post(mixed $player, mixed $session, array $overrides = [])
{
    $session->refresh();

    return asPlayer($player)->postJson("/api/sessions/{$session->code}/choice", $overrides + [
        'choice' => 'steal',
        'round' => $session->current_round,
        'decision' => $session->current_decision,
    ]);
}

it('accepts a first choice and measures response_ms on the server', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    advanceClock(320);

    post($players[0], $session, ['choice' => 'share'])
        ->assertOk()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('choice', 'share')
        ->assertJsonPath('response_ms', 320)
        ->assertJsonStructure(['server_time']);

    $decision = Decision::sole();
    $seat = $decision->pairing->seatOf($players[0]);
    expect($decision->{"choice_$seat"}->value)->toBe('share')
        ->and($decision->{"response_ms_$seat"})->toBe(320)
        ->and($players[0]->refresh()->last_seen_at)->not->toBeNull();
});

it('rejects with not_admitted for a late joiner', function () {
    [$session] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    api()->postJson("/api/sessions/{$session->code}/join", ['username' => 'Late', 'device_token' => 'tok-late'])->assertCreated();

    post('tok-late', $session)->assertStatus(409)->assertJsonPath('accepted', false)->assertJsonPath('reason', 'not_admitted');
});

it('rejects with not_admitted for a kicked player', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    engine()->kick($session, $players[0]);

    post($players[0], $session)->assertStatus(409)->assertJsonPath('reason', 'not_admitted');
});

it('rejects with paused', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    engine()->pause($session);

    post($players[0], $session)->assertStatus(409)->assertJsonPath('reason', 'paused');
});

it('rejects with not_deciding outside a decision', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    expect($session->refresh()->status)->toBe(SessionStatus::Pairing);

    post($players[0], $session, ['round' => 1, 'decision' => 1])->assertStatus(409)->assertJsonPath('reason', 'not_deciding');
});

it('rejects with wrong_decision when the body names another decision', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    post($players[0], $session, ['decision' => 2])->assertStatus(409)->assertJsonPath('reason', 'wrong_decision');
    post($players[0], $session, ['round' => 2])->assertStatus(409)->assertJsonPath('reason', 'wrong_decision');
});

it('rejects with not_in_pairing for a player admitted mid-round', function () {
    [$session] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    $late = api()->postJson("/api/sessions/{$session->code}/join", ['username' => 'Late', 'device_token' => 'tok-late'])->json('player.id');
    engine()->admit($session, $session->players()->findOrFail($late));

    post('tok-late', $session)->assertStatus(409)->assertJsonPath('reason', 'not_in_pairing');
});

it('rejects with deadline_passed when the choice arrives late', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    advanceClock(config('game.durations.fast.choose') + 100);

    post($players[0], $session)->assertStatus(409)->assertJsonPath('reason', 'deadline_passed');
    expect(Decision::sole()->choice_a)->toBeNull();
});

it('rejects a second choice with already_chosen and keeps the first', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    post($players[0], $session, ['choice' => 'share'])->assertOk();
    post($players[0], $session, ['choice' => 'steal'])->assertStatus(409)->assertJsonPath('reason', 'already_chosen');

    $decision = Decision::sole();
    expect($decision->{'choice_'.$decision->pairing->seatOf($players[0])}->value)->toBe('share');
});

it('validates the body', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    post($players[0], $session, ['choice' => 'maybe'])->assertUnprocessable();
    asPlayer($players[0])->postJson("/api/sessions/{$session->code}/choice", ['choice' => 'share'])->assertUnprocessable();
});

it('needs a player identity for this session', function () {
    [$session, $players] = lobby(2);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    api()->postJson("/api/sessions/{$session->code}/choice", ['choice' => 'share', 'round' => 1, 'decision' => 1])->assertUnauthorized();
    api(['X-Device-Token' => 'nobody'])->postJson("/api/sessions/{$session->code}/choice", ['choice' => 'share', 'round' => 1, 'decision' => 1])->assertUnauthorized();
});
