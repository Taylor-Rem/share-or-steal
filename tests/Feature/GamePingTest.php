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

    Event::assertDispatchedTimes(GamePing::class, 1);
    $event = Event::dispatched(GamePing::class)->first()[0];

    expect(collect($event->broadcastOn())->map->name->all())->toBe([
        'private-session.PING', 'private-screen.PING', 'private-director.PING',
        'private-player.'.$players[0]->id, 'private-player.'.$players[1]->id,
    ])->and($event->broadcastAs())->toBe('game.ping');

    $payload = $event->broadcastWith();
    expect($payload['event'])->toBe('game.ping')
        ->and($payload['message'])->toBe('hello')
        ->and($payload['state']['code'])->toBe('PING')
        ->and($payload['state']['player_count'])->toBe(2)
        ->and($payload['server_time'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/');
});

it('fails cleanly for an unknown code', function () {
    $this->artisan('game:ping', ['code' => 'NOPE'])->assertFailed();
});
