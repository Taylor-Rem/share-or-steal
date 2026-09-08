<?php

use App\Models\GameSession;
use App\Models\Player;
use Database\Seeders\DemoSessionSeeder;

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
        ->assertJsonPath('beat', null)
        ->assertJsonStructure(['server_time', 'state', 'players', 'beat']);
});

it('carries the beat on screen during the analysis', function () {
    $this->seed(DemoSessionSeeder::class);
    GameSession::where('code', 'DEMO')->update(['analysis_beat' => 2]);

    $this->getJson('/api/sessions/DEMO')
        ->assertOk()
        ->assertJsonPath('beat.index', 2)
        ->assertJsonPath('beat.count', 20)
        ->assertJsonPath('beat.type', 'archetype_reveal')
        ->assertJsonStructure(['beat' => ['index', 'count', 'type', 'payload' => ['player', 'archetype']]]);
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
