/**
 * Pure helpers for the panel: what the one big button should do, how to name a beat,
 * how old a timestamp is. No Vue, no network, so Vitest can pin them down.
 */

export const MIN_PLAYERS = 2;

/** The primary action for a `state` (CONTRACT.md § 5). `{ action, label, disabled, confirm, hint }` or null. */
export function primaryAction(state) {
    if (!state) return null;
    const timed = ['pairing', 'deciding', 'revealing', 'round_summary'].includes(state.status);
    if (state.status === 'finished') return null;
    if (state.paused) return { action: 'resume', label: 'Resume', confirm: false, disabled: false };
    if (state.status === 'lobby') {
        const enough = state.player_count >= MIN_PLAYERS;
        return { action: 'start', label: 'Start game', confirm: true, disabled: !enough, hint: enough ? `${state.player_count} players` : `Need at least ${MIN_PLAYERS} players` };
    }
    if (timed) return { action: 'pause', label: 'Pause', confirm: false, disabled: false };
    if (state.status === 'analysis') {
        const last = state.analysis_beat_count !== null && state.analysis_beat >= state.analysis_beat_count - 1;
        return last
            ? { action: 'next', label: 'Finish game', confirm: true, disabled: false }
            : { action: 'next', label: 'Next beat', confirm: false, disabled: false, hint: `${state.analysis_beat + 1} of ${state.analysis_beat_count}` };
    }
    return null;
}

/** The status line: "Deciding · Round 2 · Decision 7 of 10". */
export function describeState(state) {
    if (!state) return '';
    const names = { lobby: 'Lobby', pairing: 'Pairing', deciding: 'Deciding', revealing: 'Revealing', round_summary: 'Round summary', analysis: 'Analysis', finished: 'Finished' };
    const parts = [names[state.status] ?? state.status];
    if (state.round && state.status !== 'analysis' && state.status !== 'finished') parts.push(`Round ${state.round} of ${state.rounds_count}`);
    if (state.decision && ['deciding', 'revealing'].includes(state.status)) parts.push(`Decision ${state.decision} of ${state.decisions_per_round}`);
    if (state.status === 'analysis' && state.analysis_beat_count) parts.push(`Beat ${state.analysis_beat + 1} of ${state.analysis_beat_count}`);
    if (state.paused) parts.push('PAUSED');
    return parts.join(' · ');
}

/** A human name for a beat from the analysis endpoint (CONTRACT.md § 11.3). */
export function beatLabel(beat) {
    if (!beat) return '—';
    const s = beat.screen ?? {};
    switch (beat.type) {
        case 'room_share_rate': return 'Room share rate';
        case 'share_rate_by_decision': return 'Share rate by decision (the cliff)';
        case 'archetype_reveal': return `Archetype: ${s.player?.username ?? '?'} → ${s.archetype?.label ?? '?'}`;
        case 'archetype_cards': return `Archetype cards to ${s.count ?? '?'} phones`;
        case 'archetype_census': return 'Archetype census';
        case 'stat_leaders': return 'Stat leaders';
        case 'award': return `Award: ${s.award?.label ?? '?'}${s.award?.winner ? ` → ${s.award.winner.username}` : ''}`;
        case 'podium': return 'Podium';
        case 'comparison': return 'Normal vs anonymous comparison';
        default: return beat.type.replace(/_/g, ' ');
    }
}

/** "12 s ago", "3 min ago", "—". */
export function age(iso, now = Date.now()) {
    if (!iso) return '—';
    const s = Math.max(0, Math.round((now - Date.parse(iso)) / 1000));
    if (s < 60) return `${s} s ago`;
    if (s < 3600) return `${Math.floor(s / 60)} min ago`;
    return `${Math.floor(s / 3600)} h ago`;
}

/** Player rows in a useful order: waiting for admission first, then by points, bot last. */
export function sortPlayers(players) {
    return [...players].sort((a, b) => {
        if (a.is_bot !== b.is_bot) return a.is_bot ? 1 : -1;
        const pa = !a.is_admitted && !a.kicked;
        const pb = !b.is_admitted && !b.kicked;
        if (pa !== pb) return pa ? -1 : 1;
        if (a.kicked !== b.kicked) return a.kicked ? 1 : -1;
        return b.total_points - a.total_points || a.id - b.id;
    });
}

/** Turn a CommandError into one line for the banner. */
export function explain(error, verb) {
    const reasons = {
        not_enough_players: 'not enough players in the lobby',
        already_started: 'the game has already started',
        not_paused: 'the game is not paused',
        not_in_analysis: 'the game is not in the analysis yet',
        session_full: 'the room is full',
        network: "the server didn't answer",
        wrong_password: 'wrong password',
        http_401: 'the director key was rejected',
        http_404: 'no such session',
    };
    const why = reasons[error?.reason] ?? error?.message ?? 'something went wrong';
    return `Couldn't ${verb}: ${why}.`;
}
