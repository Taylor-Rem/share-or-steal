<?php

namespace App\Simulation\Client;

use App\Enums\Choice;
use App\Simulation\History;
use App\Simulation\Personality;
use App\Simulation\Strategy;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Random\Randomizer;
use React\EventLoop\LoopInterface;

/**
 * One phone: joins over HTTP, listens on its channels over a WebSocket, and answers every
 * `decision.opened` the way its personality would. Keeps a record of everything the
 * assertions need: attempts, acceptances and rejections by reason, nudges, cards, whether
 * its first choice survived a double tap, and its reconnects.
 */
final class SimulatedPlayer
{
    public ?int $id = null;

    public string $token;

    public Strategy $strategy;

    /** @var array<string, mixed> */
    public array $record = [
        'attempts' => 0, 'accepted' => 0, 'rejections' => [], 'nudges' => 0, 'reveals' => 0, 'timeouts_seen' => 0,
        'first_choice_kept' => 0, 'first_choice_lost' => 0, 'cards' => [], 'reconnects' => 0, 'auth_failures' => 0,
    ];

    /** @var list<array{me: string, them: string}> */
    private array $round = [];

    private int $roundNumber = 0;

    private int $decisionsPerRound = 10;

    private ?Socket $socket = null;

    private ?string $lastSubmitted = null;

    private bool $lastAccepted = false;

    private bool $offline = false;

    public function __construct(
        private LoopInterface $loop,
        private Http $http,
        private string $wsUrl,
        private string $origin,
        private string $code,
        public readonly string $name,
        public readonly Personality $personality,
        private Randomizer $rng,
        private \Closure $log,
    ) {
        $this->strategy = $personality->strategy();
        $this->token = 'sim-'.$code.'-'.$name.'-'.bin2hex(random_bytes(3));
    }

    private function headers(): array
    {
        return ['X-Device-Token' => $this->token, 'X-Session-Code' => $this->code];
    }

    /** POST join; resolves to the player id. */
    public function join(): PromiseInterface
    {
        return $this->http->request('POST', "sessions/{$this->code}/join", [], ['username' => $this->name, 'device_token' => $this->token])
            ->then(function (array $res) {
                if (! in_array($res['status'], [200, 201], true)) {
                    throw new \RuntimeException("{$this->name}: join -> {$res['status']} ".json_encode($res['json']));
                }
                $this->id = (int) $res['json']['player']['id'];
                $this->decisionsPerRound = (int) $res['json']['state']['decisions_per_round'];

                return $this->id;
            });
    }

    /** Open the socket; `$onReady` fires when both channels are subscribed. */
    public function connect(\Closure $onReady): void
    {
        $this->socket = new Socket(
            $this->loop,
            $this->http,
            $this->wsUrl,
            $this->origin,
            $this->headers(),
            ["private-session.{$this->code}", "private-player.{$this->id}"],
            fn (string $event, string $channel, array $payload) => $this->onEvent($event, $payload),
            $onReady,
            function (bool $byUs, int $code, string $reason) {
                if (! $byUs) {
                    ($this->log)("{$this->name}: socket closed ({$code} {$reason})");
                }
            },
        );
        $this->socket->connect();
    }

    private function onEvent(string $event, array $payload): void
    {
        switch ($event) {
            case 'simulator.auth_failed':
                $this->record['auth_failures']++;
                break;
            case 'you.paired':
                $this->round = [];
                $this->roundNumber = (int) $payload['round'];
                $this->decisionsPerRound = (int) $payload['decisions_per_round'];
                break;
            case 'decision.opened':
                $this->onDecisionOpened($payload);
                break;
            case 'you.revealed':
                $this->record['reveals']++;
                $this->round[] = ['me' => $payload['you']['choice'], 'them' => $payload['partner']['choice']];
                if ($payload['you']['timed_out']) {
                    $this->record['timeouts_seen']++;
                }
                // Only a first tap the server accepted can be kept or lost.
                if ($this->lastSubmitted !== null && $this->lastAccepted) {
                    $this->record[$payload['you']['choice'] === $this->lastSubmitted ? 'first_choice_kept' : 'first_choice_lost']++;
                }
                $this->lastSubmitted = null;
                $this->lastAccepted = false;
                break;
            case 'you.nudged':
                $this->record['nudges']++;
                break;
            case 'you.card':
                $this->record['cards'][$payload['type']] = ($this->record['cards'][$payload['type']] ?? 0) + 1;
                break;
        }
    }

    private function onDecisionOpened(array $p): void
    {
        $roundNumber = (int) $p['round'];
        $index = (int) $p['decision'];
        $chooseMs = (int) $p['choose_ms'];
        $this->roundNumber = $roundNumber;

        // The Ghost: drop the socket now and come back after the missed decisions.
        $offline = $this->strategy->offline();
        if ($offline !== null && $roundNumber === $offline[0] && $index === $offline[1] && ! $this->offline) {
            $this->offline = true;
            $this->socket?->close();
            ($this->log)("{$this->name}: offline until decision {$offline[2]} is revealed");
            // A phone in a pocket: nothing arrives. Poll the snapshot until the missed decisions
            // are behind us, then reconnect the way a reloaded phone does (CONTRACT.md § 12).
            $poll = function () use (&$poll, $offline) {
                $this->http->request('GET', "sessions/{$this->code}/me", $this->headers())->then(function (array $res) use (&$poll, $offline) {
                    $state = $res['json']['state'] ?? [];
                    $past = $res['status'] === 200 && (
                        ($state['round'] ?? 0) > $offline[0]
                        || ($state['decision'] ?? 0) > $offline[2]
                        || (($state['decision'] ?? 0) === $offline[2] && ($state['status'] ?? '') === 'revealing')
                        || in_array($state['status'] ?? '', ['round_summary', 'analysis', 'finished'], true)
                    );
                    if (! $past) {
                        $this->loop->addTimer(0.2, $poll);

                        return;
                    }
                    $this->round = [];
                    if ($reveal = $res['json']['last_reveal'] ?? null) {
                        $this->round[] = ['me' => $reveal['you']['choice'], 'them' => $reveal['partner']['choice']];
                    }
                    $this->connect(function () {
                        $this->offline = false;
                        $this->record['reconnects']++;
                        ($this->log)("{$this->name}: back online");
                    });
                });
            };
            $this->loop->addTimer(0.5, $poll);

            return;
        }
        if ($this->offline) {
            return;
        }

        $history = new History($this->round, $index, $roundNumber, $this->decisionsPerRound, $this->rng);
        $choice = $this->strategy->choose($history);
        $delay = $this->strategy->tapDelayMs($history, $chooseMs);
        if ($choice === null || $delay === null) {
            return;
        }

        // A phone's taps go out one after another on one connection; the second waits for the first.
        $submissions = $this->strategy->submissions($choice);
        $this->lastSubmitted = $submissions[0]->value;
        $this->lastAccepted = false;
        $this->loop->addTimer($delay / 1000, function () use ($submissions, $roundNumber, $index) {
            $chain = $this->submit($submissions[0], $roundNumber, $index)->then(function (array $res) {
                $this->lastAccepted = $res['status'] === 200 && ($res['json']['accepted'] ?? false);

                return $res;
            });
            foreach (array_slice($submissions, 1) as $extra) {
                $chain = $chain->then(fn () => $this->after(0.05)->then(fn () => $this->submit($extra, $roundNumber, $index)));
            }
        });
    }

    private function after(float $seconds): PromiseInterface
    {
        $promise = new Promise;
        $this->loop->addTimer($seconds, fn () => $promise->resolve(true));

        return $promise;
    }

    private function submit(Choice $choice, int $round, int $decision): PromiseInterface
    {
        $this->record['attempts']++;

        return $this->http->request('POST', "sessions/{$this->code}/choice", $this->headers(), ['choice' => $choice->value, 'round' => $round, 'decision' => $decision])
            ->then(function (array $res) {
                if ($res['status'] === 200 && ($res['json']['accepted'] ?? false)) {
                    $this->record['accepted']++;
                } else {
                    $reason = $res['json']['reason'] ?? "http_{$res['status']}";
                    $this->record['rejections'][$reason] = ($this->record['rejections'][$reason] ?? 0) + 1;
                }

                return $res;
            });
    }
}
