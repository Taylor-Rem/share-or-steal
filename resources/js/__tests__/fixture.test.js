import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useGameStore } from '../shared/stores/game';
import { buildFixture } from '../shared/fixtures/game';
import { useFixture } from '../shared/fixtures/useFixture';

describe('fixture game', () => {
    beforeEach(() => {
        vi.useFakeTimers({ now: new Date('2026-09-04T17:00:00.000Z') });
        setActivePinia(createPinia());
    });
    afterEach(() => vi.useRealTimers());

    it('emits contract-shaped events with live deadlines', () => {
        const { steps } = buildFixture({ fast: true, rounds: 1, decisionsPerRound: 3 });
        const ctx = { choice: null };
        const events = steps.flatMap((s) => s.events(Date.now(), ctx));
        const names = events.map((e) => e.event);
        expect(names.slice(0, 5)).toEqual(['player.joined', 'player.joined', 'player.joined', 'player.joined', 'player.joined']);
        expect(names.filter((n) => n === 'decision.opened')).toHaveLength(3);
        expect(names.filter((n) => n === 'you.revealed')).toHaveLength(3);
        expect(names.at(-1)).toBe('session.ended');
        const opened = events.find((e) => e.event === 'decision.opened');
        expect(opened.payload.deadline_at).toBe('2026-09-04T17:00:01.000Z');
        expect(opened.payload.state.phase_ends_at).toBe(opened.payload.deadline_at);
        expect(opened.payload).toHaveProperty('server_time');
        for (const e of events) expect(e.payload.state).toHaveProperty('status');
    });

    it('plays through the store, honouring a tap and a timeout', () => {
        const store = useGameStore();
        const fixture = useFixture(store, { code: 'DEMO', fast: true, rounds: 1, decisionsPerRound: 2 });
        expect(store.status).toBe('lobby');
        expect(store.me.username).toBe('You');

        vi.advanceTimersByTime(800 + 700 * 4 + 2500);
        expect(store.status).toBe('pairing');
        expect(store.partner.display_name).toBe('Priya');

        vi.advanceTimersByTime(3000); // pairing reveal
        expect(store.status).toBe('deciding');
        store.choose('steal');
        store.choose('share'); // second tap: ignored, first stands
        expect(store.decision).toMatchObject({ chosen: true, your_choice: 'steal' });

        vi.advanceTimersByTime(1000); // deadline
        expect(store.status).toBe('revealing');
        expect(store.lastReveal).toMatchObject({ outcome: 'betrayer', you: { choice: 'steal', points: 5, timed_out: false } });

        vi.advanceTimersByTime(1000); // next decision, no tap
        expect(store.decision.index).toBe(2);
        vi.advanceTimersByTime(1000);
        expect(store.lastReveal.you).toMatchObject({ choice: 'share', timed_out: true });

        vi.advanceTimersByTime(1000); // round summary
        expect(store.status).toBe('round_summary');
        expect(store.roundSummary.round_points).toBe(8);

        vi.advanceTimersByTime(3000 + 4000 * 4);
        expect(store.status).toBe('finished');
        expect(store.cards.map((c) => c.type)).toEqual(['archetype_reveal', 'award', 'podium']);
        expect(fixture.done).toBe(true);
    });
});
