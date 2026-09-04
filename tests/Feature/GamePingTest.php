<?php

use App\Events\GamePing;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Facades\Event;

it('broadcasts game.ping to every channel of the session', function () {
    Event::fake([GamePing::class]);
    $session = GameSession::factory()->create(['code' => 'PING']);
    $players = Player::factory()->count(2)->create(['game_session_id' => $session->id]);
    Player::factory()->bot()->create(['game_session_id' => $session->id]);

    $this->artisan('game:ping', ['code' => 'ping', '--message' => 'hello'])
        ->expectsOutputToContain('2 player channel(s)')
        ->assertSuccessful();

    Event::assertDispatched(GamePing::class, function (GamePing $event) use ($players) {
        $channels = collect($event->broadcastOn())->map->name->all();
        $payload = $event->broadcastWith();

        return $channels === [
            'private-session.PING', 'private-screen.PING', 'private-director.PING',
            'private-player.'.$players[0]->id, 'private-player.'.$players[1]->id,
        ]
            && $event->broadcastAs() === 'game.ping'
            && $payload['event'] === 'game.ping'
            && $payload['message'] === 'hello'
            && $payload['state']['code'] === 'PING'
            && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $payload['server_time']);
    });
});

it('fails cleanly for an unknown code', function () {
    $this->artisan('game:ping', ['code' => 'NOPE'])->assertFailed();
});
