<?php

namespace App\Http\Controllers\Api;

use App\Game\Engine;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /api/sessions/{code}/avatar — pick or change your look from the waiting room. */
class AvatarController extends Controller
{
    public function __invoke(Request $request, Engine $engine, string $code): JsonResponse
    {
        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(config('game.avatars.emoji'))],
            'color' => ['required', 'string', Rule::in(config('game.avatars.colors'))],
        ]);

        $session = GameSession::where('code', strtoupper($code))->firstOrFail();
        $player = $engine->setAvatar($session, $request->user('game')->player, $data);

        return response()->json([
            'server_time' => GameSession::iso(now()),
            'player' => $player->toPublicArray(),
        ]);
    }
}
