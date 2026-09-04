/** `prefers-reduced-motion`: every animation becomes a cut. CSS uses Tailwind's motion-reduce:, JS asks here. */
export function reducedMotion() {
    try {
        return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    } catch {
        return false;
    }
}
