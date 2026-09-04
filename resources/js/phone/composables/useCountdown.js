import { computed, onScopeDispose, ref, watch } from 'vue';

/**
 * Milliseconds left until an ISO deadline, in server time (CONTRACT.md § 6), ticking on
 * requestAnimationFrame. `total` is the full window so callers can draw a fraction.
 * The phone never decides a phase is over: at zero it just shows zero and waits.
 */
export function useCountdown(clock, deadlineRef, totalRef) {
    const remaining = ref(0);
    let frame = null;

    const tick = () => {
        remaining.value = clock.remaining(deadlineRef.value);
        if (remaining.value > 0) frame = requestAnimationFrame(tick);
        else frame = null;
    };
    const start = () => {
        if (frame !== null) cancelAnimationFrame(frame);
        frame = null;
        tick();
    };

    watch(deadlineRef, start, { immediate: true });
    onScopeDispose(() => frame !== null && cancelAnimationFrame(frame));

    const total = computed(() => Math.max(1, Number(totalRef?.value ?? 0) || 0));
    const fraction = computed(() => (totalRef ? Math.min(1, remaining.value / total.value) : 0));
    const seconds = computed(() => Math.ceil(remaining.value / 1000));

    return { remaining, fraction, seconds, restart: start };
}
