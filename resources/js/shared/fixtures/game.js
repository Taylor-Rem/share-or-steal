/**
 * A scripted game in the exact shapes of CONTRACT.md § 9 and § 11, for driving the phone
 * and the big screen without a server. `buildFixture()` returns the `GET me` snapshot to
 * start from and a list of steps; each step waits `delay` ms after the previous one, then
 * produces events from the clock *at that moment*, so `deadline_at` and `phase_ends_at`
 * are real and countdowns count.
 *
 * Six players are simulated every decision, so `decision.revealed` carries real results,
 * moments and a leaderboard, `round.summary` a real scoreboard, and the analysis every
 * beat type. You are player 1 ("You"): round 1 pairs you with Priya (a wall who tests you
 * early), round 2 with Sam (a backstabber). Your choices come from the fixture controller
 * (`ctx.choice`); no tap counts as a timed-out share, as on the server.
 */

const DURATIONS = {
    normal: { pairing_reveal: 8000, choose: 5000, reveal: 5000, round_summary: 12000, beat: 7000 },
    fast: { pairing_reveal: 3000, choose: 1000, reveal: 1000, round_summary: 3000, beat: 1500 },
};
const PAYOFFS = { share: { share: 3, steal: 0 }, steal: { share: 5, steal: 1 } };

export const ME = { id: 1, username: 'You', is_bot: false };
export const ROOM = [
    ME,
    { id: 2, username: 'Priya', is_bot: false },
    { id: 3, username: 'Sam', is_bot: false },
    { id: 4, username: 'Jordan', is_bot: false },
    { id: 5, username: 'Alex', is_bot: false },
    { id: 6, username: 'Morgan Fitzgerald-Whitcombe', is_bot: false },
];
const CODENAMES = { 1: 'Silver Otter', 2: 'Blue Heron', 3: 'Amber Fox', 4: 'Jade Wren', 5: 'Cobalt Lynx', 6: 'Crimson Moth' };
const ARCHETYPES = {
    1: { key: 'diplomat', label: 'The Diplomat', blurb: 'You got stolen from and came back to the table anyway.' },
    2: { key: 'wall', label: 'The Wall', blurb: 'You never let anyone in, and never got burned either.' },
    3: { key: 'backstabber', label: 'The Backstabber', blurb: 'Perfect partner right up until it stopped mattering.' },
    4: { key: 'saint', label: 'The Saint', blurb: 'You shared no matter what it cost you.' },
    5: { key: 'mirror', label: 'The Mirror', blurb: 'Tit-for-tat: nice, retaliatory, forgiving, clear. The tournament winner.' },
    6: { key: 'wildcard', label: 'The Wildcard', blurb: 'Nobody could read you, including maybe you.' },
};
const RESPONSE_MS = { 1: 1240, 2: 2175, 3: 2692, 4: 3303, 5: 812, 6: 2840 };
const PREDICTABILITY = { 1: 0.72, 2: 1, 3: 0.89, 4: 1, 5: 0.9, 6: 0.41 };

// Round 1 pairs you with Priya; round 2 with Sam. Rounds beyond that cycle.
const PAIRS = [
    [[1, 2], [3, 4], [5, 6]],
    [[1, 3], [2, 5], [4, 6]],
];
const SCRIPTS = {
    2: ['share', 'share', 'share', 'steal', 'share', 'share', 'steal', 'steal', 'share', 'share'],
    3: ['share', 'share', 'share', 'share', 'share', 'share', 'share', 'share', 'steal', 'steal'],
};

const iso = (ms) => new Date(ms).toISOString();
const outcome = (you, them) =>
    you === 'share' && them === 'share' ? 'mutual_share' : you === 'steal' && them === 'steal' ? 'mutual_steal' : you === 'share' ? 'betrayed' : 'betrayer';
const rate = (n, d) => (d > 0 ? Number((n / d).toFixed(4)) : null);
const pct = (v) => (v === null ? '—' : `${Math.round(v * 100)}%`);

/** A tiny seeded PRNG so the wildcard is the same wildcard every run. */
function lcg(seed) {
    let s = seed >>> 0;
    return () => ((s = (s * 1664525 + 1013904223) >>> 0) / 2 ** 32);
}

/** Standard competition ranking of totals: [{id, rank}]. */
function ranksOf(totals) {
    const ids = Object.keys(totals).map(Number).sort((a, b) => totals[b] - totals[a] || a - b);
    const ranks = {};
    let rank = 0;
    ids.forEach((id, i) => {
        if (i === 0 || totals[id] < totals[ids[i - 1]]) rank = i + 1;
        ranks[id] = rank;
    });
    return { ids, ranks };
}

export function buildFixture({ code = 'DEMO', fast = false, rounds = 2, decisionsPerRound = 10, anonymous = false, comparison = false } = {}) {
    const d = fast ? DURATIONS.fast : DURATIONS.normal;
    const playerCount = ROOM.length;
    const players = Object.fromEntries(ROOM.map((p) => [p.id, p]));
    const rng = lcg(42);
    const totals = Object.fromEntries(ROOM.map((p) => [p.id, 0]));
    const log = Object.fromEntries(ROOM.map((p) => [p.id, []])); // id -> [{round, partner, moves: [{i, me, them, myPoints, theirPoints, timedOut}]}]
    let previousRanks = null;
    let pairings = [];

    const display = (id) => (anonymous ? CODENAMES[id] : players[id].username);
    const partnerShape = (id) => ({ id, display_name: display(id), is_bot: false, is_codename: anonymous });

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

    /** What player `id` does at decision `i` given this pairing's history from their seat. */
    const move = (id, i, mine, ctx) => {
        const last = mine[mine.length - 1];
        switch (id) {
            case 1: return ctx.choice; // null = no tap
            case 2: case 3: return SCRIPTS[id][(i - 1) % 10];
            case 4: return 'share';
            case 5: return last ? last.them : 'share';
            case 6: return rng() < 0.5 ? 'share' : 'steal';
            default: return 'share';
        }
    };

    for (let r = 1; r <= rounds; r++) {
        const isLastRound = r === rounds;
        const pairsThisRound = PAIRS[(r - 1) % PAIRS.length];

        // Pairing reveal.
        step(r === 1 ? 2500 : d.round_summary, (now) => {
            pairings = pairsThisRound.map(([a, b], k) => ({ id: r * 10 + k, a, b, points: { [a]: 0, [b]: 0 }, moves: [], streak: 0 }));
            for (const p of pairings) {
                log[p.a].push({ round: r, partner: p.b, moves: [] });
                log[p.b].push({ round: r, partner: p.a, moves: [] });
            }
            const ends = now + d.pairing_reveal;
            const s = state(now, { status: 'pairing', round: r, phase_ends_at: iso(ends) });
            const out = [];
            if (r === 1) out.push(session('game.started', now, s, { rounds_count: rounds, decisions_per_round: decisionsPerRound, player_count: playerCount, has_bot: false }));
            out.push(
                session('pairing.revealed', now, s, {
                    round: r,
                    anonymous,
                    ends_at: iso(ends),
                    pairs: anonymous ? [] : pairings.map((p) => ({ a: players[p.a], b: players[p.b] })),
                }),
            );
            const mine = pairings.find((p) => p.a === 1 || p.b === 1);
            out.push(you('you.paired', now, s, { round: r, anonymous, decisions_per_round: decisionsPerRound, seat: mine.a === 1 ? 'a' : 'b', partner: partnerShape(mine.a === 1 ? mine.b : mine.a) }));
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

            // Deadline: score every pairing and reveal.
            step(d.choose, (now, ctx) => {
                ctx.open = false;
                const isLast = i === decisionsPerRound;
                const next = now + d.reveal;
                const s = state(now, { status: 'revealing', round: r, decision: i, phase_ends_at: iso(next) });
                const results = [];
                const moments = [];
                const agg = { shares: 0, steals: 0, mutual_share: 0, mutual_steal: 0, betrayals: 0 };
                let mine = null;

                for (const p of pairings) {
                    const histA = p.moves.map((m) => ({ me: m.a, them: m.b }));
                    const histB = p.moves.map((m) => ({ me: m.b, them: m.a }));
                    const rawA = move(p.a, i, histA, ctx);
                    const rawB = move(p.b, i, histB, ctx);
                    const a = rawA ?? 'share';
                    const b = rawB ?? 'share';
                    const pa = PAYOFFS[a][b];
                    const pb = PAYOFFS[b][a];
                    const beforeA = p.points[p.a];
                    const beforeB = p.points[p.b];
                    p.points[p.a] += pa;
                    p.points[p.b] += pb;
                    totals[p.a] += pa;
                    totals[p.b] += pb;
                    p.moves.push({ i, a, b, timedOutA: rawA === null, timedOutB: rawB === null });
                    log[p.a].at(-1).moves.push({ i, me: a, them: b, myPoints: pa, theirPoints: pb, timedOut: rawA === null, responseMs: rawA === null ? null : p.a === 1 ? ctx.responseMs : RESPONSE_MS[p.a] });
                    log[p.b].at(-1).moves.push({ i, me: b, them: a, myPoints: pb, theirPoints: pa, timedOut: rawB === null, responseMs: rawB === null ? null : RESPONSE_MS[p.b] });

                    agg.shares += (a === 'share') + (b === 'share');
                    agg.steals += (a === 'steal') + (b === 'steal');
                    const o = outcome(a, b);
                    if (o === 'mutual_share') agg.mutual_share++;
                    else if (o === 'mutual_steal') agg.mutual_steal++;
                    else agg.betrayals++;

                    const na = players[p.a].username;
                    const nb = players[p.b].username;
                    if (o === 'betrayer') moments.push({ type: 'betrayal', text: `${na} stole from ${nb}`, player_ids: [p.a, p.b] });
                    if (o === 'betrayed') moments.push({ type: 'betrayal', text: `${nb} stole from ${na}`, player_ids: [p.b, p.a] });
                    if (o === 'mutual_steal') moments.push({ type: 'mutual_steal', text: `${na} and ${nb} both stole`, player_ids: [p.a, p.b] });
                    p.streak = o === 'mutual_share' ? p.streak + 1 : 0;
                    if (p.streak === 3 || (p.streak === decisionsPerRound && p.streak > 3)) moments.push({ type: 'mutual_share_streak', text: `${na} and ${nb} have shared ${p.streak} in a row`, player_ids: [p.a, p.b] });
                    if (beforeB - beforeA >= 5 && p.points[p.a] >= p.points[p.b]) moments.push({ type: 'comeback', text: `${na} came back against ${nb}`, player_ids: [p.a, p.b] });
                    else if (beforeA - beforeB >= 5 && p.points[p.b] >= p.points[p.a]) moments.push({ type: 'comeback', text: `${nb} came back against ${na}`, player_ids: [p.b, p.a] });

                    const seat = (id, choice, points, timedOut) => ({ player_id: id, choice, points, round_total: p.points[id], total: totals[id], timed_out: timedOut });
                    results.push({ pairing_id: p.id, a: seat(p.a, a, pa, rawA === null), b: seat(p.b, b, pb, rawB === null) });
                    if (p.a === 1 || p.b === 1) mine = { p, a, b, pa, pb, rawA, rawB };
                }

                const { ids, ranks } = ranksOf(totals);
                const roundPoints = Object.fromEntries(pairings.flatMap((p) => [[p.a, p.points[p.a]], [p.b, p.points[p.b]]]));
                const leaderboard = ids.map((id) => ({ rank: ranks[id], player: players[id], total_points: totals[id], round_points: roundPoints[id], movement: previousRanks ? previousRanks[id] - ranks[id] : 0 }));

                const meSeat = mine.p.a === 1 ? 'a' : 'b';
                const yours = meSeat === 'a' ? mine.a : mine.b;
                const theirs = meSeat === 'a' ? mine.b : mine.a;
                const yp = meSeat === 'a' ? mine.pa : mine.pb;
                const tp = meSeat === 'a' ? mine.pb : mine.pa;
                const timedOut = (meSeat === 'a' ? mine.rawA : mine.rawB) === null;
                const partnerId = meSeat === 'a' ? mine.p.b : mine.p.a;

                return [
                    session('decision.revealed', now, s, {
                        round: r,
                        decision: i,
                        next_at: iso(next),
                        is_last: isLast,
                        results: anonymous ? [] : results,
                        aggregate: { ...agg, share_rate: rate(agg.shares, agg.shares + agg.steals) },
                        moments: anonymous ? [] : moments.slice(0, 8),
                        leaderboard: anonymous ? [] : leaderboard.slice(0, 10),
                    }),
                    you('you.revealed', now, s, {
                        round: r,
                        decision: i,
                        next_at: iso(next),
                        is_last: isLast,
                        you: { choice: yours, points: yp, timed_out: timedOut, response_ms: timedOut ? null : ctx.responseMs },
                        partner: { choice: theirs, points: tp, timed_out: false },
                        round_total: { you: mine.p.points[1], partner: mine.p.points[partnerId] },
                        total_points: totals[1],
                        outcome: outcome(yours, theirs),
                    }),
                ];
            });
        }

        // Round summary.
        step(d.reveal, (now) => {
            const ends = now + d.round_summary;
            const s = state(now, { status: 'round_summary', round: r, phase_ends_at: iso(ends) });
            const { ids, ranks } = ranksOf(totals);
            const roundPoints = Object.fromEntries(pairings.flatMap((p) => [[p.a, p.points[p.a]], [p.b, p.points[p.b]]]));
            const leaderboard = ids.map((id) => ({ rank: ranks[id], player: players[id], total_points: totals[id], round_points: roundPoints[id], movement: previousRanks ? previousRanks[id] - ranks[id] : 0 }));
            previousRanks = ranks;

            let betrayal = null;
            let cooperative = null;
            const agg = { shares: 0, steals: 0, mutual_share: 0, mutual_steal: 0, betrayals: 0 };
            for (const p of pairings) {
                const taken = { [p.a]: 0, [p.b]: 0 };
                let mutual = 0;
                for (const m of p.moves) {
                    agg.shares += (m.a === 'share') + (m.b === 'share');
                    agg.steals += (m.a === 'steal') + (m.b === 'steal');
                    const o = outcome(m.a, m.b);
                    if (o === 'mutual_share') { agg.mutual_share++; mutual++; } else if (o === 'mutual_steal') agg.mutual_steal++; else agg.betrayals++;
                    if (o === 'betrayer') taken[p.a] += 5;
                    if (o === 'betrayed') taken[p.b] += 5;
                }
                for (const [thief, victim] of [[p.a, p.b], [p.b, p.a]]) {
                    if (taken[thief] > 0 && taken[thief] > (betrayal?.points ?? 0)) betrayal = { text: `${players[thief].username} took ${taken[thief]} points off ${players[victim].username}`, player_ids: [thief, victim], points: taken[thief] };
                }
                if (mutual > 0 && mutual > (cooperative?.mutual_shares ?? 0)) cooperative = { text: `${players[p.a].username} and ${players[p.b].username} shared ${mutual} of ${decisionsPerRound}`, player_ids: [p.a, p.b], mutual_shares: mutual };
            }

            const mine = pairings.find((p) => p.a === 1 || p.b === 1);
            const partnerId = mine.a === 1 ? mine.b : mine.a;
            const myMoves = log[1].at(-1).moves;
            return [
                session('round.summary', now, s, {
                    round: r,
                    is_last: isLastRound,
                    ends_at: iso(ends),
                    leaderboard: anonymous ? [] : leaderboard,
                    biggest_betrayal: anonymous ? null : betrayal,
                    most_cooperative_pair: anonymous ? null : cooperative,
                    aggregate: { share_rate: rate(agg.shares, agg.shares + agg.steals), mutual_share: agg.mutual_share, mutual_steal: agg.mutual_steal, betrayals: agg.betrayals },
                }),
                you('you.round_summary', now, s, {
                    round: r,
                    is_last: isLastRound,
                    round_points: mine.points[1],
                    total_points: totals[1],
                    rank: ranks[1],
                    player_count: playerCount,
                    partner: partnerShape(partnerId),
                    shares: myMoves.filter((m) => m.me === 'share').length,
                    steals: myMoves.filter((m) => m.me === 'steal').length,
                    stolen_from: myMoves.filter((m) => m.them === 'steal').length,
                }),
            ];
        });
    }

    // ---- Analysis: every beat type, computed from the log above where it matters. ----
    const statsFor = (id) => {
        const rounds = log[id];
        const moves = rounds.flatMap((rd) => rd.moves);
        const chosen = moves.filter((m) => !m.timedOut);
        const shares = chosen.filter((m) => m.me === 'share').length;
        const steals = chosen.length - shares;
        let betrayals = 0, exploitation = 0, retaliated = 0, stolenWithNext = 0, forgiven = 0, stolenWithFollow = 0;
        for (const rd of rounds) {
            rd.moves.forEach((m, k) => {
                const prev = rd.moves[k - 1];
                if (prev && !m.timedOut) {
                    if (m.me === 'steal' && prev.me === 'share' && prev.them === 'share') betrayals++;
                    if (m.me === 'steal' && prev.them === 'share') exploitation++;
                    if (prev.them === 'steal') { stolenWithNext++; if (m.me === 'steal') retaliated++; }
                }
                if (m.them === 'steal') {
                    const following = rd.moves.slice(k + 1, k + 4).filter((n) => !n.timedOut);
                    if (following.length) { stolenWithFollow++; if (following.some((n) => n.me === 'share')) forgiven++; }
                }
            });
        }
        const early = chosen.filter((m) => m.i < 9);
        const late = chosen.filter((m) => m.i >= 9);
        const earlyRate = rate(early.filter((m) => m.me === 'share').length, early.length);
        const lateRate = rate(late.filter((m) => m.me === 'share').length, late.length);
        const opening = chosen.filter((m) => m.i === 1);
        return {
            player: players[id],
            total_points: totals[id],
            rank: ranksOf(totals).ranks[id],
            decisions_count: moves.length,
            timeouts: moves.length - chosen.length,
            share_rate: rate(shares, chosen.length),
            opening_move: rate(opening.filter((m) => m.me === 'share').length, opening.length),
            retaliation: rate(retaliated, stolenWithNext),
            forgiveness: rate(forgiven, stolenWithFollow),
            betrayals,
            exploitation,
            exploitation_rate: rate(exploitation, steals),
            endgame_shift: earlyRate === null || lateRate === null ? null : Number((lateRate - earlyRate).toFixed(4)),
            predictability: PREDICTABILITY[id],
            partner_yield: rate(moves.reduce((s, m) => s + m.theirPoints, 0), moves.length),
            sucker_count: chosen.filter((m) => m.me === 'share' && m.them === 'steal').length,
            times_stolen_from: moves.filter((m) => m.them === 'steal').length,
            avg_response_ms: RESPONSE_MS[id],
            archetype: ARCHETYPES[id],
        };
    };

    const info = (key, label, description) => ({ key, label, description });
    const AWARD_INFO = {
        kindest: info('kindest', 'Kindest', 'Highest share rate.'),
        most_forgiving: info('most_forgiving', 'Most Forgiving', 'Highest forgiveness rate, minimum three times stolen from.'),
        most_ruthless: info('most_ruthless', 'Most Ruthless', 'Highest steal rate.'),
        best_partner: info('best_partner', 'Best Partner', 'People who played you walked away richest.'),
        most_betrayed: info('most_betrayed', 'Most Betrayed', 'Shared, and got stolen from, more than anyone.'),
        cold_blooded: info('cold_blooded', 'Cold Blooded', 'Most betrayals right after a mutual share.'),
        endgame_assassin: info('endgame_assassin', 'Endgame Assassin', 'Most negative endgame shift.'),
        unreadable: info('unreadable', 'Unreadable', 'Lowest predictability.'),
        fastest_thumb: info('fastest_thumb', 'Fastest Thumb', 'Lowest average decision time.'),
        champion: info('champion', 'Champion', 'Most total points.'),
    };

    let analysis = null;
    const buildAnalysis = () => {
        const stats = Object.fromEntries(ROOM.map((p) => [p.id, statsFor(p.id)]));
        const { ids } = ranksOf(totals);
        const pick = (key, winnerId, value, label, tie = null) => ({ ...AWARD_INFO[key], winner: players[winnerId], value, value_label: label, tie_break: tie });
        const awards = [
            pick('kindest', 4, stats[4].share_rate, pct(stats[4].share_rate)),
            pick('most_forgiving', 1, 0.67, '67%', 'points'),
            pick('most_ruthless', 2, stats[2].share_rate, pct(stats[2].share_rate)),
            pick('best_partner', 4, stats[4].partner_yield, `${(stats[4].partner_yield ?? 0).toFixed(1)} pts`),
            pick('most_betrayed', 4, stats[4].sucker_count, String(stats[4].sucker_count)),
            pick('cold_blooded', 6, Math.max(1, stats[6].betrayals), String(Math.max(1, stats[6].betrayals))),
            pick('endgame_assassin', 3, stats[3].endgame_shift ?? -1, pct(stats[3].endgame_shift ?? -1)),
            pick('unreadable', 6, PREDICTABILITY[6], pct(PREDICTABILITY[6]), 'coin_flip'),
            pick('fastest_thumb', 5, RESPONSE_MS[5], `${RESPONSE_MS[5]} ms`),
        ];
        const podium = ids.slice(0, 3).map((id, k) => ({ ...AWARD_INFO.champion, winner: players[id], value: totals[id], value_label: `${totals[id]} pts`, tie_break: null, place: k + 1 }));
        const awardsOf = (id) => [...awards.filter((a) => a.winner.id === id), ...podium.filter((a) => a.winner.id === id)].map((a) => info(a.key, a.label, a.description));

        const allMoves = ROOM.flatMap((p) => log[p.id].flatMap((rd) => rd.moves)).filter((m) => !m.timedOut);
        const series = Array.from({ length: decisionsPerRound }, (_, k) => {
            const at = allMoves.filter((m) => m.i === k + 1);
            return { decision: k + 1, share_rate: rate(at.filter((m) => m.me === 'share').length, at.length) };
        });
        const beats = [
            { type: 'room_share_rate', screen: { share_rate: rate(allMoves.filter((m) => m.me === 'share').length, allMoves.length), total_points: Object.values(totals).reduce((a, b) => a + b, 0), max_cooperative_points: playerCount * rounds * decisionsPerRound * 3 } },
            { type: 'share_rate_by_decision', screen: { series } },
        ];
        if (anonymous) {
            beats.push({ type: 'archetype_cards', screen: { count: playerCount }, private: Object.fromEntries(ROOM.map((p) => [p.id, stats[p.id]])) });
        } else {
            for (const id of [...ids].reverse()) beats.push({ type: 'archetype_reveal', screen: stats[id], private: { [id]: stats[id] } });
        }
        const counts = {};
        for (const p of ROOM) counts[ARCHETYPES[p.id].key] = (counts[ARCHETYPES[p.id].key] ?? 0) + 1;
        beats.push({ type: 'archetype_census', screen: { counts: Object.entries(counts).map(([key, count]) => ({ archetype: Object.values(ARCHETYPES).find((a) => a.key === key), count })) } });
        const leader = (stat, label, dir, format) => {
            const best = [...ROOM].map((p) => stats[p.id]).filter((s) => s[stat] !== null).sort((x, y) => (dir === 'lowest' ? x[stat] - y[stat] : y[stat] - x[stat]))[0];
            if (!best) return null;
            const entry = { stat, label, value: best[stat], value_label: format(best[stat]) };
            return anonymous ? entry : { stat, label, player: best.player, ...entry };
        };
        beats.push({ type: 'stat_leaders', screen: { leaders: [
            leader('share_rate', 'Share rate', 'highest', pct), leader('forgiveness', 'Forgiveness', 'highest', pct), leader('betrayals', 'Betrayals', 'highest', String),
            leader('endgame_shift', 'Endgame shift', 'lowest', pct), leader('partner_yield', 'Partner yield', 'highest', (v) => `${v.toFixed(1)} pts`), leader('avg_response_ms', 'Fastest thumb', 'lowest', (v) => `${v} ms`),
        ].filter(Boolean) } });
        for (const a of awards) beats.push({ type: 'award', screen: { award: anonymous ? info(a.key, a.label, a.description) : a }, private: { [a.winner.id]: { award: a } } });
        const sorted = Object.values(totals).sort((x, y) => x - y);
        beats.push({
            type: 'podium',
            screen: anonymous
                ? { distribution: { min: sorted[0], max: sorted.at(-1), median: (sorted[2] + sorted[3]) / 2 }, top_scores: podium.map((a) => a.value) }
                : { places: podium.map((a) => ({ place: a.place, player: a.winner, total_points: a.value, archetype: ARCHETYPES[a.winner.id] })) },
            private: Object.fromEntries(ROOM.map((p) => [p.id, { rank: stats[p.id].rank, total_points: totals[p.id], archetype: ARCHETYPES[p.id], awards: awardsOf(p.id) }])),
        });
        if (comparison) {
            const shape = (subject, before, after) => ({ text: `${subject} stole ${Math.round(((1 - after.share) / (1 - before.share) - 1) * 100)}% more often and forgave ${Math.abs(Math.round((after.forgive / before.forgive - 1) * 100))}% less`, share_rate: { before: before.share, after: after.share }, forgiveness: { before: before.forgive, after: after.forgive }, betrayals: { before: before.betray, after: after.betray } });
            beats.push({
                type: 'comparison',
                screen: shape('Playing anonymously, this room', { share: 0.71, forgive: 0.65, betray: 1.8 }, { share: 0.62, forgive: 0.39, betray: 2.9 }),
                private: Object.fromEntries(ROOM.map((p) => [p.id, shape('You', { share: 0.7, forgive: 0.6, betray: 2 }, { share: stats[p.id].share_rate ?? 0.5, forgive: stats[p.id].forgiveness ?? 0.4, betray: stats[p.id].betrayals })])),
            });
        }
        return beats;
    };

    // One step per beat: the first also starts the analysis. The beat list is built when the
    // analysis starts, from the log as it stands then.
    const maxBeats = 2 + playerCount + 2 + 9 + 1 + (comparison ? 1 : 0);
    for (let index = 0; index < maxBeats; index++) {
        step(index === 0 ? d.round_summary : d.beat, (now) => {
            if (index === 0) analysis = buildAnalysis();
            const beat = analysis[index];
            if (!beat) return [];
            const s = state(now, { status: 'analysis', round: rounds, analysis_beat: index, analysis_beat_count: analysis.length });
            const out = [];
            if (index === 0) out.push(session('analysis.started', now, s, { beat_count: analysis.length }));
            out.push(session('analysis.beat', now, s, { index, count: analysis.length, type: beat.type, payload: beat.screen }));
            for (const [id, payload] of Object.entries(beat.private ?? {})) {
                if (Number(id) === ME.id) out.push(you('you.card', now, s, { index, type: beat.type, payload }));
            }
            return out;
        });
    }
    step(d.beat, (now) => [session('session.ended', now, state(now, { status: 'finished', round: rounds, analysis_beat: analysis.length - 1, analysis_beat_count: analysis.length }), { reason: 'completed' })]);

    return { me, players: ROOM.slice(0, 1), steps, durations: d };
}
