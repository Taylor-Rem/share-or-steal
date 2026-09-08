import { ref } from 'vue';
import { Howl, Howler } from 'howler';
import { recall, remember } from './device';

/**
 * Sound for both clients, on top of Howler and one sprite sheet per client
 * (public/audio/{client}.json, built by scripts/audio-pack.mjs from resources/audio).
 *
 *   const audio = useAudio();
 *   audio.unlock();              // from a user gesture: the join tap, the screen's first click
 *   audio.cue('betrayal');       // a named effect from docs/PLAN.md's sound table
 *   audio.music('round');        // screen only: lobby | round | analysis | null, with crossfades
 *   audio.intensity(true);       // screen only: the round loop's drum layer for decisions 8-10
 *
 * Phones default to sound off (`sos.sound`), and the toggle persists. The screen is on
 * once unlocked. A sting ducks the music for a moment. Reduced motion changes nothing
 * here: there is simply no visual pulse to sync to.
 */
export const CUES = ['join', 'tick', 'tick_loud', 'lockin', 'mutual_share', 'betrayal', 'mutual_steal', 'round_end', 'card', 'award', 'award_hit', 'podium'];
export const MUSIC = { lobby: 'music_lobby', round: 'music_round', analysis: 'music_analysis' };
const INTENSE = 'music_intense';
const DUCK = { betrayal: 0.35, round_end: 0.4, award: 0.5, award_hit: 0.3, podium: 0.3, card: 0.6 };
const MUSIC_VOLUME = 0.8;
const FADE_MS = 900;

const enabled = ref(recall('sound') === 'on');
const unlocked = ref(false);
const loaded = ref(false);

let client = null; // 'phone' | 'screen'
let howl = null;
let sprite = {};
let musicId = null;
let musicName = null;
let intenseId = null;
let duckTimer = null;

let listening = false;

/** The first real gesture anywhere unlocks audio: a reloaded phone never taps Join again. */
function unlockOnFirstGesture() {
    if (listening || typeof window === 'undefined') return;
    listening = true;
    const once = () => {
        api.unlock();
        for (const type of ['pointerdown', 'touchend', 'keydown']) window.removeEventListener(type, once, true);
    };
    for (const type of ['pointerdown', 'touchend', 'keydown']) window.addEventListener(type, once, { capture: true, passive: true });
}

async function load(which) {
    if (howl || client === which) return;
    client = which;
    unlockOnFirstGesture();
    try {
        const res = await fetch(`/audio/${which}.json`, { cache: 'force-cache' });
        const map = await res.json();
        sprite = map.sprite;
        howl = new Howl({ src: map.src, sprite: map.sprite, preload: true, html5: false, onload: () => (loaded.value = true) });
    } catch (e) {
        console.warn('[audio] no sprite sheet for', which, e);
    }
}

function play(name, volume = 1) {
    if (!howl || !sprite[name]) return null;
    const id = howl.play(name);
    howl.volume(volume, id);
    return id;
}

function fadeOut(id) {
    if (id === null || !howl) return;
    howl.fade(howl.volume(id), 0, FADE_MS, id);
    setTimeout(() => howl?.stop(id), FADE_MS + 50);
}

function duck(amount) {
    if (musicId === null || !howl) return;
    clearTimeout(duckTimer);
    howl.fade(howl.volume(musicId), MUSIC_VOLUME * amount, 120, musicId);
    if (intenseId !== null) howl.fade(howl.volume(intenseId), MUSIC_VOLUME * amount, 120, intenseId);
    duckTimer = setTimeout(() => {
        if (musicId !== null) howl.fade(howl.volume(musicId), MUSIC_VOLUME, 700, musicId);
        if (intenseId !== null) howl.fade(howl.volume(intenseId), MUSIC_VOLUME, 700, intenseId);
    }, 1200);
}

export function useAudio() {
    return api;
}

// One shared instance; also on window.__sosAudio for poking at from the console.
const api = {
        enabled,
        unlocked,
        loaded,
        /** Which sprite sheet to use; the entry point calls this once. */
        use(which) {
            load(which);
        },
        toggle() {
            enabled.value = !enabled.value;
            remember('sound', enabled.value ? 'on' : 'off');
            if (enabled.value) this.unlock();
            else this.music(null);
        },
        /** Call from a user gesture. Resumes the AudioContext browsers keep suspended until then. */
        unlock() {
            unlocked.value = true;
            try {
                if (Howler.ctx && Howler.ctx.state !== 'running') Howler.ctx.resume();
            } catch {
                /* no WebAudio here */
            }
            if (!howl && client) load(client);
        },
        /** A named effect, if sound is on and unlocked. Stings duck the music briefly. */
        cue(name) {
            if (!CUES.includes(name)) console.warn(`[audio] unknown cue "${name}"`);
            if (!enabled.value || !unlocked.value) return;
            if (!howl) {
                if (import.meta.env?.DEV) console.debug(`[audio] ${name} (no sheet)`);
                return;
            }
            play(name);
            if (DUCK[name]) duck(DUCK[name]);
        },
        /** Crossfade to a music bed by name (lobby | round | analysis), or to silence with null. */
        music(name) {
            if (name === musicName) return;
            musicName = name;
            fadeOut(musicId);
            musicId = null;
            if (intenseId !== null) {
                fadeOut(intenseId);
                intenseId = null;
            }
            if (!name || !enabled.value || !unlocked.value || !howl) return;
            musicId = play(MUSIC[name], 0);
            if (musicId !== null) howl.fade(0, MUSIC_VOLUME, FADE_MS, musicId);
        },
        /** The round loop's drum layer: on for decisions 8-10, off otherwise. */
        intensity(on) {
            if (musicName !== 'round' || !howl) return;
            if (on && intenseId === null) {
                intenseId = play(INTENSE, 0);
                if (intenseId !== null) howl.fade(0, MUSIC_VOLUME, FADE_MS, intenseId);
            } else if (!on && intenseId !== null) {
                fadeOut(intenseId);
                intenseId = null;
            }
        },
        /** For tests and the console. */
        _state: () => ({ client, musicName, hasMusic: musicId !== null, hasIntense: intenseId !== null, sprites: Object.keys(sprite) }),
};

if (typeof window !== 'undefined') {
    window.__sosAudio = api;
    window.__Howler = Howler;
}
