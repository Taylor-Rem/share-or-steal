<?php

use App\Models\GameSession;
use App\Models\Player;

it('returns the public snapshot for a session', function () {
    $session = GameSession::factory()->create(['code' => 'ABCD']);
    Player::factory()->count(3)->create(['game_session_id' => $session->id]);

    $this->getJson('/api/sessions/abcd')
        ->assertOk()
        ->assertJsonPath('state.code', 'ABCD')
        ->assertJsonPath('state.status', 'lobby')
        ->assertJsonPath('state.player_count', 3)
        ->assertJsonCount(3, 'players')
        ->assertJsonMissingPath('players.0.device_token')
        ->assertJsonStructure(['server_time', 'state', 'players']);
});

it('hides player names in anonymous mode', function () {
    $session = GameSession::factory()->anonymous()->create(['code' => 'ANON']);
    Player::factory()->count(2)->create(['game_session_id' => $session->id]);

    $this->getJson('/api/sessions/ANON')
        ->assertOk()
        ->assertJsonPath('state.player_count', 2)
        ->assertJsonCount(0, 'players');
});

it('404s an unknown code', function () {
    $this->getJson('/api/sessions/ZZZZ')->assertNotFound();
});
