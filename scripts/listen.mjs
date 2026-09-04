#!/usr/bin/env node
/**
 * Subscribe to a session's channels over a real WebSocket and print every event.
 * A phone with no UI: the smoke test for "does a broadcast reach a client?".
 *
 *   node scripts/listen.mjs DEMO                      # session.DEMO + screen.DEMO as a screen
 *   node scripts/listen.mjs DEMO --director=KEY       # + director.DEMO
 *   node scripts/listen.mjs DEMO --device=TOKEN       # as a player (player.{id} needs a join, Session 1)
 *   node scripts/listen.mjs DEMO --url=https://x.laravel.cloud --ws=wss://ws-host:443
 *
 * Needs Node 22+ (global WebSocket). Reads REVERB_APP_KEY, REVERB_HOST, REVERB_PORT,
 * REVERB_SCHEME and APP_URL from .env unless overridden by --key / --ws / --url.
 */
import { readFileSync } from 'node:fs';

const args = Object.fromEntries(process.argv.slice(3).map((a) => { const [k, v = true] = a.replace(/^--/, '').split('='); return [k, v]; }));
const code = (process.argv[2] ?? '').toUpperCase();
if (!code) { console.error('usage: node scripts/listen.mjs CODE [--director=KEY] [--device=TOKEN] [--url=] [--ws=] [--key=]'); process.exit(2); }

const env = {};
try { for (const line of readFileSync(new URL('../.env', import.meta.url), 'utf8').split('\n')) { const m = line.match(/^([A-Z_]+)=("?)(.*)\2$/); if (m) env[m[1]] = m[3]; } } catch {}

const key = args.key ?? env.REVERB_APP_KEY;
const appUrl = (args.url ?? env.APP_URL ?? 'http://localhost').replace(/\/$/, '');
const wsBase = args.ws ?? `${env.REVERB_SCHEME === 'https' ? 'wss' : 'ws'}://${env.REVERB_HOST ?? 'localhost'}:${env.REVERB_PORT ?? 8080}`;
const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Screen-Code': code };
if (args.director) headers['X-Director-Key'] = args.director;
if (args.device) { headers['X-Device-Token'] = args.device; headers['X-Session-Code'] = code; }

const channels = [`private-session.${code}`, `private-screen.${code}`];
if (args.director) channels.push(`private-director.${code}`);

// Reverb on Laravel Cloud only accepts the app's own origin, so send it (Node's WebSocket sends none by default).
const ws = new WebSocket(`${wsBase}/app/${key}?protocol=7&client=js&version=8.6.0`, { headers: { Origin: appUrl } });
const t0 = Date.now();
const log = (...a) => console.log(`[+${String(Date.now() - t0).padStart(5)}ms]`, ...a);

ws.onerror = (e) => { console.error('websocket error', e.message ?? e); process.exit(1); };
ws.onclose = (e) => { log('closed', e.code); process.exit(e.code === 1000 ? 0 : 1); };
ws.onmessage = async ({ data }) => {
    const msg = JSON.parse(data);
    if (msg.event === 'pusher:connection_established') {
        const { socket_id } = JSON.parse(msg.data);
        log('connected, socket', socket_id);
        for (const channel of channels) {
            const res = await fetch(`${appUrl}/api/broadcasting/auth`, { method: 'POST', headers, body: JSON.stringify({ socket_id, channel_name: channel }) });
            if (!res.ok) { log(`auth ${channel} -> ${res.status}`); continue; }
            const { auth } = await res.json();
            ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth } }));
        }
        return;
    }
    if (msg.event === 'pusher_internal:subscription_succeeded') return log('subscribed', msg.channel);
    if (msg.event === 'pusher:error') return log('error', msg.data);
    if (msg.event === 'pusher:ping') return ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
    const payload = typeof msg.data === 'string' ? JSON.parse(msg.data) : msg.data;
    log(msg.event, 'on', msg.channel, JSON.stringify(payload));
};
