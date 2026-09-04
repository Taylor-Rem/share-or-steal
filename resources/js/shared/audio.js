import { ref } from 'vue';
import { recall, remember } from './device';

/**
 * Named sound cues (CONTRACT-adjacent: see docs/PLAN.md "Sound & animation"). Session 6
 * implements them with Howler; until then every cue is a no-op that logs. Phones default
 * to sound off (`sos.sound`), and the toggle persists.
 *
 * Cues: join, tick, lockin, mutual_share, betrayal, mutual_steal, round_end, card, award.
 */
export const CUES = ['join', 'tick', 'lockin', 'mutual_share', 'betrayal', 'mutual_steal', 'round_end', 'card', 'award'];

const enabled = ref(recall('sound') === 'on');
let unlocked = false;

export function useAudio() {
    return {
        enabled,
        toggle() {
            enabled.value = !enabled.value;
            remember('sound', enabled.value ? 'on' : 'off');
            if (enabled.value) this.unlock();
        },
        /** Call from a user gesture (the join tap). Session 6 resumes the AudioContext here. */
        unlock() {
            unlocked = true;
        },
        cue(name) {
            if (!CUES.includes(name)) console.warn(`[audio] unknown cue "${name}"`);
            if (!enabled.value) return;
            if (import.meta.env?.DEV) console.debug(`[audio] ${name}${unlocked ? '' : ' (locked)'}`);
        },
    };
}
