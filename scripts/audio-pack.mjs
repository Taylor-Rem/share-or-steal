#!/usr/bin/env node
/**
 * Pack the audio sprite sheets from resources/audio/manifest.json with ffmpeg:
 * trim leading silence, cap each cue's length with a short fade, normalise loudness
 * (-16 LUFS effects, -20 LUFS music), lay every cue on one timeline with 300 ms of
 * silence between them, and write public/audio/{client}.webm + .mp3 + .json (the Howler
 * sprite map). Music loops are kept whole and untrimmed so they stay seamless.
 *
 *   node scripts/audio-pack.mjs            # both clients
 *   node scripts/audio-pack.mjs phone      # one client
 */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const manifest = JSON.parse(readFileSync(join(root, 'resources/audio/manifest.json'), 'utf8'));
const clients = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(manifest).filter((k) => !k.startsWith('_'));
const GAP = 0.3;
const RATE = 44100;

const ffmpeg = (args) => execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: ['ignore', 'pipe', 'inherit'] });
const duration = (file) => Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file]).toString().trim());

for (const client of clients) {
    const cues = manifest[client];
    const work = mkdtempSync(join(tmpdir(), `sos-audio-${client}-`));
    const parts = [];

    for (const [name, cue] of Object.entries(cues)) {
        const src = join(root, 'resources/audio/source', cue.src);
        const out = join(work, `${name}.wav`);
        const filters = [];
        if (!cue.loop) filters.push('silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.02');
        if (cue.start) filters.push(`atrim=start=${cue.start}`, 'asetpts=PTS-STARTPTS');
        if (cue.duration && !cue.loop) filters.push(`atrim=end=${cue.duration}`, `afade=t=out:st=${Math.max(0, cue.duration - 0.15)}:d=0.15`);
        filters.push(cue.loop ? 'loudnorm=I=-20:TP=-1.5:LRA=11' : 'loudnorm=I=-16:TP=-1.0:LRA=9');
        if (cue.gain) filters.push(`volume=${cue.gain}dB`);
        ffmpeg(['-i', src, '-af', filters.join(','), '-ar', String(RATE), '-ac', '2', out]);
        parts.push({ name, file: out, seconds: duration(out), loop: Boolean(cue.loop) });
    }

    // One timeline: cue, gap, cue, gap…
    const silence = join(work, 'gap.wav');
    ffmpeg(['-f', 'lavfi', '-i', `anullsrc=r=${RATE}:cl=stereo`, '-t', String(GAP), silence]);
    const list = join(work, 'list.txt');
    const sprite = {};
    let t = 0;
    const lines = [];
    for (const p of parts) {
        sprite[p.name] = p.loop ? [Math.round(t * 1000), Math.round(p.seconds * 1000), true] : [Math.round(t * 1000), Math.round(p.seconds * 1000)];
        lines.push(`file '${p.file}'`, `file '${silence}'`);
        t += p.seconds + GAP;
    }
    writeFileSync(list, lines.join('\n'));
    const merged = join(work, 'merged.wav');
    ffmpeg(['-f', 'concat', '-safe', '0', '-i', list, '-c', 'copy', merged]);

    const outDir = join(root, 'public/audio');
    mkdirSync(outDir, { recursive: true });
    ffmpeg(['-i', merged, '-c:a', 'libopus', '-b:a', '96k', join(outDir, `${client}.webm`)]);
    ffmpeg(['-i', merged, '-c:a', 'libmp3lame', '-b:a', '128k', join(outDir, `${client}.mp3`)]);
    // Version the file URLs by content so a browser never pairs an old sheet with a new map.
    const version = createHash('md5').update(readFileSync(join(outDir, `${client}.webm`))).digest('hex').slice(0, 8);
    writeFileSync(join(outDir, `${client}.json`), JSON.stringify({ version, src: [`/audio/${client}.webm?v=${version}`, `/audio/${client}.mp3?v=${version}`], sprite }, null, 2));

    console.log(`${client}: ${parts.length} cues, ${t.toFixed(1)} s`);
    for (const p of parts) console.log(`   ${p.name.padEnd(16)} ${p.seconds.toFixed(2)} s${p.loop ? ' (loop)' : ''}`);
    rmSync(work, { recursive: true, force: true });
}
