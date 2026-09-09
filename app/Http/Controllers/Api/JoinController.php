<?php

namespace App\Http\Controllers\Api;

use App\Game\Engine;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /api/sessions/{code}/join — CONTRACT.md § 10.2. */
class JoinController extends Controller
{
    public function __invoke(Request $request, Engine $engine, string $code): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:1', 'max:24'],
            'device_token' => ['required', 'string', 'max:64'],
            'avatar' => ['sometimes', 'nullable', 'array:emoji,color'],
            'avatar.emoji' => ['required_with:avatar', 'string', Rule::in(config('game.avatars.emoji'))],
            'avatar.color' => ['required_with:avatar', 'string', Rule::in(config('game.avatars.colors'))],
        ]);

        $session = GameSession::where('code', strtoupper($code))->firstOrFail();

        $result = $engine->join($session, trim($data['username']), $data['device_token'], $data['avatar'] ?? null);

        return response()->json([
            'server_time' => GameSession::iso(now()),
            'state' => $result['session']->toStateArray(),
            'player' => $result['player']->toPublicArray(),
            'is_admitted' => (bool) $result['player']->is_admitted,
        ], $result['created'] ? 201 : 200);
    }
}
