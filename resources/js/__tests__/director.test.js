import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useGameStore } from '../shared/stores/game';
import { age, beatLabel, describeState, explain, primaryAction, sortPlayers } from '../director/logic';

const state = (over = {}) => ({
    code: 'ROOM', mode: 'normal', status: 'lobby', fast_mode: false, paused: false, rounds_count: 5, decisions_per_round: 10,
    round: null, decision: null, analysis_beat: null, analysis_beat_count: null, phase_ends_at: null, player_count: 4, ...over,
});

describe('primaryAction', () => {
    it('starts from the lobby only with enough players', () => {
        expect(primaryAction(state({ player_count: 1 }))).toMatchObject({ action: 'start', disabled: true });
        expect(primaryAction(state({ player_count: 2 }))).toMatchObject({ action: 'start', disabled: false, confirm: true });
    });
    it('pauses a timed phase and resumes a paused one', () => {
        expect(primaryAction(state({ status: 'deciding', round: 1, decision: 3 }))).toMatchObject({ action: 'pause' });
        expect(primaryAction(state({ status: 'deciding', paused: true }))).toMatchObject({ action: 'resume' });
        expect(primaryAction(state({ status: 'lobby', paused: true }))).toMatchObject({ action: 'resume' });
    });
    it('steps beats and finishes on the last one', () => {
        expect(primaryAction(state({ status: 'analysis', analysis_beat: 0, analysis_beat_count: 3 }))).toMatchObject({ action: 'next', label: 'Next beat', hint: '1 of 3' });
        expect(primaryAction(state({ status: 'analysis', analysis_beat: 2, analysis_beat_count: 3 }))).toMatchObject({ action: 'next', label: 'Finish game', confirm: true });
    });
    it('has nothing to do when finished', () => {
        expect(primaryAction(state({ status: 'finished' }))).toBeNull();
        expect(primaryAction(null)).toBeNull();
    });
});

describe('labels', () => {
    it('describes the state', () => {
        expect(describeState(state({ status: 'deciding', round: 2, decision: 7 }))).toBe('Deciding · Round 2 of 5 · Decision 7 of 10');
        expect(describeState(state({ status: 'analysis', round: 5, analysis_beat: 3, analysis_beat_count: 20, paused: true }))).toBe('Analysis · Beat 4 of 20 · PAUSED');
        expect(describeState(state())).toBe('Lobby');
        expect(describeState(state({ status: 'analysis', round: 2, decision: 10, analysis_beat: 0, analysis_beat_count: 2 }))).toBe('Analysis · Beat 1 of 2');
    });
    it('names beats', () => {
        expect(beatLabel({ type: 'archetype_reveal', screen: { player: { username: 'Priya' }, archetype: { label: 'The Wall' } } })).toBe('Archetype: Priya → The Wall');
        expect(beatLabel({ type: 'award', screen: { award: { label: 'Kindest', winner: { username: 'Jordan' } } } })).toBe('Award: Kindest → Jordan');
        expect(beatLabel({ type: 'award', screen: { award: { label: 'Kindest' } } })).toBe('Award: Kindest');
        expect(beatLabel({ type: 'podium' })).toBe('Podium');
        expect(beatLabel(null)).toBe('—');
    });
    it('formats ages and errors', () => {
        const now = Date.parse('2026-09-04T17:01:00.000Z');
        expect(age('2026-09-04T17:00:48.000Z', now)).toBe('12 s ago');
        expect(age('2026-09-04T16:58:00.000Z', now)).toBe('3 min ago');
        expect(age(null)).toBe('—');
        expect(explain({ reason: 'not_enough_players' }, 'start')).toBe("Couldn't start: not enough players in the lobby.");
        expect(explain({ reason: 'weird', message: 'Boom' }, 'start')).toBe("Couldn't start: Boom.");
    });
    it('sorts waiting players first, bots last, kicked at the back', () => {
        const rows = sortPlayers([
            { id: 1, username: 'a', is_bot: false, is_admitted: true, kicked: false, total_points: 5 },
            { id: 2, username: 'bot', is_bot: true, is_admitted: true, kicked: false, total_points: 50 },
            { id: 3, username: 'late', is_bot: false, is_admitted: false, kicked: false, total_points: 0 },
            { id: 4, username: 'k', is_bot: false, is_admitted: true, kicked: true, total_points: 9 },
            { id: 5, username: 'b', is_bot: false, is_admitted: true, kicked: false, total_points: 8 },
        ]);
        expect(rows.map((r) => r.id)).toEqual([3, 5, 1, 4, 2]);
    });
});

describe('store: director channel', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('keeps the player list current from the detail endpoint and director.player_updated', () => {
        const store = useGameStore();
        store.configure({ code: 'ROOM', kind: 'director', directorKey: 'k' });
        store.setDirectorPlayers([{ id: 1, username: 'Jordan', is_bot: false, is_admitted: true, kicked: false, last_seen_at: null, consecutive_timeouts: 0, total_points: 0 }]);
        store.receive('director.ROOM', '.director.player_updated', {
            event: 'director.player_updated', server_time: '2026-09-04T17:00:00.000Z', state: state(),
            player: { id: 1, username: 'Jordan', is_bot: false }, is_admitted: true, kicked: false, last_seen_at: '2026-09-04T17:00:00.000Z', consecutive_timeouts: 2, total_points: 41,
        });
        store.receive('director.ROOM', '.director.player_updated', {
            event: 'director.player_updated', server_time: '2026-09-04T17:00:00.000Z', state: state(),
            player: { id: 2, username: 'Late', is_bot: false }, is_admitted: false, kicked: false, last_seen_at: null, consecutive_timeouts: 0, total_points: 0,
        });
        expect(store.directorPlayers[1]).toMatchObject({ username: 'Jordan', consecutive_timeouts: 2, total_points: 41 });
        expect(store.directorPlayers[2]).toMatchObject({ username: 'Late', is_admitted: false });
        store.receive('director.ROOM', '.director.warning', { event: 'director.warning', server_time: '2026-09-04T17:00:01.000Z', state: state(), message: '3 players have not answered' });
        expect(store.warnings[0].message).toBe('3 players have not answered');
    });
});
