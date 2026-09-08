import { gsap } from 'gsap';
import { reducedMotion } from '../shared/motion';

/**
 * GSAP with one rule: every duration and delay collapses to a cut under
 * prefers-reduced-motion. Components build one timeline per beat with `timeline()` and
 * use `dur()` for any number of seconds.
 */
const reduced = reducedMotion();

export const dur = (seconds) => (reduced ? 0 : seconds);

export function timeline(vars = {}) {
    const tl = gsap.timeline(vars);
    if (reduced) tl.timeScale(1000);
    return tl;
}

export { gsap };
