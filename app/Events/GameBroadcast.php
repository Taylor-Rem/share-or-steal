<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for every game event. Adds the envelope from CONTRACT.md § Envelope:
 * `event`, `server_time`, and the session `state` ride on every payload.
 *
 * Events broadcast immediately (ShouldBroadcastNow), never through a queue,
 * because a reveal that arrives a second late is a broken reveal.
 */
abstract class GameBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameSession $session) {}

    /** The dotted event name clients listen for, e.g. `decision.opened`. */
    abstract public function name(): string;

    /**
     * The payload fields specific to this event, merged into the envelope.
     *
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /** @return array<int, Channel> */
    abstract public function broadcastOn(): array;

    public function broadcastAs(): string
    {
        return $this->name();
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->name(),
            'server_time' => GameSession::iso(now()),
            'state' => $this->session->toStateArray(),
        ] + $this->payload();
    }

    public static function sessionChannel(GameSession $session): PrivateChannel
    {
        return new PrivateChannel('session.'.$session->code);
    }

    public static function screenChannel(GameSession $session): PrivateChannel
    {
        return new PrivateChannel('screen.'.$session->code);
    }

    public static function directorChannel(GameSession $session): PrivateChannel
    {
        return new PrivateChannel('director.'.$session->code);
    }

    public static function playerChannel(int $playerId): PrivateChannel
    {
        return new PrivateChannel('player.'.$playerId);
    }
}
