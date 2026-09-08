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
            // The beat on screen right now, so a projector that reloads mid-analysis can draw it.
            'beat' => $this->currentBeat($session),
        ]);
    }

    /** The `analysis.beat` payload for the current beat (its screen half), or null outside the analysis. */
    private function currentBeat(GameSession $session): ?array
    {
        $beats = $session->analysis_beats ?? [];
        $index = $session->analysis_beat;
        if ($index === null || ! isset($beats[$index]) || ! in_array($session->status->value, ['analysis', 'finished'], true)) {
            return null;
        }

        return ['index' => (int) $index, 'count' => count($beats), 'type' => $beats[$index]['type'], 'payload' => $beats[$index]['screen']];
    }
}
