<?php

namespace App\Http\Controllers\Api\Director;

use App\Game\Engine;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

/** Admit a late joiner, or kick someone. Returns the DirectorPlayer row. */
class PlayerCommandController extends Controller
{
    public function __construct(private Engine $engine) {}

    public function admit(string $code, int $player): JsonResponse
    {
        [$session, $row] = $this->find($code, $player);
        $this->engine->admit($session, $row);

        return response()->json(['player' => $row->refresh()->toDirectorArray()]);
    }

    public function kick(string $code, int $player): JsonResponse
    {
        [$session, $row] = $this->find($code, $player);
        $this->engine->kick($session, $row);

        return response()->json(['player' => $row->refresh()->toDirectorArray()]);
    }

    /** @return array{0: GameSession, 1: Player} */
    private function find(string $code, int $playerId): array
    {
        $session = GameSession::where('code', strtoupper($code))->firstOrFail();
        $player = $session->players()->whereKey($playerId)->firstOrFail();

        return [$session, $player];
    }
}
