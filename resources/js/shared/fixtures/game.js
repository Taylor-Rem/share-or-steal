/**
 * A scripted game in the exact shapes of CONTRACT.md § 9, for driving the phone (and the
 * screen) without a server. `buildFixture()` returns the `GET me` snapshot to start from
 * and a list of steps; each step waits `delay` ms after the previous one, then produces
 * events from the clock *at that moment*, so `deadline_at` and `phase_ends_at` are real
 * and countdowns count.
 *
 * You are player 1 ("You"). Round 1 pairs you with Priya (a mirror who tests you once),
 * round 2 with Sam (a backstabber). Your choices come from the fixture controller
 * (`ctx.choice`); no tap counts as a timed-out share, as on the server.
 */

const DURATIONS = {
    normal: { pairing_reveal: 8000, choose: 5000, reveal: 5000, round_summary: 12000 },
    fast: { pairing_reveal: 3000, choose: 1000, reveal: 1000, round_summary: 3000 },
};
const PAYOFFS = { share: { share: 3, steal: 0 }, steal: { share: 5, steal: 1 } };

export const ME = { id: 1, username: 'You', is_bot: false };
export const ROOM = [
    ME,
    { id: 2, username: 'Priya', is_bot: false },
    { id: 3, username: 'Sam', is_bot: false },
    { id: 4, username: 'Jordan', is_bot: false },
    { id: 5, username: 'Alex', is_bot: false },
    { id: 6, username: 'Morgan', is_bot: false },
];

const PARTNERS = [
    { player: ROOM[1], codename: 'Blue Heron', script: ['share', 'share', 'share', 'steal', 'share', 'share', 'steal', 'steal', 'share', 'share'] },
    { player: ROOM[2], codename: 'Amber Fox', script: ['share', 'share', 'share', 'share', 'share', 'share', 'share', 'share', 'steal', 'steal'] },
];

const iso = (ms) => new Date(ms).toISOString();
const outcome = (you, them) =>
    you === 'share' && them === 'share' ? 'mutual_share' : you === 'steal' && them === 'steal' ? 'mutual_steal' : you === 'share' ? 'betrayed' : 'betrayer';

export function buildFixture({ code = 'DEMO', fast = false, rounds = 2, decisionsPerRound = 10, anonymous = false } = {}) {
    const d = fast ? DURATIONS.fast : DURATIONS.normal;
    const playerCount = ROOM.length;
    const totals = { you: 0, partner: 0 };
    const roundTotals = { you: 0, partner: 0 };
    let roundShares = 0;
    let roundSteals = 0;
    let roundStolenFrom = 0;

    const state = (now, over = {}) => ({
        code,
        mode: anonymous ? 'anonymous' : 'normal',
        status: 'lobby',
        fast_mode: fast,
        paused: false,
        rounds_count: rounds,
        decisions_per_round: decisionsPerRound,
        round: null,
        decision: null,
        analysis_beat: null,
        analysis_beat_count: null,
        phase_ends_at: null,
        player_count: playerCount,
        ...over,
    });
    const envelope = (channel, event, now, state, fields) => ({ channel, event, payload: { event, server_time: iso(now), state, ...fields } });
    const session = (event, now, state, fields) => envelope(`session.${code}`, event, now, state, fields);
    const you = (event, now, state, fields) => envelope(`player.${ME.id}`, event, now, state, fields);

    const me = {
        server_time: iso(Date.now()),
        state: state(Date.now(), { player_count: 1 }),
        player: ME,
        is_admitted: true,
        kicked: false,
        total_points: 0,
        round: null,
        decision: null,
        last_reveal: null,
        card: null,
    };

    const steps = [];
    const step = (delay, events) => steps.push({ delay, events });

    // Lobby: the room fills.
    ROOM.slice(1).forEach((p, i) => {
        step(i === 0 ? 800 : 700, (now) => [session('player.joined', now, state(now, { player_count: i + 2 }), { player: anonymous ? null : p, player_count: i + 2 })]);
    });

    for (let r = 1; r <= rounds; r++) {
        const partner = PARTNERS[(r - 1) % PARTNERS.length];
        const partnerShape = { id: partner.player.id, display_name: anonymous ? partner.codename : partner.player.username, is_bot: false, is_codename: anonymous };
        const isLastRound = r === rounds;

        // Pairing reveal.
        step(r === 1 ? 2500 : d.round_summary, (now) => {
            roundTotals.you = 0;
            roundTotals.partner = 0;
            roundShares = roundSteals = roundStolenFrom = 0;
            const ends = now + d.pairing_reveal;
            const s = state(now, { status: 'pairing', round: r, phase_ends_at: iso(ends) });
            const out = [];
            if (r === 1) out.push(session('game.started', now, s, { rounds_count: rounds, decisions_per_round: decisionsPerRound, player_count: playerCount, has_bot: false }));
            out.push(
                session('pairing.revealed', now, s, {
                    round: r,
                    anonymous,
                    ends_at: iso(ends),
                    pairs: anonymous ? [] : [{ a: ME, b: partner.player }, { a: ROOM[2 + (r % 2)], b: ROOM[3 + (r % 2)] }, { a: ROOM[4], b: ROOM[5] }].slice(0, 3),
                }),
                you('you.paired', now, s, { round: r, anonymous, decisions_per_round: decisionsPerRound, seat: 'a', partner: partnerShape }),
            );
            return out;
        });

        for (let i = 1; i <= decisionsPerRound; i++) {
            // Decision opens.
            step(i === 1 ? d.pairing_reveal : d.reveal, (now, ctx) => {
                ctx.choice = null;
                ctx.openedAt = now;
                ctx.open = true;
                const deadline = now + d.choose;
                const s = state(now, { status: 'deciding', round: r, decision: i, phase_ends_at: iso(deadline) });
                return [session('decision.opened', now, s, { round: r, decision: i, opened_at: iso(now), deadline_at: iso(deadline), choose_ms: d.choose })];
            });

            // Deadline: score and reveal.
            step(d.choose, (now, ctx) => {
                ctx.open = false;
                const yours = ctx.choice ?? 'share';
                const timedOut = ctx.choice === null;
                const theirs = partner.script[(i - 1) % partner.script.length];
                const yp = PAYOFFS[yours][theirs];
                const tp = PAYOFFS[theirs][yours];
                roundTotals.you += yp;
                roundTotals.partner += tp;
                totals.you += yp;
                totals.partner += tp;
                if (!timedOut) yours === 'share' ? roundShares++ : roundSteals++;
                else roundShares++;
                if (theirs === 'steal') roundStolenFrom++;
                const isLast = i === decisionsPerRound;
                const next = now + d.reveal;
                const s = state(now, { status: 'revealing', round: r, decision: i, phase_ends_at: iso(next) });
                const shares = 4 + (theirs === 'share' ? 1 : 0) + (yours === 'share' ? 1 : 0);
                return [
                    session('decision.revealed', now, s, {
                        round: r,
                        decision: i,
                        next_at: iso(next),
                        is_last: isLast,
                        results: [],
                        aggregate: { shares, steals: 6 - shares, mutual_share: 2, mutual_steal: 0, betrayals: 1, share_rate: Number((shares / 6).toFixed(4)) },
                        moments: [],
                        leaderboard: [],
                    }),
                    you('you.revealed', now, s, {
                        round: r,
                        decision: i,
                        next_at: iso(next),
                        is_last: isLast,
                        you: { choice: yours, points: yp, timed_out: timedOut, response_ms: timedOut ? null : ctx.responseMs },
                        partner: { choice: theirs, points: tp, timed_out: false },
                        round_total: { ...roundTotals },
                        total_points: totals.you,
                        outcome: outcome(yours, theirs),
                    }),
                ];
            });
        }

        // Round summary.
        step(d.reveal, (now) => {
            const ends = now + d.round_summary;
            const s = state(now, { status: 'round_summary', round: r, phase_ends_at: iso(ends) });
            const rank = totals.you >= totals.partner ? 1 : 2;
            return [
                session('round.summary', now, s, {
                    round: r,
                    is_last: isLastRound,
                    ends_at: iso(ends),
                    leaderboard: [],
                    biggest_betrayal: null,
                    most_cooperative_pair: null,
                    aggregate: { share_rate: 0.61, mutual_share: 14, mutual_steal: 3, betrayals: 9 },
                }),
                you('you.round_summary', now, s, {
                    round: r,
                    is_last: isLastRound,
                    round_points: roundTotals.you,
                    total_points: totals.you,
                    rank,
                    player_count: playerCount,
                    partner: partnerShape,
                    shares: roundShares,
                    steals: roundSteals,
                    stolen_from: roundStolenFrom,
                }),
            ];
        });
    }

    // Analysis: four beats, three of them with a card for you.
    const stats = (now) => ({
        player: ME,
        total_points: totals.you,
        rank: 2,
        decisions_count: rounds * decisionsPerRound,
        timeouts: 0,
        share_rate: 0.7,
        opening_move: 1,
        retaliation: 0.5,
        forgiveness: 0.67,
        betrayals: 1,
        exploitation: 2,
        exploitation_rate: 0.33,
        endgame_shift: -0.1,
        predictability: 0.72,
        partner_yield: 2.6,
        sucker_count: 3,
        times_stolen_from: 4,
        avg_response_ms: 1240,
        archetype: { key: 'diplomat', label: 'The Diplomat', blurb: 'You got stolen from and came back to the table anyway.' },
    });
    const award = { key: 'most_forgiving', label: 'Most Forgiving', description: 'Highest forgiveness rate, minimum three times stolen from.', winner: ME, value: 0.67, value_label: '67%', tie_break: null };
    const beats = [
        { type: 'room_share_rate', screen: () => ({ share_rate: 0.61, total_points: 612, max_cooperative_points: 6 * rounds * decisionsPerRound * 3 }) },
        { type: 'archetype_reveal', screen: (now) => stats(now), card: (now) => stats(now) },
        { type: 'award', screen: () => ({ award }), card: () => ({ award }) },
        { type: 'podium', screen: () => ({ places: [{ place: 1, player: ROOM[3], total_points: totals.you + 4, archetype: { key: 'mirror', label: 'The Mirror', blurb: 'Tit-for-tat.' } }, { place: 2, player: ME, total_points: totals.you, archetype: stats().archetype }, { place: 3, player: ROOM[1], total_points: totals.partner, archetype: { key: 'pragmatist', label: 'The Pragmatist', blurb: 'You read the room and adjusted.' } }] }), card: () => ({ rank: 2, total_points: totals.you, archetype: stats().archetype, awards: [{ key: award.key, label: award.label, description: award.description }] }) },
    ];
    beats.forEach((beat, index) => {
        step(index === 0 ? d.round_summary : 4000, (now) => {
            const s = state(now, { status: 'analysis', round: rounds, analysis_beat: index, analysis_beat_count: beats.length });
            const out = [];
            if (index === 0) out.push(session('analysis.started', now, s, { beat_count: beats.length }));
            out.push(session('analysis.beat', now, s, { index, count: beats.length, type: beat.type, payload: beat.screen(now) }));
            if (beat.card) out.push(you('you.card', now, s, { index, type: beat.type, payload: beat.card(now) }));
            return out;
        });
    });
    step(4000, (now) => [session('session.ended', now, state(now, { status: 'finished', round: rounds, analysis_beat: beats.length - 1, analysis_beat_count: beats.length }), { reason: 'completed' })]);

    return { me, players: ROOM.slice(0, 1), steps, durations: d };
}
