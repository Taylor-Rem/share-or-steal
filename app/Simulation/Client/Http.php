<?php

namespace App\Simulation\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use React\EventLoop\LoopInterface;

/**
 * Guzzle driven from the ReactPHP loop: the curl-multi handler is ticked every few
 * milliseconds, so thirty phones can post choices at once without blocking the sockets.
 * Every response resolves to ['status' => int, 'json' => array].
 */
final class Http
{
    private CurlMultiHandler $handler;

    private Client $client;

    public function __construct(LoopInterface $loop, string $baseUrl)
    {
        $this->handler = new CurlMultiHandler;
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/api/',
            'handler' => HandlerStack::create($this->handler),
            'http_errors' => false,
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'Simulator'],
        ]);
        $loop->addPeriodicTimer(0.004, fn () => $this->handler->tick());
    }

    /** @return PromiseInterface resolves to array{status: int, json: array<string, mixed>} */
    public function request(string $method, string $path, array $headers = [], ?array $json = null): PromiseInterface
    {
        $options = ['headers' => $headers];
        if ($json !== null) {
            $options['json'] = $json;
        }

        return $this->client->requestAsync($method, ltrim($path, '/'), $options)->then(
            fn ($response) => ['status' => $response->getStatusCode(), 'json' => json_decode((string) $response->getBody(), true) ?? []],
            fn (\Throwable $e) => ['status' => 0, 'json' => ['message' => $e->getMessage()]],
        );
    }

    /** The same, blocking: for the handful of director calls made outside the loop. */
    public function sync(string $method, string $path, array $headers = [], ?array $json = null): array
    {
        return $this->request($method, $path, $headers, $json)->wait();
    }
}
