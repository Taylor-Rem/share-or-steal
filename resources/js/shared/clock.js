/**
 * Server clock correction — CONTRACT.md § Clock.
 *
 * Every payload carries `server_time`. On the first one (and on later ones, lightly
 * smoothed) we record offset = server_time - local now. Countdowns then run toward
 * `deadline_at` in *server* time, so a phone whose clock is five minutes wrong still
 * hits zero when the server does.
 */
export function createClock() {
    let offsetMs = null;

    return {
        /** Call with the `server_time` string from any payload or response. */
        sync(serverTime) {
            if (!serverTime) return;
            const sample = Date.parse(serverTime) - Date.now();
            if (Number.isNaN(sample)) return;
            // First sample wins outright; later samples nudge by a fifth so a slow response can't yank the clock.
            offsetMs = offsetMs === null ? sample : offsetMs + (sample - offsetMs) * 0.2;
        },
        /** Server "now" in ms since epoch. Falls back to local time before the first sync. */
        now() {
            return Date.now() + (offsetMs ?? 0);
        },
        /** Milliseconds until an ISO deadline, never negative. */
        remaining(deadlineAt) {
            if (!deadlineAt) return 0;
            return Math.max(0, Date.parse(deadlineAt) - this.now());
        },
        offset() {
            return offsetMs;
        },
    };
}
