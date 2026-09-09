import { beforeEach, describe, expect, it, vi } from 'vitest';

const played = [];
vi.mock('howler', () => {
    class Howl {
        constructor(opts) {
            this.opts = opts;
            this.next = 1;
            this.volumes = {};
            this.stopped = [];
            setTimeout(() => opts.onload?.(), 0);
        }
        play(name) {
            played.push(name);
            const id = this.next++;
            this.volumes[id] = 1;
            return id;
        }
        volume(v, id) {
            if (id === undefined) return this.volumes[v] ?? 1;
            this.volumes[id] = v;
        }
        fade(from, to, ms, id) {
            this.volumes[id] = to;
        }
        stop(id) {
            this.stopped.push(id);
        }
        seek(pos, id) {
            if (id === undefined) return 18 + 21.25; // file time: 21.25 s into the round loop, which starts at 18 s
            this.seeks = { ...(this.seeks ?? {}), [id]: pos };
        }
    }
    return { Howl, Howler: { ctx: { state: 'suspended', resume: vi.fn() } } };
});

const sheet = { src: ['/audio/screen.webm'], sprite: { join: [0, 100], betrayal: [200, 1200], music_lobby: [2000, 15000, true], music_round: [18000, 30000, true], music_intense: [49000, 16000, true], music_analysis: [66000, 32000, true] } };
globalThis.fetch = vi.fn(async () => ({ json: async () => sheet }));

describe('useAudio', () => {
    let audio;
    beforeEach(async () => {
        vi.resetModules();
        played.length = 0;
        localStorage.setItem('sos.sound', 'on');
        ({ useAudio: audio } = await import('../shared/audio'));
        audio = audio();
        audio.use('screen');
        await new Promise((r) => setTimeout(r, 5));
    });

    it('stays silent until unlocked by a gesture', () => {
        audio.cue('join');
        expect(played).toEqual([]);
        audio.unlock();
        audio.cue('join');
        expect(played).toEqual(['join']);
    });

    it('plays music beds by name with a single crossfade and a drum layer for the endgame', () => {
        audio.unlock();
        audio.music('lobby');
        audio.music('lobby');
        expect(played).toEqual(['music_lobby']);
        audio.music('round');
        expect(played).toEqual(['music_lobby', 'music_round']);
        audio.intensity(true);
        audio.intensity(true);
        expect(played.at(-1)).toBe('music_intense');
        // Started at the round loop's bar position (21.25 s in, modulo the layer's 16 s) in file time: layer start 49 s + 5.25.
        expect(Object.values(audio._howl().seeks)).toEqual([49 + 5.25]);
        expect(audio._state()).toMatchObject({ musicName: 'round', hasMusic: true, hasIntense: true });
        audio.intensity(false);
        expect(audio._state().hasIntense).toBe(false);
        audio.music('analysis');
        expect(audio._state()).toMatchObject({ musicName: 'analysis', hasIntense: false });
        audio.music(null);
        expect(audio._state().hasMusic).toBe(false);
    });

    it('ignores the drum layer outside the round loop', () => {
        audio.unlock();
        audio.music('lobby');
        audio.intensity(true);
        expect(played).toEqual(['music_lobby']);
    });

    it('respects the toggle and persists it', () => {
        audio.unlock();
        audio.toggle();
        expect(localStorage.getItem('sos.sound')).toBe('off');
        audio.cue('betrayal');
        expect(played).toEqual([]);
        audio.toggle();
        audio.cue('betrayal');
        expect(played).toEqual(['betrayal']);
    });

    it('warns on an unknown cue name', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        audio.cue('kazoo');
        expect(warn).toHaveBeenCalled();
    });
});
