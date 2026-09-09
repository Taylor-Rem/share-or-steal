/**
 * Pure helpers for the big screen: what the feed shows, which sound a reveal deserves,
 * how a name is clipped. No Vue, no GSAP, so Vitest can pin them down.
 */

/**
 * Append a reveal's moments to the feed, newest reveal first, capped. The server already
 * orders a reveal's moments rarest first (comeback, streak, betrayal, mutual steal), so
 * that order is kept. Each item gets a stable key.
 */
export function pushMoments(feed, reveal, cap = 8) {
    if (!reveal?.moments?.length) return feed;
    const fresh = reveal.moments.map((m, i) => ({ ...m, key: `${reveal.round}-${reveal.decision}-${i}`, round: reveal.round, decision: reveal.decision }));
    return [...fresh, ...feed].slice(0, cap);
}

/**
 * The one sound a reveal gets (docs/PLAN.md sound table): a sting if anyone was
 * betrayed, a warm chime if the room mostly shared, else the dull thud.
 */
export function revealCue(aggregate) {
    if (!aggregate) return null;
    if (aggregate.betrayals > 0) return 'betrayal';
    if (aggregate.mutual_share >= aggregate.mutual_steal) return 'mutual_share';
    return 'mutual_steal';
}

/** "↑2", "↓1", "" for the leaderboard. */
export function movementArrow(movement) {
    if (!movement) return '';
    return movement > 0 ? `↑${movement}` : `↓${-movement}`;
}

/** Clip a username for a big-screen slot; the phone shows the whole thing. */
export function clip(name, max = 14) {
    if (!name) return '';
    return name.length <= max ? name : `${name.slice(0, max - 1).trimEnd()}…`;
}

/** Ordinal for a rank. */
export function ordinal(n) {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return `${n}${s[(v - 20) % 10] || s[v] || s[0]}`;
}

/** Percent label for a 0..1 rate, or a dash. */
export function pct(v) {
    return v === null || v === undefined ? '—' : `${Math.round(v * 100)}%`;
}
