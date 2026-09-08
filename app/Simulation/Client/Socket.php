<?php

namespace App\Simulation\Client;

use Closure;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use React\Socket\Connector as SocketConnector;

/**
 * One Reverb connection speaking the Pusher protocol, like scripts/listen.mjs: connect,
 * learn the socket id, authorize each private channel over HTTP with the identity
 * headers, subscribe, answer pings, and hand every event to `$onEvent(name, channel,
 * payload)`. `$onReady` fires once every channel is subscribed.
 */
final class Socket
{
    private ?WebSocket $ws = null;

    private int $pending = 0;

    public bool $closedByUs = false;

    public function __construct(
        private LoopInterface $loop,
        private Http $http,
        private string $wsUrl,
        private string $origin,
        private array $authHeaders,
        private array $channels,
        private Closure $onEvent,
        private ?Closure $onReady = null,
        private ?Closure $onClose = null,
    ) {}

    public function connect(): void
    {
        $this->closedByUs = false;
        $this->pending = count($this->channels);
        $connector = new Connector($this->loop, new SocketConnector($this->loop, ['timeout' => 15]));
        $connector($this->wsUrl, [], ['Origin' => $this->origin])->then(
            function (WebSocket $ws) {
                $this->ws = $ws;
                $ws->on('message', fn ($message) => $this->handle((string) $message));
                $ws->on('close', fn ($code, $reason) => ($this->onClose ?? fn () => null)($this->closedByUs, $code, (string) $reason));
            },
            fn (\Throwable $e) => ($this->onClose ?? fn () => null)(false, -1, $e->getMessage()),
        );
    }

    public function close(): void
    {
        $this->closedByUs = true;
        $this->ws?->close();
        $this->ws = null;
    }

    private function handle(string $raw): void
    {
        $message = json_decode($raw, true);
        if (! is_array($message)) {
            return;
        }
        $event = $message['event'] ?? '';
        $data = is_string($message['data'] ?? null) ? (json_decode($message['data'], true) ?? []) : ($message['data'] ?? []);

        if ($event === 'pusher:connection_established') {
            $socketId = $data['socket_id'] ?? '';
            foreach ($this->channels as $channel) {
                $this->http->request('POST', 'broadcasting/auth', $this->authHeaders, ['socket_id' => $socketId, 'channel_name' => $channel])
                    ->then(function (array $res) use ($channel) {
                        if ($res['status'] !== 200 || ! isset($res['json']['auth'])) {
                            ($this->onEvent)('simulator.auth_failed', $channel, ['status' => $res['status']]);

                            return;
                        }
                        $this->ws?->send(json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel, 'auth' => $res['json']['auth']]]));
                    });
            }

            return;
        }
        if ($event === 'pusher_internal:subscription_succeeded') {
            if (--$this->pending === 0 && $this->onReady) {
                ($this->onReady)();
            }

            return;
        }
        if ($event === 'pusher:ping') {
            $this->ws?->send(json_encode(['event' => 'pusher:pong', 'data' => []]));

            return;
        }
        if (str_starts_with($event, 'pusher')) {
            return;
        }

        ($this->onEvent)($event, (string) ($message['channel'] ?? ''), $data);
    }
}
