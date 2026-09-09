import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { normalizeJoinError, useGameStore } from '../shared/stores/game';

const state = (over = {}) => ({
    code: 'ROOM', mode: 'normal', status: 'lobby', fast_mode: false, paused: false, rounds_count: 5, decisions_per_round: 10,
    round: null, decision: null, analysis_beat: null, analysis_beat_count: null, phase_ends_at: null, player_count: 2, ...over,
});
const me = { id: 1, username: 'You', is_bot: false };
const priya = { id: 2, username: 'Priya', is_bot: false };
const partner = { id: 2, display_name: 'Priya', is_bot: false, is_codename: false };

function receive(store, event, fields, stateOver = {}) {
    const channel = event.startsWith('you.') ? 'player.1' : 'session.ROOM';
    store.receive(channel, `.${event}`, { event, server_time: '2026-09-04T17:00:00.000Z', state: state(stateOver), ...fields });
}

describe('game store', () => {
    let store;
    beforeEach(() => {
        setActivePinia(createPinia());
        store = useGameStore();
        store.configure({ code: 'room', kind: 'phone', deviceToken: 'tok' });
    });

    it('applies the GET me snapshot', () => {
        store.applyMe({
            server_time: '2026-09-04T17:00:00.000Z', state: state({ status: 'deciding', round: 3, decision: 7, phase_ends_at: '2026-09-04T17:00:05.000Z' }),
            player: me, is_admitted: true, kicked: false, total_points: 41,
            round: { number: 3, anonymous: false, partner, seat: 'a', round_total: { you: 12, partner: 9 } },
            decision: { index: 7, opened_at: '2026-09-04T17:00:00.000Z', deadline_at: '2026-09-04T17:00:05.000Z', your_choice: 'share', chosen: true },
            last_reveal: { round: 3, decision: 6, next_at: '…', is_last: false, you: { choice: 'share', points: 3, timed_out: false, response_ms: 900 }, partner: { choice: 'share', points: 3, timed_out: false }, round_total: { you: 12, partner: 9 }, total_points: 41, outcome: 'mutual_share' },
            card: null,
        });
        expect(store.code).toBe('ROOM');
        expect(store.me).toEqual(me);
        expect(store.totalPoints).toBe(41);
        expect(store.round).toMatchObject({ number: 3, seat: 'a', partner, decisions_per_round: 10 });
        expect(store.decision).toMatchObject({ round: 3, index: 7, your_choice: 'share', chosen: true });
        expect(store.reveals[6].outcome).toBe('mutual_share');
        expect(store.inCurrentRound).toBe(true);
        expect(store.roundTotal).toEqual({ you: 12, partner: 9 });
        expect(store.clock.offset()).not.toBeNull();
    });

    it('walks a round from the events alone', () => {
        store.me = me;
        receive(store, 'player.joined', { player: priya, player_count: 2 });
        receive(store, 'player.joined', { player: priya, player_count: 2 });
        expect(store.players).toEqual([priya]);

        receive(store, 'game.started', { rounds_count: 5, decisions_per_round: 10, player_count: 2, has_bot: false }, { status: 'pairing', round: 1 });
        receive(store, 'pairing.revealed', { round: 1, anonymous: false, ends_at: '…', pairs: [{ a: me, b: priya }] }, { status: 'pairing', round: 1 });
        receive(store, 'you.paired', { round: 1, anonymous: false, decisions_per_round: 10, seat: 'a', partner }, { status: 'pairing', round: 1 });
        expect(store.status).toBe('pairing');
        expect(store.pairs).toHaveLength(1);
        expect(store.round).toMatchObject({ number: 1, partner });

        receive(store, 'decision.opened', { round: 1, decision: 1, opened_at: '2026-09-04T17:00:00.000Z', deadline_at: '2026-09-04T17:00:05.000Z', choose_ms: 5000 }, { status: 'deciding', round: 1, decision: 1 });
        expect(store.decision).toMatchObject({ index: 1, chosen: false, your_choice: null, choose_ms: 5000 });

        receive(store, 'you.revealed', { round: 1, decision: 1, next_at: '…', is_last: false, you: { choice: 'share', points: 0, timed_out: false, response_ms: 1240 }, partner: { choice: 'steal', points: 5, timed_out: false }, round_total: { you: 0, partner: 5 }, total_points: 0, outcome: 'betrayed' }, { status: 'revealing', round: 1, decision: 1 });
        expect(store.lastReveal.outcome).toBe('betrayed');
        expect(store.reveals[1].you.points).toBe(0);
        expect(store.decision).toMatchObject({ chosen: true, your_choice: 'share' });

        receive(store, 'decision.opened', { round: 1, decision: 2, opened_at: '…', deadline_at: '…', choose_ms: 5000 }, { status: 'deciding', round: 1, decision: 2 });
        receive(store, 'you.nudged', { consecutive_timeouts: 3 });
        expect(store.nudge).toBe(3);
        receive(store, 'you.revealed', { round: 1, decision: 2, next_at: '…', is_last: false, you: { choice: 'steal', points: 5, timed_out: false, response_ms: 300 }, partner: { choice: 'share', points: 0, timed_out: false }, round_total: { you: 5, partner: 5 }, total_points: 5, outcome: 'betrayer' }, { status: 'revealing', round: 1, decision: 2 });
        expect(Object.keys(store.reveals)).toEqual(['1', '2']);
        expect(store.totalPoints).toBe(5);

        receive(store, 'you.round_summary', { round: 1, is_last: false, round_points: 5, total_points: 5, rank: 2, player_count: 2, partner, shares: 1, steals: 1, stolen_from: 1 }, { status: 'round_summary', round: 1 });
        expect(store.roundSummary.rank).toBe(2);

        // A new round wipes the track and the summary; the nudge clears when a decision opens.
        receive(store, 'you.paired', { round: 2, anonymous: false, decisions_per_round: 10, seat: 'b', partner }, { status: 'pairing', round: 2 });
        expect(store.reveals).toEqual({});
        expect(store.roundSummary).toBeNull();
        expect(store.lastReveal).toBeNull();
        expect(store.nudge).toBeNull();
    });

    it('collects cards through the analysis and notices the end', () => {
        store.me = me;
        receive(store, 'analysis.started', { beat_count: 3 }, { status: 'analysis', round: 5, analysis_beat: 0, analysis_beat_count: 3 });
        receive(store, 'analysis.beat', { index: 0, count: 3, type: 'room_share_rate', payload: {} }, { status: 'analysis', round: 5, analysis_beat: 0, analysis_beat_count: 3 });
        expect(store.card).toBeNull();
        receive(store, 'you.card', { index: 1, type: 'archetype_reveal', payload: { archetype: { key: 'saint' } } }, { status: 'analysis', analysis_beat: 1 });
        receive(store, 'you.card', { index: 2, type: 'podium', payload: { rank: 1 } }, { status: 'analysis', analysis_beat: 2 });
        expect(store.card.type).toBe('podium');
        expect(store.cards.map((c) => c.type)).toEqual(['archetype_reveal', 'podium']);
        receive(store, 'session.ended', { reason: 'completed' }, { status: 'finished' });
        expect(store.ended).toBe('completed');
        expect(store.status).toBe('finished');
    });

    it('handles admission, kicking and pausing', () => {
        store.me = me;
        store.isAdmitted = false;
        const subscribe = vi.spyOn(store, 'subscribe').mockImplementation(() => {});
        receive(store, 'you.admitted', {});
        expect(store.isAdmitted).toBe(true);
        expect(subscribe).toHaveBeenCalledWith('session.ROOM');

        receive(store, 'session.paused', { paused_from: 'deciding', remaining_ms: 2140 }, { status: 'deciding', paused: true });
        expect(store.isPaused).toBe(true);
        receive(store, 'session.resumed', { status: 'deciding', phase_ends_at: '…' }, { status: 'deciding', paused: false });
        expect(store.isPaused).toBe(false);

        receive(store, 'player.left', { player_id: 1, reason: 'kicked', player_count: 1 });
        expect(store.kicked).toBe(true);
    });

    it('is not in the round when admitted mid-game', () => {
        store.me = me;
        receive(store, 'decision.opened', { round: 2, decision: 4, opened_at: '…', deadline_at: '…', choose_ms: 5000 }, { status: 'deciding', round: 2, decision: 4 });
        expect(store.round).toBeNull();
        expect(store.inCurrentRound).toBe(false);
    });

    it('posts a choice and records a rejection', async () => {
        store.me = me;
        receive(store, 'decision.opened', { round: 1, decision: 1, opened_at: '…', deadline_at: '…', choose_ms: 5000 }, { status: 'deciding', round: 1, decision: 1 });
        const post = vi.spyOn(store._api, 'post').mockResolvedValue({ data: { accepted: true, choice: 'steal', response_ms: 300, server_time: '2026-09-04T17:00:00.300Z' } });
        await store.choose('steal');
        expect(post).toHaveBeenCalledWith('/sessions/ROOM/choice', { choice: 'steal', round: 1, decision: 1 });
        expect(store.decision).toMatchObject({ chosen: true, your_choice: 'steal' });

        await store.choose('share'); // already chosen locally: no request
        expect(post).toHaveBeenCalledTimes(1);

        receive(store, 'decision.opened', { round: 1, decision: 2, opened_at: '…', deadline_at: '…', choose_ms: 5000 }, { status: 'deciding', round: 1, decision: 2 });
        post.mockRejectedValue({ response: { status: 409, data: { accepted: false, reason: 'deadline_passed', server_time: '2026-09-04T17:00:06.000Z' } } });
        await store.choose('share');
        expect(store.decision).toMatchObject({ chosen: false, rejected: 'deadline_passed' });
    });

    it('turns join failures into messages', () => {
        expect(normalizeJoinError({ response: { status: 404 } }).reason).toBe('not_found');
        expect(normalizeJoinError({ response: { status: 422, data: { errors: { username: ['username taken'] } } } }).reason).toBe('username_taken');
        expect(normalizeJoinError({ response: { status: 409, data: { reason: 'session_full' } } }).message).toBe('The room is full.');
        expect(normalizeJoinError({ response: { status: 409, data: { reason: 'session_finished' } } }).reason).toBe('session_finished');
        expect(normalizeJoinError({}).reason).toBe('network');
    });
});

describe('game store: looks', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('applies player.updated to the room and to me', () => {
        const store = useGameStore();
        store.configure({ code: 'ROOM', kind: 'phone', deviceToken: 'tok' });
        store.me = { id: 1, username: 'You', is_bot: false, avatar: null };
        store.players = [store.me, { id: 2, username: 'Priya', is_bot: false, avatar: null }];
        const look = { emoji: '🦊', color: 'amber' };
        store.receive('session.ROOM', '.player.updated', { event: 'player.updated', server_time: '2026-09-10T17:00:00.000Z', state: state(), player: { id: 1, username: 'You', is_bot: false, avatar: look } });
        expect(store.me.avatar).toEqual(look);
        expect(store.players[0].avatar).toEqual(look);
        expect(store.players[1].avatar).toBeNull();
    });

    it('posts a look and keeps the answer', async () => {
        const store = useGameStore();
        store.configure({ code: 'ROOM', kind: 'phone', deviceToken: 'tok' });
        store.me = { id: 1, username: 'You', is_bot: false, avatar: null };
        store.players = [store.me];
        const post = vi.spyOn(store._api, 'post').mockResolvedValue({ data: { server_time: '2026-09-10T17:00:00.000Z', player: { id: 1, username: 'You', is_bot: false, avatar: { emoji: '🐙', color: 'violet' } } } });
        await store.setAvatar({ emoji: '🐙', color: 'violet' });
        expect(post).toHaveBeenCalledWith('/sessions/ROOM/avatar', { emoji: '🐙', color: 'violet' });
        expect(store.me.avatar.emoji).toBe('🐙');
        expect(store.players[0].avatar.emoji).toBe('🐙');
    });
});
