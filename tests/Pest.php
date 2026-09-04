<?php

use App\Enums\SessionStatus;
use App\Events\GameBroadcast;
use App\Game\Engine;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers for engine tests
|--------------------------------------------------------------------------
*/

/** Fake every game broadcast so nothing tries to reach Reverb; payloads stay inspectable. */
function fakeGameEvents(): void
{
    $classes = collect(glob(app_path('Events/*.php')))
        ->map(fn (string $file) => 'App\\Events\\'.basename($file, '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, GameBroadcast::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();

    Event::fake($classes);
}

/** The broadcast payloads (envelope included) of every dispatched event of a class, in order. */
function payloadsOf(string $class): Collection
{
    return Event::dispatched($class)->map(fn (array $args) => $args[0]->broadcastWith())->values();
}

/** The dispatched events themselves, for channel assertions. */
function eventsOf(string $class): Collection
{
    return Event::dispatched($class)->map(fn (array $args) => $args[0])->values();
}

function engine(): Engine
{
    return app(Engine::class);
}

/**
 * A request with identity headers. The `game` guard caches the resolved identity per
 * process, so forget it first: every call here is a separate HTTP request in real life.
 */
function api(array $headers = []): TestCase
{
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withHeaders($headers);
}

function asDirector(): TestCase
{
    return api(['X-Director-Key' => config('game.director_password')]);
}

function asPlayer(Player|string $player, ?string $code = null): TestCase
{
    $token = $player instanceof Player ? $player->device_token : $player;

    return api(array_filter(['X-Device-Token' => $token, 'X-Session-Code' => $code]));
}

/**
 * A lobby with `$count` human players, device tokens tok-1..tok-N.
 *
 * @return array{0: GameSession, 1: Collection<int, Player>}
 */
function lobby(int $count = 4, array $attributes = []): array
{
    $session = GameSession::factory()->fast()->create($attributes);
    $players = collect(range(1, $count))->map(fn (int $i) => Player::factory()->create([
        'game_session_id' => $session->id,
        'username' => 'Player'.$i,
        'device_token' => 'tok-'.$i,
    ]));

    return [$session, $players];
}

function advanceClock(int $ms): void
{
    Carbon::setTestNow(now()->addMilliseconds($ms));
}

/**
 * Tick the clock (one config tick at a time) until the session reaches `$status`, or
 * `$until` returns true. Returns the status sequence observed (one entry per change).
 *
 * @return list<string>
 */
function tickUntil(GameSession $session, SessionStatus|Closure $until, int $maxTicks = 20_000): array
{
    $tick = (int) config('game.tick_ms');
    $seen = [];
    $last = null;
    for ($i = 0; $i < $maxTicks; $i++) {
        $session->refresh();
        if ($session->status->value !== $last) {
            $seen[] = $last = $session->status->value;
        }
        $done = $until instanceof Closure ? $until($session) : $session->status === $until;
        if ($done) {
            return $seen;
        }
        advanceClock($tick);
        engine()->tick();
    }

    throw new RuntimeException('tickUntil gave up after '.$maxTicks.' ticks; last status '.$session->status->value);
}

/** Tick until the next `deciding` phase opens (status changes away from, then back to, deciding). */
function tickToDeciding(GameSession $session): void
{
    if ($session->refresh()->status === SessionStatus::Deciding) {
        tickUntil($session, fn (GameSession $s) => $s->status !== SessionStatus::Deciding);
    }
    tickUntil($session, SessionStatus::Deciding);
}

function choose(Player $player, GameSession $session, string $choice): TestResponse
{
    $session->refresh();

    return asPlayer($player)->postJson("/api/sessions/{$session->code}/choice", [
        'choice' => $choice,
        'round' => $session->current_round,
        'decision' => $session->current_decision,
    ]);
}
