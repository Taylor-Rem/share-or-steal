<?php

namespace App\Http\Controllers\Api\Director;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** /api/director/sessions — list, create, detail, analysis (CONTRACT.md § 10.3). */
class SessionsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'sessions' => GameSession::query()->latest('id')->get()->map->toSummaryArray()->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['sometimes', Rule::enum(SessionMode::class)],
            'rounds_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'decisions_per_round' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'fast_mode' => ['sometimes', 'boolean'],
            'max_players' => ['sometimes', 'integer', 'min:'.config('game.min_players'), 'max:100'],
        ]);

        $session = GameSession::create([
            'code' => GameSession::generateCode(),
            'mode' => SessionMode::from($data['mode'] ?? SessionMode::Normal->value),
            'status' => SessionStatus::Lobby,
            'rounds_count' => $data['rounds_count'] ?? config('game.rounds'),
            'decisions_per_round' => $data['decisions_per_round'] ?? config('game.decisions_per_round'),
            'fast_mode' => (bool) ($data['fast_mode'] ?? false),
            'max_players' => $data['max_players'] ?? config('game.max_players'),
        ]);

        return response()->json([
            'state' => $session->toStateArray(),
            'urls' => [
                'join' => url('/'),
                'play' => url('/play/'.$session->code),
                'screen' => url('/screen/'.$session->code),
                'director' => url('/director/'.$session->code),
            ],
        ], 201);
    }

    public function show(string $code): JsonResponse
    {
        $session = GameSession::where('code', strtoupper($code))->firstOrFail();

        return response()->json([
            'server_time' => GameSession::iso(now()),
            'state' => $session->toStateArray(),
            'players' => $session->players()->orderBy('id')->get()->map->toDirectorArray()->values(),
            'rounds' => $session->rounds()->get()->map(fn ($r) => [
                'number' => $r->number,
                'anonymous' => (bool) $r->anonymous,
                'started_at' => GameSession::iso($r->started_at),
                'ended_at' => GameSession::iso($r->ended_at),
            ])->values(),
        ]);
    }

    public function analysis(string $code): JsonResponse
    {
        $session = GameSession::where('code', strtoupper($code))->firstOrFail();

        return response()->json([
            'state' => $session->toStateArray(),
            'stats' => $session->stats()->with('player')->orderBy('rank')->orderBy('player_id')->get()->map->toContractArray()->values(),
            'awards' => $session->awards()->with('player')->orderBy('id')->get()->map->toContractArray()->values(),
            'beats' => $session->analysis_beats ?? [],
        ]);
    }
}
