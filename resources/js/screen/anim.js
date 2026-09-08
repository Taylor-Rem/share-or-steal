import { gsap } from 'gsap';
import { reducedMotion } from '../shared/motion';

/**
 * GSAP with two rules. Every duration and delay collapses to a cut under
 * prefers-reduced-motion. And a timeline started while the tab is hidden (the browser
 * pauses animation frames there) jumps straight to its end state, so a projector tab that
 * was briefly covered shows the finished beat, not a half-drawn one.
 */
const reduced = reducedMotion();
const hidden = () => typeof document !== 'undefined' && document.visibilityState === 'hidden';

export const dur = (seconds) => (reduced ? 0 : seconds);

export function timeline(vars = {}) {
    const tl = gsap.timeline(vars);
    if (reduced || hidden()) tl.timeScale(1000);
    return tl;
}

export { gsap };
