<?php

namespace App\Http\Controllers\Api;

use App\Enums\SessionStatus;
use App\Game\Payloads;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/sessions/{code}/me — the reconnect snapshot (CONTRACT.md § 10.2, § 12).
 * Everything the phone needs to render this instant without waiting for an event.
 */
class MeController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user('game')->player;
        $player->forceFill(['last_seen_at' => now()])->save();
        $session = $player->session()->firstOrFail();

        $pairing = null;
        $seat = null;
        $round = null;
        if ($session->current_round !== null && $session->status->isTimed()) {
            $round = $session->rounds()->where('number', $session->current_round)->first();
            $pairing = $round?->pairings()
                ->with(['playerA', 'playerB', 'decisions'])
                ->where(fn ($q) => $q->where('player_a_id', $player->id)->orWhere('player_b_id', $player->id))
                ->first();
            $seat = $pairing?->seatOf($player);
        }

        return response()->json([
            'server_time' => GameSession::iso(now()),
            'state' => $session->toStateArray(),
            'player' => $player->toPublicArray(),
            'is_admitted' => (bool) $player->is_admitted,
            'kicked' => $player->kicked_at !== null,
            'total_points' => (int) $player->total_points,
            'round' => $pairing ? [
                'number' => $round->number,
                'anonymous' => (bool) $round->anonymous,
                'partner' => Payloads::partner($pairing, $seat, (bool) $round->anonymous),
                'seat' => $seat,
                'round_total' => ['you' => (int) $pairing->{"points_$seat"}, 'partner' => (int) $pairing->{'points_'.Payloads::otherSeat($seat)}],
            ] : null,
            'decision' => $pairing ? $this->decision($session, $pairing, $seat) : null,
            'last_reveal' => $pairing ? $this->lastReveal($session, $pairing, $seat, $round->number) : null,
            'card' => $this->card($session, $player),
        ]);
    }

    private function decision(GameSession $session, Pairing $pairing, string $seat): ?array
    {
        if (! in_array($session->status, [SessionStatus::Deciding, SessionStatus::Revealing], true) || $session->current_decision === null) {
            return null;
        }
        $decision = $pairing->decisions->firstWhere('index', $session->current_decision);
        if ($decision === null) {
            return null;
        }
        $choice = $decision->{"choice_$seat"};

        return [
            'index' => (int) $decision->index,
            'opened_at' => GameSession::iso($decision->opened_at),
            'deadline_at' => GameSession::iso($decision->deadline_at),
            'your_choice' => $choice?->value,
            'chosen' => $choice !== null,
        ];
    }

    private function lastReveal(GameSession $session, Pairing $pairing, string $seat, int $round): ?array
    {
        $decision = $pairing->decisions->filter->isRevealed()->sortByDesc('index')->first();
        if ($decision === null) {
            return null;
        }
        $isCurrent = $session->status === SessionStatus::Revealing && $decision->index === $session->current_decision;
        $nextAt = $isCurrent ? $session->phase_ends_at : $decision->revealed_at->copy()->addMilliseconds($session->durations()['reveal']);

        return Payloads::youRevealed($decision, $pairing, $seat, $round, $nextAt, $decision->index >= $session->decisions_per_round);
    }

    /** The latest you.card this player has been sent, if any. */
    private function card(GameSession $session, Player $player): ?array
    {
        if (! in_array($session->status, [SessionStatus::Analysis, SessionStatus::Finished], true) || $session->analysis_beat === null) {
            return null;
        }
        $beats = $session->analysis_beats ?? [];
        for ($i = min($session->analysis_beat, count($beats) - 1); $i >= 0; $i--) {
            $payload = $beats[$i]['private'][$player->id] ?? $beats[$i]['private'][(string) $player->id] ?? null;
            if ($payload !== null) {
                return ['index' => $i, 'type' => $beats[$i]['type'], 'payload' => $payload];
            }
        }

        return null;
    }
}
