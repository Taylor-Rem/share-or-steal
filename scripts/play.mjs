#!/usr/bin/env node
/**
 * Play a whole game against a running server: N scripted phones and one director, over
 * real WebSockets and the real HTTP API. The wire-level smoke test for the engine, and a
 * rehearsal for Session 7's simulator.
 *
 *   node scripts/play.mjs --director=KEY                          # 30 players, fast mode, new session
 *   node scripts/play.mjs --director=KEY --players=9 --anonymous --rounds=2 --decisions=3
 *   node scripts/play.mjs --director=KEY --code=ABCD              # join an existing lobby instead
 *   node scripts/play.mjs --director=KEY --url=https://x.laravel.cloud --ws=wss://ws-host:443
 *
 * Needs `php artisan game:run` and Reverb running (`composer dev` starts both) and Node 22+.
 * Prints what the director sees, then the event names observed per channel, and exits
 * non-zero if any event from CONTRACT.md § 9 never showed up on the wire.
 *
 * The room: every fifth player never answers (timeouts, nudges, a director warning), the
 * rest are saints, walls, coin-flippers and copycats. Player 1 double-taps every decision,
 * player 2 answers 100 ms after every deadline, a late joiner arrives after the start and is
 * admitted after round 1, one sleeper is kicked during round 1, and the director pauses for a
 * moment at the start of round 2 and asks the screen to reload once.
 */
import { readFileSync } from 'node:fs';

const args = Object.fromEntries(process.argv.slice(2).map((a) => { const [k, v = true] = a.replace(/^--/, '').split('='); return [k, v]; }));
if (!args.director) { console.error('usage: node scripts/play.mjs --director=KEY [--players=30] [--rounds=5] [--decisions=10] [--anonymous] [--slow] [--code=] [--url=] [--ws=] [--key=]'); process.exit(2); }

const env = {};
try { for (const line of readFileSync(new URL('../.env', import.meta.url), 'utf8').split('\n')) { const m = line.match(/^([A-Z_]+)=("?)(.*)\2$/); if (m) env[m[1]] = m[3]; } } catch {}

const key = args.key ?? env.REVERB_APP_KEY;
const appUrl = (args.url ?? env.APP_URL ?? 'http://localhost').replace(/\/$/, '');
const wsBase = args.ws ?? `${env.REVERB_SCHEME === 'https' ? 'wss' : 'ws'}://${env.REVERB_HOST ?? 'localhost'}:${env.REVERB_PORT ?? 8080}`;
const playerCount = Number(args.players ?? 30);
const t0 = Date.now();
const log = (...a) => console.log(`[+${String(((Date.now() - t0) / 1000).toFixed(1)).padStart(6)}s]`, ...a);

const EXPECTED = {
    session: ['player.joined', 'player.left', 'game.started', 'pairing.revealed', 'decision.opened', 'decision.revealed', 'round.summary', 'analysis.started', 'analysis.beat', 'session.paused', 'session.resumed', 'session.ended'],
    player: ['you.paired', 'you.revealed', 'you.nudged', 'you.round_summary', 'you.card', 'you.admitted', 'you.kicked'],
    screen: ['screen.reload'],
    director: ['director.player_updated', 'director.warning'],
};
const seen = { session: {}, player: {}, screen: {}, director: {} };
const rejections = {};
let accepted = 0;
let clockOffset = 0;

// ---------------------------------------------------------------------------------------------
// HTTP and WebSocket plumbing
// ---------------------------------------------------------------------------------------------

async function http(method, path, headers = {}, body) {
    const res = await fetch(`${appUrl}/api${path}`, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...headers }, body: body ? JSON.stringify(body) : undefined });
    const json = await res.json().catch(() => ({}));
    if (json.server_time) clockOffset = Date.parse(json.server_time) - Date.now();
    return { status: res.status, json };
}

const sockets = [];

/** Open one WebSocket, subscribe to the channels, and route events to `onEvent(name, payload, channel)`. */
function connect(headers, channels, onEvent) {
    return new Promise((resolve, reject) => {
        const ws = new WebSocket(`${wsBase}/app/${key}?protocol=7&client=js&version=8.6.0`, { headers: { Origin: appUrl } });
        sockets.push(ws);
        let pending = channels.length;
        ws.onerror = (e) => reject(new Error(`websocket error: ${e.message ?? e}`));
        ws.onmessage = async ({ data }) => {
            const msg = JSON.parse(data);
            if (msg.event === 'pusher:connection_established') {
                const { socket_id } = JSON.parse(msg.data);
                for (const channel of channels) {
                    const res = await fetch(`${appUrl}/api/broadcasting/auth`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...headers }, body: JSON.stringify({ socket_id, channel_name: channel }) });
                    if (!res.ok) return reject(new Error(`auth ${channel} -> ${res.status}`));
                    const { auth } = await res.json();
                    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth } }));
                }
                return;
            }
            if (msg.event === 'pusher_internal:subscription_succeeded') { if (--pending === 0) resolve(ws); return; }
            if (msg.event === 'pusher:ping') return ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            if (msg.event === 'pusher:error') return log('pusher error', msg.data);
            const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
            const kind = msg.channel.replace(/^private-/, '').split('.')[0];
            if (seen[kind]) seen[kind][msg.event] = (seen[kind][msg.event] ?? 0) + 1;
            if (payload?.server_time) clockOffset = Date.parse(payload.server_time) - Date.now();
            onEvent(msg.event, payload, msg.channel);
        };
    });
}

const serverNow = () => Date.now() + clockOffset;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------------------------------------
// The director
// ---------------------------------------------------------------------------------------------

const directorHeaders = { 'X-Director-Key': args.director };
const dir = (method, path, body) => http(method, `/director${path}`, directorHeaders, body);

let code = args.code?.toUpperCase();
if (!code) {
    const { status, json } = await dir('POST', '/sessions', { mode: args.anonymous ? 'anonymous' : 'normal', fast_mode: !args.slow, rounds_count: Number(args.rounds ?? 5), decisions_per_round: Number(args.decisions ?? 10) });
    if (status !== 201) { console.error('could not create a session', status, json); process.exit(1); }
    code = json.state.code;
    log(`created session ${code} (${json.state.mode}, ${json.state.rounds_count} x ${json.state.decisions_per_round}, fast=${json.state.fast_mode})`, json.urls.screen);
}
const { json: initial } = await http('GET', `/sessions/${code}`);
const chooseMs = initial.state.fast_mode ? 1000 : 5000;

let late = null;
let kicked = false;
let paused = false;
let finished = false;
let lastEvent = Date.now();

const directorOn = async (event, payload) => {
    lastEvent = Date.now();
    const s = payload.state;
    const where = s.round ? ` r${s.round}${s.decision ? ` d${s.decision}` : ''}` : '';
    if (!['director.player_updated'].includes(event)) log(`${event.padEnd(24)} ${s.status.padEnd(13)}${where.padEnd(8)}`, summarize(event, payload));

    if (event === 'game.started') {
        await dir('POST', `/sessions/${code}/screen/reload`);
        late = await spawnPlayer(playerCount + 1, 'Late', true);
    }
    if (event === 'decision.opened' && s.round === 1 && s.decision === 3 && !kicked) {
        kicked = true;
        const sleeper = players.find((p) => p.sleeper && !p.kicked);
        if (sleeper) { sleeper.kicked = true; await dir('POST', `/sessions/${code}/players/${sleeper.id}/kick`); }
    }
    if (event === 'round.summary' && s.round === 1 && late) {
        const { status, json } = await dir('POST', `/sessions/${code}/players/${late.id}/admit`);
        log(`admit late joiner -> ${status}`, json.player ? `is_admitted=${json.player.is_admitted}` : json);
    }
    if (event === 'decision.opened' && s.round === 2 && s.decision === 1 && !paused) {
        paused = true;
        await dir('POST', `/sessions/${code}/pause`);
        await sleep(700);
        await dir('POST', `/sessions/${code}/resume`);
    }
    if (event === 'analysis.started' || event === 'analysis.beat') {
        await sleep(300);
        await dir('POST', `/sessions/${code}/next`);
    }
    if (event === 'session.ended') finish(payload.reason);
};

function summarize(event, p) {
    switch (event) {
        case 'player.joined': return `${p.player?.username ?? '(anonymous)'} count=${p.player_count}`;
        case 'game.started': return `players=${p.player_count} bot=${p.has_bot}`;
        case 'pairing.revealed': return `${p.pairs.length} pairs${p.anonymous ? ' (anonymous)' : ''}`;
        case 'decision.revealed': return `share_rate=${p.aggregate.share_rate} moments=${p.moments.map((m) => m.type).join(',') || '-'} top=${p.leaderboard[0] ? `${p.leaderboard[0].player.username} ${p.leaderboard[0].total_points}` : '-'}`;
        case 'round.summary': return `${p.biggest_betrayal?.text ?? ''} | ${p.most_cooperative_pair?.text ?? ''}`;
        case 'analysis.beat': return `${p.index + 1}/${p.count} ${p.type}`;
        case 'session.paused': return `from ${p.paused_from}, ${p.remaining_ms} ms left`;
        case 'director.warning': return p.message;
        case 'session.ended': return p.reason;
        default: return '';
    }
}

await connect(directorHeaders, [`private-session.${code}`, `private-screen.${code}`, `private-director.${code}`], directorOn);
log('director connected');

// ---------------------------------------------------------------------------------------------
// The phones
// ---------------------------------------------------------------------------------------------

const players = [];

async function spawnPlayer(i, name, lateJoiner = false) {
    const token = `play-${code}-${i}-${Math.random().toString(36).slice(2, 8)}`;
    const headers = { 'X-Device-Token': token, 'X-Session-Code': code };
    const { status, json } = await http('POST', `/sessions/${code}/join`, {}, { username: name, device_token: token });
    if (status !== 201 && status !== 200) throw new Error(`join ${name} -> ${status} ${JSON.stringify(json)}`);
    const me = { id: json.player.id, name, token, headers, sleeper: !lateJoiner && i % 5 === 0, kind: i % 5, partnerLast: null, kicked: false };
    players.push(me);

    const post = async (choice, round, decision) => {
        const { status, json } = await http('POST', `/sessions/${code}/choice`, headers, { choice, round, decision });
        if (json.accepted) accepted++; else rejections[json.reason ?? status] = (rejections[json.reason ?? status] ?? 0) + 1;
    };

    const onEvent = (event, p) => {
        lastEvent = Date.now();
        if (event === 'you.revealed') me.partnerLast = p.partner.choice;
        if (event === 'you.paired') me.partnerLast = null;
        if (event !== 'decision.opened' || me.sleeper) return;
        const { round, decision, deadline_at } = p;
        let choice = { 1: 'share', 2: 'steal', 3: Math.random() < 0.5 ? 'share' : 'steal', 4: me.partnerLast ?? 'share' }[me.kind] ?? 'share';
        if (i === 1) { post(choice, round, decision).then(() => post(choice === 'share' ? 'steal' : 'share', round, decision)); return; }
        if (i === 2) { setTimeout(() => post(choice, round, decision), Math.max(0, Date.parse(deadline_at) - serverNow() + 100)); return; }
        setTimeout(() => post(choice, round, decision), 30 + Math.random() * chooseMs * 0.5);
    };

    // A late joiner may only hold its own channel until admitted; the session channel would 403.
    await connect(headers, lateJoiner ? [`private-player.${me.id}`] : [`private-session.${code}`, `private-player.${me.id}`], onEvent);
    return me;
}

for (let i = 1; i <= playerCount; i++) await spawnPlayer(i, `Sim${String(i).padStart(2, '0')}`);
log(`${players.length} phones joined and connected`);

{
    const { status, json } = await dir('POST', `/sessions/${code}/start`);
    if (status !== 200) { console.error('start failed', status, json); process.exit(1); }
}

// ---------------------------------------------------------------------------------------------
// The end
// ---------------------------------------------------------------------------------------------

const watchdog = setInterval(() => { if (Date.now() - lastEvent > 60_000) { console.error('no events for 60 s, giving up'); finish('timeout'); } }, 5_000);

async function finish(reason) {
    if (finished) return;
    finished = true;
    clearInterval(watchdog);
    await sleep(500);
    const { json } = await dir('GET', `/sessions/${code}/analysis`);
    console.log('\nPodium:', (json.beats ?? []).find((b) => b.type === 'podium')?.screen);
    console.log(`Choices: ${accepted} accepted, rejected:`, rejections);
    let missing = 0;
    for (const [kind, names] of Object.entries(EXPECTED)) {
        const counts = names.map((n) => { const c = seen[kind][n] ?? 0; if (!c) missing++; return `${n}${c ? `×${c}` : ' MISSING'}`; });
        console.log(`${kind.padEnd(9)} ${counts.join('  ')}`);
    }
    const unexpected = Object.entries(seen).flatMap(([kind, names]) => Object.keys(names).filter((n) => !EXPECTED[kind].includes(n) && n !== 'game.ping').map((n) => `${kind}:${n}`));
    if (unexpected.length) console.log('unexpected:', unexpected.join(' '));
    console.log(missing === 0 && reason === 'completed' ? '\nOK: every contract event observed on the wire.' : `\nFAIL: ${missing} event(s) never seen, ended by ${reason}.`);
    for (const ws of sockets) ws.close(1000);
    process.exit(missing === 0 && reason === 'completed' ? 0 : 1);
}
