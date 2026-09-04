<?php

namespace App\Http\Controllers\Api;

use App\Enums\Choice;
use App\Game\Engine;
use App\Game\GameException;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /api/sessions/{code}/choice — CONTRACT.md § 10.2. First choice wins. */
class ChoiceController extends Controller
{
    public function __invoke(Request $request, Engine $engine, string $code): JsonResponse
    {
        $data = $request->validate([
            'choice' => ['required', Rule::enum(Choice::class)],
            'round' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'integer', 'min:1'],
        ]);

        $session = GameSession::where('code', strtoupper($code))->firstOrFail();
        $player = $request->user('game')->player;

        try {
            $result = $engine->choose($session, $player, Choice::from($data['choice']), (int) $data['round'], (int) $data['decision']);
        } catch (GameException $e) {
            return response()->json([
                'accepted' => false,
                'reason' => $e->reason,
                'server_time' => GameSession::iso(now()),
            ], 409);
        }

        return response()->json([
            'accepted' => true,
            'choice' => $result['choice']->value,
            'response_ms' => $result['response_ms'],
            'server_time' => GameSession::iso(now()),
        ]);
    }
}
