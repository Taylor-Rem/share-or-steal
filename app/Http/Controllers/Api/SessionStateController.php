<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/sessions/{code} — the public snapshot every client loads first.
 * See CONTRACT.md § Endpoints → Public.
 */
class SessionStateController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        $session = GameSession::where('code', strtoupper($code))->firstOrFail();

        $players = $session->players()
            ->whereNull('kicked_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'server_time' => GameSession::iso(now()),
            'state' => $session->toStateArray(),
            // In anonymous mode the room never sees names, only a count.
            'players' => $session->isAnonymous()
                ? []
                : $players->map(fn ($p) => $p->toPublicArray())->values(),
        ]);
    }
}
