<?php

namespace App\Analysis;

use App\Models\GameSession;

/**
 * The hand-off from the engine to the analysis. Called once, inside the transaction that
 * moves a session from `round_summary` to `analysis`, after the last decision is scored.
 *
 * An implementation reads the session's decision log and writes `player_stats`, `awards`
 * and `sessions.analysis_beats` in the CONTRACT.md § 11 shapes. It must be idempotent
 * (delete what it wrote before) and must never broadcast; the engine does that.
 *
 * Session 1 ships StubAnalyzer; Session 5 binds the real one in AppServiceProvider.
 */
interface Analyzer
{
    public function analyze(GameSession $session): void;
}
