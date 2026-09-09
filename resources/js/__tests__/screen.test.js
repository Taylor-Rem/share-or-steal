import { describe, expect, it } from 'vitest';
import { clip, movementArrow, ordinal, pct, pushMoments, revealCue } from '../screen/logic';
import { buildFixture } from '../shared/fixtures/game';

describe('screen logic', () => {
    it('keeps a capped, keyed feed with the newest moment first', () => {
        const r1 = { round: 1, decision: 1, moments: [{ type: 'betrayal', text: 'A stole from B', player_ids: [1, 2] }, { type: 'mutual_steal', text: 'C and D both stole', player_ids: [3, 4] }] };
        const r2 = { round: 1, decision: 2, moments: [{ type: 'comeback', text: 'B came back against A', player_ids: [2, 1] }] };
        let feed = pushMoments([], r1);
        expect(feed.map((m) => m.key)).toEqual(['1-1-0', '1-1-1']);
        feed = pushMoments(feed, r2, 2);
        expect(feed.map((m) => m.key)).toEqual(['1-2-0', '1-1-0']);
        expect(pushMoments(feed, { round: 1, decision: 3, moments: [] })).toBe(feed);
    });

    it('picks the reveal sound from the aggregate', () => {
        expect(revealCue({ betrayals: 2, mutual_share: 5, mutual_steal: 0 })).toBe('betrayal');
        expect(revealCue({ betrayals: 0, mutual_share: 5, mutual_steal: 1 })).toBe('mutual_share');
        expect(revealCue({ betrayals: 0, mutual_share: 1, mutual_steal: 4 })).toBe('mutual_steal');
        expect(revealCue(null)).toBeNull();
    });

    it('formats arrows, names, ordinals and rates', () => {
        expect(movementArrow(2)).toBe('↑2');
        expect(movementArrow(-1)).toBe('↓1');
        expect(movementArrow(0)).toBe('');
        expect(clip('Morgan Fitzgerald-Whitcombe')).toBe('Morgan Fitzge…');
        expect(clip('Sam')).toBe('Sam');
        expect([1, 2, 3, 4, 11, 12, 13, 21, 22].map(ordinal)).toEqual(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd']);
        expect(pct(0.583)).toBe('58%');
        expect(pct(null)).toBe('—');
    });
});

describe('fixture: what the screen needs', () => {
    it('carries results, moments, a leaderboard with movement and every beat type', () => {
        const { steps } = buildFixture({ fast: true, rounds: 2, decisionsPerRound: 10 });
        const ctx = { choice: null };
        const events = steps.flatMap((s) => s.events(Date.now(), ctx));
        const reveals = events.filter((e) => e.event === 'decision.revealed').map((e) => e.payload);
        expect(reveals).toHaveLength(20);
        expect(reveals[0].results).toHaveLength(3);
        expect(reveals[0].results[0].a).toHaveProperty('round_total');
        expect(reveals[0].leaderboard).toHaveLength(6);
        expect(reveals[0].leaderboard[0]).toMatchObject({ rank: 1, movement: 0 });
        expect(reveals.some((r) => r.moments.some((m) => m.type === 'betrayal'))).toBe(true);
        expect(reveals.some((r) => r.moments.some((m) => m.type === 'mutual_share_streak'))).toBe(true);

        const summaries = events.filter((e) => e.event === 'round.summary').map((e) => e.payload);
        expect(summaries[0].leaderboard).toHaveLength(6);
        expect(summaries[0].biggest_betrayal).toHaveProperty('points');
        expect(summaries[0].most_cooperative_pair).toHaveProperty('mutual_shares');
        expect(summaries[1].leaderboard.some((e) => e.movement !== 0)).toBe(true);

        const beats = events.filter((e) => e.event === 'analysis.beat').map((e) => e.payload);
        expect(beats.map((b) => b.type)).toEqual(['room_share_rate', 'share_rate_by_decision', ...Array(6).fill('archetype_reveal'), 'archetype_census', 'stat_leaders', ...Array(9).fill('award'), 'podium']);
        expect(beats[1].payload.series).toHaveLength(10);
        expect(beats.at(-1).payload.places).toHaveLength(3);
        // Reveals run from the back of the field to the champion.
        const ranks = beats.filter((b) => b.type === 'archetype_reveal').map((b) => b.payload.rank);
        expect(ranks).toEqual([...ranks].sort((a, b) => b - a));
    });

    it('shows no names anywhere in the anonymous variant, and adds the comparison when asked', () => {
        const { steps } = buildFixture({ fast: true, anonymous: true, comparison: true });
        const ctx = { choice: null };
        const events = steps.flatMap((s) => s.events(Date.now(), ctx));
        const publicPayloads = events.filter((e) => e.channel.startsWith('session.')).map((e) => e.payload);
        expect(JSON.stringify(publicPayloads.map((p) => ({ ...p, state: null })))).not.toContain('Priya');
        expect(events.find((e) => e.event === 'pairing.revealed').payload.pairs).toEqual([]);
        const beats = publicPayloads.filter((p) => p.event === 'analysis.beat');
        expect(beats.map((b) => b.type)).toContain('archetype_cards');
        expect(beats.map((b) => b.type)).not.toContain('archetype_reveal');
        expect(beats.at(-1).type).toBe('comparison');
        expect(beats.at(-2).payload).toHaveProperty('distribution');
        expect(events.filter((e) => e.event === 'you.card').map((e) => e.payload.type)).toContain('comparison');
    });
});
