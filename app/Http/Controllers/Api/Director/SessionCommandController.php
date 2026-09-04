<?php

namespace App\Http\Controllers\Api\Director;

use App\Game\Engine;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;

/**
 * The director's buttons: start, pause, resume, next, end, screen reload.
 * Each returns the new state; the engine broadcasts the matching event.
 */
class SessionCommandController extends Controller
{
    public function __construct(private Engine $engine) {}

    public function start(string $code): JsonResponse
    {
        return $this->state($this->engine->start($this->session($code)));
    }

    public function pause(string $code): JsonResponse
    {
        return $this->state($this->engine->pause($this->session($code)));
    }

    public function resume(string $code): JsonResponse
    {
        return $this->state($this->engine->resume($this->session($code)));
    }

    public function next(string $code): JsonResponse
    {
        return $this->state($this->engine->next($this->session($code)));
    }

    public function end(string $code): JsonResponse
    {
        return $this->state($this->engine->end($this->session($code)));
    }

    public function screenReload(string $code): JsonResponse
    {
        $this->engine->screenReload($this->session($code));

        return response()->json((object) []);
    }

    private function session(string $code): GameSession
    {
        return GameSession::where('code', strtoupper($code))->firstOrFail();
    }

    private function state(GameSession $session): JsonResponse
    {
        return response()->json(['state' => $session->toStateArray()]);
    }
}
