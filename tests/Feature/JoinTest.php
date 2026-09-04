<?php

use App\Enums\SessionStatus;
use App\Events\DirectorPlayerUpdated;
use App\Events\PlayerJoined;
use App\Models\GameSession;
use App\Models\Player;

beforeEach(fn () => fakeGameEvents());

it('creates a player and broadcasts player.joined', function () {
    $session = GameSession::factory()->create(['code' => 'ROOM']);

    $response = api()->postJson('/api/sessions/room/join', ['username' => '  Jordan ', 'device_token' => 'tok-jordan'])
        ->assertCreated()
        ->assertJsonPath('state.code', 'ROOM')
        ->assertJsonPath('state.player_count', 1)
        ->assertJsonPath('player.username', 'Jordan')
        ->assertJsonPath('player.is_bot', false)
        ->assertJsonPath('is_admitted', true)
        ->assertJsonStructure(['server_time', 'state', 'player', 'is_admitted']);

    expect(Player::sole()->device_token)->toBe('tok-jordan');

    $joined = payloadsOf(PlayerJoined::class)->sole();
    expect($joined['player']['id'])->toBe($response->json('player.id'))
        ->and($joined['player_count'])->toBe(1)
        ->and($joined['state']['status'])->toBe('lobby');
    expect(payloadsOf(DirectorPlayerUpdated::class)->sole()['is_admitted'])->toBeTrue();
});

it('returns the existing player for a repeated device token', function () {
    $session = GameSession::factory()->create(['code' => 'ROOM']);
    $first = api()->postJson('/api/sessions/ROOM/join', ['username' => 'Jordan', 'device_token' => 'tok-jordan'])->json('player.id');

    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Someone Else', 'device_token' => 'tok-jordan'])
        ->assertOk()
        ->assertJsonPath('player.id', $first)
        ->assertJsonPath('player.username', 'Jordan');

    expect($session->players()->count())->toBe(1);
    Event::assertDispatchedTimes(PlayerJoined::class, 1);
});

it('rejects a taken username, case-insensitively', function () {
    GameSession::factory()->create(['code' => 'ROOM']);
    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Jordan', 'device_token' => 'tok-1'])->assertCreated();

    api()->postJson('/api/sessions/ROOM/join', ['username' => 'jordan', 'device_token' => 'tok-2'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'username taken');
});

it('validates the username length', function () {
    GameSession::factory()->create(['code' => 'ROOM']);

    api()->postJson('/api/sessions/ROOM/join', ['username' => str_repeat('x', 25), 'device_token' => 'tok-1'])->assertUnprocessable();
    api()->postJson('/api/sessions/ROOM/join', ['username' => '', 'device_token' => 'tok-1'])->assertUnprocessable();
    api()->postJson('/api/sessions/ROOM/join', ['username' => 'ok'])->assertUnprocessable();
});

it('creates a late joiner as not admitted without announcing them to the room', function () {
    [$session] = lobby(2, ['code' => 'ROOM']);
    engine()->start($session);
    Event::fake([PlayerJoined::class, DirectorPlayerUpdated::class]);

    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Late', 'device_token' => 'tok-late'])
        ->assertCreated()
        ->assertJsonPath('is_admitted', false)
        ->assertJsonPath('state.status', 'pairing');

    Event::assertNotDispatched(PlayerJoined::class);
    Event::assertDispatched(DirectorPlayerUpdated::class, fn ($e) => $e->broadcastWith()['is_admitted'] === false);
});

it('refuses a full room', function () {
    [$session] = lobby(2, ['code' => 'ROOM', 'max_players' => 2]);

    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Third', 'device_token' => 'tok-3'])
        ->assertStatus(409)
        ->assertJsonPath('reason', 'session_full');
});

it('refuses a finished session', function () {
    GameSession::factory()->create(['code' => 'ROOM', 'status' => SessionStatus::Finished]);

    api()->postJson('/api/sessions/ROOM/join', ['username' => 'Jordan', 'device_token' => 'tok-1'])
        ->assertStatus(409)
        ->assertJsonPath('reason', 'session_finished');
});

it('hides the player in player.joined in anonymous mode', function () {
    GameSession::factory()->anonymous()->create(['code' => 'ANON']);

    api()->postJson('/api/sessions/ANON/join', ['username' => 'Jordan', 'device_token' => 'tok-1'])->assertCreated();

    $joined = payloadsOf(PlayerJoined::class)->sole();
    expect($joined['player'])->toBeNull()->and($joined['player_count'])->toBe(1);
});

it('404s an unknown code', function () {
    api()->postJson('/api/sessions/ZZZZ/join', ['username' => 'Jordan', 'device_token' => 'tok-1'])->assertNotFound();
});
