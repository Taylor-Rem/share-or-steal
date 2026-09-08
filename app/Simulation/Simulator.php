<?php

namespace App\Simulation;

use App\Simulation\Client\Http;
use App\Simulation\Client\SimulatedPlayer;
use App\Simulation\Client\Socket;
use Closure;
use GuzzleHttp\Promise\Utils;
use Random\Engine\Mt19937;
use Random\Randomizer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

/**
 * A complete game against a real server: the director creates and starts it, thirty
 * scripted phones play it over HTTP and WebSockets, the director walks the analysis, and
 * the roster's promises are checked at the end. `php artisan game:simulate` is the CLI.
 */
final class Simulator
{
    private LoopInterface $loop;

    private Http $http;

    private string $code = '';

    private array $state = [];

    /** @var list<SimulatedPlayer> */
    private array $players = [];

    private int $connected = 0;

    private bool $started = false;

    private bool $finished = false;

    private ?string $ended = null;

    private float $lastEvent = 0;

    private array $seen = [];

    /** @var array<string, int> event name => count, seen by the director socket */
    public array $events = [];

    public array $result = [];

    public function __construct(
        public readonly string $url,
        public readonly string $wsUrl,
        public readonly string $directorKey,
        public readonly int $playerCount = 30,
        public readonly bool $anonymous = false,
        public readonly bool $fast = true,
        public readonly int $seed = 1,
        public readonly ?int $rounds = null,
        public readonly ?int $decisions = null,
        private ?Closure $log = null,
        private bool $verbose = false,
    ) {
        $this->log ??= fn (string $line) => null;
    }

    private function log(string $line): void
    {
        ($this->log)($line);
    }

    private function directorHeaders(): array
    {
        return ['X-Director-Key' => $this->directorKey];
    }

    /** Play the whole game. Returns the assertion results; see `result` for everything else. */
    public function run(): Assertions
    {
        $this->loop = Loop::get();
        $this->http = new Http($this->loop, $this->url);
        $rng = new Randomizer(new Mt19937($this->seed));

        // 1. The director creates the session.
        $body = array_filter([
            'mode' => $this->anonymous ? 'anonymous' : 'normal',
            'fast_mode' => $this->fast,
            'rounds_count' => $this->rounds,
            'decisions_per_round' => $this->decisions,
            'max_players' => min(40, $this->playerCount + 1),
        ], fn ($v) => $v !== null);
        $res = $this->http->sync('POST', 'director/sessions', $this->directorHeaders(), $body);
        if ($res['status'] !== 201) {
            throw new \RuntimeException("Could not create a session: {$res['status']} ".json_encode($res['json']));
        }
        $this->state = $res['json']['state'];
        $this->code = $this->state['code'];
        $this->log(sprintf('Session %s: %s, %d x %d, %s', $this->code, $this->state['mode'], $this->state['rounds_count'], $this->state['decisions_per_round'], $this->fast ? 'fast clocks' : 'game-day clocks'));

        // 2. The roster.
        foreach (Personality::roster($this->playerCount) as [$kind, $count]) {
            for ($i = 1; $i <= $count; $i++) {
                $this->players[] = new SimulatedPlayer($this->loop, $this->http, $this->wsUrl, $this->url, $this->code, "{$kind}_{$i}", Personality::from($kind), $rng, fn ($l) => $this->verbose && $this->log($l));
            }
        }

        // 3. The director listens.
        $director = new Socket(
            $this->loop, $this->http, $this->wsUrl, $this->url, $this->directorHeaders(),
            ["private-session.{$this->code}", "private-screen.{$this->code}", "private-director.{$this->code}"],
            fn (string $event, string $channel, array $payload) => $this->onDirectorEvent($event, $payload),
            fn () => $this->joinEveryone(),
        );
        $director->connect();

        // 4. A watchdog, in case the clock or the sockets die.
        $this->lastEvent = microtime(true);
        $this->loop->addPeriodicTimer(5, function () {
            if (! $this->finished && microtime(true) - $this->lastEvent > 90) {
                $this->log('No events for 90 s; giving up.');
                $this->finish('timeout');
            }
        });

        $this->loop->run();

        return $this->wrapUp();
    }

    private function joinEveryone(): void
    {
        $this->log(count($this->players).' phones joining…');
        $chain = Utils::all(array_map(fn (SimulatedPlayer $p) => $p->join(), $this->players));
        $chain->then(function () {
            foreach ($this->players as $player) {
                $player->connect(function () {
                    if (++$this->connected === count($this->players) && ! $this->started) {
                        $this->started = true;
                        $this->log("All {$this->connected} phones connected. Starting.");
                        $res = $this->http->sync('POST', "director/sessions/{$this->code}/start", $this->directorHeaders());
                        if ($res['status'] !== 200) {
                            $this->log("Start failed: {$res['status']} ".json_encode($res['json']));
                            $this->finish('start_failed');
                        }
                    }
                });
            }
        }, function (\Throwable $e) {
            $this->log('Join failed: '.$e->getMessage());
            $this->finish('join_failed');
        });
    }

    private function onDirectorEvent(string $event, array $payload): void
    {
        $this->lastEvent = microtime(true);
        $this->events[$event] = ($this->events[$event] ?? 0) + 1;
        if (isset($payload['state'])) {
            $this->state = $payload['state'];
        }
        $s = $this->state;
        if ($this->verbose || in_array($event, ['game.started', 'pairing.revealed', 'round.summary', 'analysis.started', 'session.ended', 'director.warning'], true)) {
            $this->log(sprintf('%-22s %-13s%s', $event, $s['status'] ?? '', isset($s['round']) && $s['round'] ? " r{$s['round']}".(($s['decision'] ?? null) ? " d{$s['decision']}" : '') : ''));
        }
        if ($event === 'decision.revealed' && ! $this->verbose && ($payload['is_last'] ?? false)) {
            $this->log(sprintf('   round %d revealed: share rate %s', $payload['round'], $payload['aggregate']['share_rate'] ?? '—'));
        }
        if ($event === 'analysis.started' || $event === 'analysis.beat') {
            $this->loop->addTimer(0.4, function () {
                if ($this->finished) {
                    return;
                }
                $res = $this->http->sync('POST', "director/sessions/{$this->code}/next", $this->directorHeaders());
                if ($res['status'] !== 200) {
                    $this->log("Next failed: {$res['status']} ".json_encode($res['json']));
                }
            });
        }
        if ($event === 'session.ended') {
            $this->finish($payload['reason'] ?? 'unknown');
        }
    }

    private function finish(string $reason): void
    {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $this->ended = $reason;
        $this->loop->addTimer(0.5, fn () => $this->loop->stop());
    }

    private function wrapUp(): Assertions
    {
        $analysis = $this->http->sync('GET', "director/sessions/{$this->code}/analysis", $this->directorHeaders());
        $detail = $this->http->sync('GET', "director/sessions/{$this->code}", $this->directorHeaders());
        $stats = $analysis['json']['stats'] ?? [];
        $awards = $analysis['json']['awards'] ?? [];
        $records = [];
        $kinds = [];
        foreach ($this->players as $p) {
            $records[$p->name] = $p->record;
            $kinds[$p->name] = $p->personality->value;
        }

        $assertions = new Assertions;
        if ($this->ended !== 'completed') {
            $assertions->results[] = ['name' => 'game completed', 'ok' => false, 'soft' => false, 'detail' => "ended by {$this->ended}"];
        }
        $assertions->run(
            $stats,
            $awards,
            $records,
            $kinds,
            (int) ($this->state['rounds_count'] ?? 5),
            (int) ($this->state['decisions_per_round'] ?? 10),
            $this->playerCount % 2 === 1,
            $detail['json']['players'] ?? [],
        );

        $this->result = [
            'code' => $this->code,
            'ended' => $this->ended,
            'options' => ['url' => $this->url, 'players' => $this->playerCount, 'anonymous' => $this->anonymous, 'fast' => $this->fast, 'seed' => $this->seed],
            'state' => $this->state,
            'events' => $this->events,
            'stats' => $stats,
            'awards' => $awards,
            'beats' => array_map(fn ($b) => $b['type'], $analysis['json']['beats'] ?? []),
            'records' => $records,
            'assertions' => $assertions->results,
        ];

        return $assertions;
    }
}
