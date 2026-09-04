<?php

use App\Models\GameSession;
use App\Models\Player;

beforeEach(function () {
    config(['game.director_password' => 'secret']);
    $this->session = GameSession::factory()->create(['code' => 'ROOM']);
    $this->player = Player::factory()->create(['game_session_id' => $this->session->id, 'device_token' => 'tok-1']);
});

function authorize(string $channel, array $headers = [])
{
    // Each call is a separate HTTP request in real life; the request guard caches identity in-process.
    app('auth')->forgetGuards();

    return test()->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-'.$channel,
        'socket_id' => '1234.5678',
    ], $headers);
}

it('lets a player into the session channel and their own player channel', function () {
    authorize('session.ROOM', ['X-Device-Token' => 'tok-1'])->assertOk()->assertJsonStructure(['auth']);
    authorize('player.'.$this->player->id, ['X-Device-Token' => 'tok-1'])->assertOk();
});

it('keeps a player out of other channels', function () {
    $other = Player::factory()->create(['game_session_id' => $this->session->id]);

    authorize('player.'.$other->id, ['X-Device-Token' => 'tok-1'])->assertForbidden();
    authorize('director.ROOM', ['X-Device-Token' => 'tok-1'])->assertForbidden();
    authorize('session.ELSE', ['X-Device-Token' => 'tok-1'])->assertForbidden();
});

it('keeps a kicked player out', function () {
    $this->player->update(['kicked_at' => now()]);

    authorize('session.ROOM', ['X-Device-Token' => 'tok-1'])->assertForbidden();
});

it('lets a screen in by code, but not into director or player channels', function () {
    authorize('session.ROOM', ['X-Screen-Code' => 'room'])->assertOk();
    authorize('screen.ROOM', ['X-Screen-Code' => 'ROOM'])->assertOk();
    authorize('director.ROOM', ['X-Screen-Code' => 'ROOM'])->assertForbidden();
    authorize('player.'.$this->player->id, ['X-Screen-Code' => 'ROOM'])->assertForbidden();
    authorize('session.ROOM', ['X-Screen-Code' => 'NOPE'])->assertForbidden();
});

it('lets the director everywhere except player channels', function () {
    authorize('session.ROOM', ['X-Director-Key' => 'secret'])->assertOk();
    authorize('screen.ROOM', ['X-Director-Key' => 'secret'])->assertOk();
    authorize('director.ROOM', ['X-Director-Key' => 'secret'])->assertOk();
    authorize('player.'.$this->player->id, ['X-Director-Key' => 'secret'])->assertForbidden();
    authorize('director.ROOM', ['X-Director-Key' => 'wrong'])->assertForbidden();
});

it('rejects a request with no identity', function () {
    authorize('session.ROOM')->assertForbidden();
});
