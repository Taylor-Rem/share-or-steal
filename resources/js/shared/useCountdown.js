import { computed, onScopeDispose, ref, watch } from 'vue';

/**
 * Milliseconds left until an ISO deadline, in server time (CONTRACT.md § 6). `total` is
 * the full window so callers can draw a fraction. The client never decides a phase is
 * over: at zero it shows zero and waits for the event.
 *
 * Driven by a short interval, not requestAnimationFrame: rAF stops in a background tab
 * (a director's second window, a phone that flipped apps), and a frozen countdown that
 * springs back to life is worse than a 100 ms one.
 */
const TICK_MS = 100;

export function useCountdown(clock, deadlineRef, totalRef) {
    const remaining = ref(0);
    let timer = null;

    const stop = () => {
        if (timer !== null) clearInterval(timer);
        timer = null;
    };
    const tick = () => {
        remaining.value = clock.remaining(deadlineRef.value);
        if (remaining.value <= 0) stop();
    };
    const start = () => {
        stop();
        tick();
        if (remaining.value > 0) timer = setInterval(tick, TICK_MS);
    };

    watch(deadlineRef, start, { immediate: true });

    const onVisible = () => document.visibilityState === 'visible' && start();
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisible);
    onScopeDispose(() => {
        stop();
        if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisible);
    });

    const total = computed(() => Math.max(1, Number(totalRef?.value ?? 0) || 0));
    const fraction = computed(() => (totalRef ? Math.min(1, remaining.value / total.value) : 0));
    const seconds = computed(() => Math.ceil(remaining.value / 1000));

    return { remaining, fraction, seconds, restart: start };
}
