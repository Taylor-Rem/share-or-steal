<?php

use App\Enums\SessionStatus;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\ScreenReload;
use App\Events\SessionEnded;
use App\Events\SessionPaused;
use App\Events\SessionResumed;
use App\Events\YouAdmitted;
use App\Events\YouKicked;
use App\Models\Decision;
use App\Models\GameSession;

beforeEach(fn () => fakeGameEvents());

it('validates the password on login', function () {
    api()->postJson('/api/director/login', ['password' => config('game.director_password')])->assertOk()->assertExactJson([]);
    api()->postJson('/api/director/login', ['password' => 'nope'])->assertUnauthorized();
    api()->postJson('/api/director/login', [])->assertUnprocessable();
});

it('requires the director key on every director route', function () {
    GameSession::factory()->create(['code' => 'ROOM']);

    api()->getJson('/api/director/sessions')->assertUnauthorized();
    api(['X-Director-Key' => 'wrong'])->postJson('/api/director/sessions/ROOM/start')->assertUnauthorized();
    api(['X-Screen-Code' => 'ROOM'])->postJson('/api/director/sessions/ROOM/start')->assertUnauthorized();
});

it('creates a session with defaults and returns the urls', function () {
    $response = asDirector()->postJson('/api/director/sessions', [])
        ->assertCreated()
        ->assertJsonPath('state.status', 'lobby')
        ->assertJsonPath('state.mode', 'normal')
        ->assertJsonPath('state.rounds_count', config('game.rounds'))
        ->assertJsonPath('state.decisions_per_round', config('game.decisions_per_round'))
        ->assertJsonPath('state.fast_mode', false)
        ->assertJsonStructure(['state', 'urls' => ['join', 'play', 'screen', 'director']]);

    $code = $response->json('state.code');
    expect($code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/')
        ->and($response->json('urls.play'))->toEndWith("/play/$code")
        ->and($response->json('urls.screen'))->toEndWith("/screen/$code")
        ->and(GameSession::where('code', $code)->sole()->max_players)->toBe(config('game.max_players'));
});

it('creates a fast anonymous session with custom structure', function () {
    asDirector()->postJson('/api/director/sessions', ['mode' => 'anonymous', 'rounds_count' => 2, 'decisions_per_round' => 3, 'fast_mode' => true, 'max_players' => 12])
        ->assertCreated()
        ->assertJsonPath('state.mode', 'anonymous')
        ->assertJsonPath('state.rounds_count', 2)
        ->assertJsonPath('state.decisions_per_round', 3)
        ->assertJsonPath('state.fast_mode', true);

    asDirector()->postJson('/api/director/sessions', ['mode' => 'secret'])->assertUnprocessable();
});

it('lists sessions newest first as summaries', function () {
    GameSession::factory()->create(['code' => 'AAAA']);
    GameSession::factory()->create(['code' => 'BBBB']);

    asDirector()->getJson('/api/director/sessions')
        ->assertOk()
        ->assertJsonPath('sessions.0.code', 'BBBB')
        ->assertJsonPath('sessions.1.code', 'AAAA')
        ->assertJsonStructure(['sessions' => [['code', 'status', 'player_count', 'created_at', 'started_at', 'ended_at']]]);
});

it('shows a session with its player list and rounds', function () {
    [$session, $players] = lobby(3, ['code' => 'ROOM']);
    engine()->start($session);

    asDirector()->getJson('/api/director/sessions/room')
        ->assertOk()
        ->assertJsonPath('state.status', 'pairing')
        ->assertJsonCount(4, 'players') // three humans plus The Machine
        ->assertJsonPath('players.0.is_admitted', true)
        ->assertJsonPath('players.0.kicked', false)
        ->assertJsonPath('players.0.consecutive_timeouts', 0)
        ->assertJsonPath('players.3.is_bot', true)
        ->assertJsonCount(1, 'rounds')
        ->assertJsonPath('rounds.0.number', 1)
        ->assertJsonStructure(['server_time', 'state', 'players' => [['id', 'username', 'is_bot', 'is_admitted', 'kicked', 'last_seen_at', 'consecutive_timeouts', 'total_points']], 'rounds' => [['number', 'anonymous', 'started_at', 'ended_at']]]);
});

it('starts a session and refuses to start twice or with too few players', function () {
    $session = GameSession::factory()->create(['code' => 'ROOM']);

    asDirector()->postJson('/api/director/sessions/ROOM/start')->assertStatus(409)->assertJsonPath('reason', 'not_enough_players');

    [$session] = lobby(2, ['code' => 'FULL']);
    asDirector()->postJson('/api/director/sessions/FULL/start')->assertOk()->assertJsonPath('state.status', 'pairing')->assertJsonPath('state.round', 1);
    asDirector()->postJson('/api/director/sessions/FULL/start')->assertStatus(409)->assertJsonPath('reason', 'already_started');
});

it('pauses and resumes with the remaining time preserved', function () {
    [$session, $players] = lobby(2, ['code' => 'ROOM']);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);
    advanceClock(400);

    $remaining = config('game.durations.fast.choose') - 400;
    asDirector()->postJson('/api/director/sessions/ROOM/pause')->assertOk()->assertJsonPath('state.paused', true);
    $paused = payloadsOf(SessionPaused::class)->sole();
    expect($paused['paused_from'])->toBe('deciding')->and($paused['remaining_ms'])->toBe($remaining);

    // The clock leaves a paused session alone, however long it sleeps.
    advanceClock(10_000);
    engine()->tick();
    expect($session->refresh()->status)->toBe(SessionStatus::Deciding);

    asDirector()->postJson('/api/director/sessions/ROOM/resume')->assertOk()->assertJsonPath('state.paused', false);
    $resumed = payloadsOf(SessionResumed::class)->sole();
    expect($resumed['status'])->toBe('deciding')
        ->and($resumed['phase_ends_at'])->toBe(GameSession::iso(now()->addMilliseconds($remaining)))
        ->and(Decision::sole()->deadline_at->valueOf())->toBe(now()->addMilliseconds($remaining)->valueOf());

    // A choice inside the restored window is accepted; the phase ends on time.
    choose($players[0], $session, 'share')->assertOk();
    advanceClock($remaining - 50);
    engine()->tick();
    expect($session->refresh()->status)->toBe(SessionStatus::Deciding);
    advanceClock(100);
    engine()->tick();
    expect($session->refresh()->status)->toBe(SessionStatus::Revealing);

    asDirector()->postJson('/api/director/sessions/ROOM/resume')->assertStatus(409)->assertJsonPath('reason', 'not_paused');
});

it('accepts a pause in the lobby as a no-op beyond the broadcast', function () {
    GameSession::factory()->create(['code' => 'ROOM']);
    asDirector()->postJson('/api/director/sessions/ROOM/pause')->assertOk()->assertJsonPath('state.paused', true)->assertJsonPath('state.status', 'lobby');
    asDirector()->postJson('/api/director/sessions/ROOM/resume')->assertOk()->assertJsonPath('state.paused', false);
});

it('refuses next outside the analysis and ends a session on demand', function () {
    [$session] = lobby(2, ['code' => 'ROOM']);
    asDirector()->postJson('/api/director/sessions/ROOM/next')->assertStatus(409)->assertJsonPath('reason', 'not_in_analysis');

    engine()->start($session);
    asDirector()->postJson('/api/director/sessions/ROOM/end')->assertOk()->assertJsonPath('state.status', 'finished')->assertJsonPath('state.phase_ends_at', null);
    expect(payloadsOf(SessionEnded::class)->sole()['reason'])->toBe('ended_by_director')
        ->and($session->refresh()->ended_at)->not->toBeNull();

    // Ending twice is harmless and silent.
    asDirector()->postJson('/api/director/sessions/ROOM/end')->assertOk();
    Event::assertDispatchedTimes(SessionEnded::class, 1);
});

it('admits a late joiner, who plays from the next round', function () {
    [$session] = lobby(2, ['code' => 'ROOM', 'rounds_count' => 2, 'decisions_per_round' => 1]);
    engine()->start($session);
    $late = api()->postJson('/api/sessions/ROOM/join', ['username' => 'Late', 'device_token' => 'tok-late'])->json('player.id');

    asDirector()->postJson("/api/director/sessions/ROOM/players/$late/admit")
        ->assertOk()
        ->assertJsonPath('player.id', $late)
        ->assertJsonPath('player.is_admitted', true);

    expect(payloadsOf(PlayerJoined::class)->last()['player']['id'])->toBe($late);
    Event::assertDispatched(YouAdmitted::class, fn ($e) => $e->playerId === $late);

    tickUntil($session, fn (GameSession $s) => $s->current_round === 2);
    $round2 = $session->rounds()->where('number', 2)->sole();
    expect($round2->pairings()->count())->toBe(2) // three humans plus The Machine
        ->and($round2->pairings()->where(fn ($q) => $q->where('player_a_id', $late)->orWhere('player_b_id', $late))->exists())->toBeTrue();
});

it('refuses to admit past max_players', function () {
    [$session] = lobby(2, ['code' => 'ROOM', 'max_players' => 3]);
    engine()->start($session);
    $late = api()->postJson('/api/sessions/ROOM/join', ['username' => 'Late', 'device_token' => 'tok-late'])->json('player.id');
    $session->update(['max_players' => 2]);

    asDirector()->postJson("/api/director/sessions/ROOM/players/$late/admit")->assertStatus(409)->assertJsonPath('reason', 'session_full');
});

it('kicks a player, whose remaining decisions time out and who is excluded next round', function () {
    [$session, $players] = lobby(4, ['code' => 'ROOM', 'rounds_count' => 2, 'decisions_per_round' => 1]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Deciding);

    asDirector()->postJson("/api/director/sessions/ROOM/players/{$players[0]->id}/kick")
        ->assertOk()
        ->assertJsonPath('player.kicked', true);

    expect(payloadsOf(PlayerLeft::class)->sole())->toMatchArray(['player_id' => $players[0]->id, 'reason' => 'kicked', 'player_count' => 3]);
    Event::assertDispatched(YouKicked::class, fn ($e) => $e->playerId === $players[0]->id);

    tickUntil($session, SessionStatus::Revealing);
    $decision = Decision::whereHas('pairing', fn ($q) => $q->where('player_a_id', $players[0]->id)->orWhere('player_b_id', $players[0]->id))->sole();
    expect($decision->{'timed_out_'.$decision->pairing->seatOf($players[0])})->toBeTrue();

    tickUntil($session, fn (GameSession $s) => $s->current_round === 2);
    $round2 = $session->rounds()->where('number', 2)->sole();
    expect($round2->pairings()->where(fn ($q) => $q->where('player_a_id', $players[0]->id)->orWhere('player_b_id', $players[0]->id))->exists())->toBeFalse()
        ->and($round2->pairings()->count())->toBe(2); // three humans plus The Machine

    asPlayer($players[0])->postJson('/api/broadcasting/auth', ['channel_name' => 'private-session.ROOM', 'socket_id' => '1.1'])->assertForbidden();
});

it('asks the screen to reload', function () {
    GameSession::factory()->create(['code' => 'ROOM']);
    asDirector()->postJson('/api/director/sessions/ROOM/screen/reload')->assertOk()->assertExactJson([]);
    expect(eventsOf(ScreenReload::class)->sole()->broadcastOn()[0]->name)->toBe('private-screen.ROOM');
});

it('serves the computed analysis', function () {
    [$session] = lobby(2, ['code' => 'ROOM', 'rounds_count' => 1, 'decisions_per_round' => 1]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Analysis);

    asDirector()->getJson('/api/director/sessions/ROOM/analysis')
        ->assertOk()
        ->assertJsonPath('state.status', 'analysis')
        ->assertJsonCount(2, 'stats')
        ->assertJsonPath('stats.0.rank', 1)
        ->assertJsonCount(0, 'awards') // nobody chose anything, so nobody won anything
        ->assertJsonPath('stats.0.archetype.key', 'pragmatist')
        ->assertJsonPath('beats.0.type', 'room_share_rate')
        ->assertJsonStructure(['stats' => [['player', 'total_points', 'rank', 'share_rate', 'archetype']], 'awards', 'beats']);
});
