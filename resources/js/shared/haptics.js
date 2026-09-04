/**
 * Vibration, where the browser allows it (Android Chrome; iOS Safari does not). Short buzz
 * on a reveal, double buzz when stolen from. Never throws.
 */
export function vibrate(pattern) {
    try {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') navigator.vibrate(pattern);
    } catch {
        /* not allowed here */
    }
}

export const BUZZ = { tap: 15, reveal: 40, stolen: [50, 60, 50], nudge: [30, 40, 30, 40, 30] };
