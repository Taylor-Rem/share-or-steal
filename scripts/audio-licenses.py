#!/usr/bin/env python3
"""Regenerate resources/audio/LICENSES.md from the manifest and the Freesound metadata
saved by the fetches (resources/audio/source/freesound/meta.json)."""
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
meta = json.load(open(root / 'resources/audio/source/freesound/meta.json'))
man = json.load(open(root / 'resources/audio/manifest.json'))

used = {}
for client, cues in man.items():
    if client.startswith('_'):
        continue
    for cue, c in cues.items():
        used.setdefault(c['src'], []).append(f'{client}:{cue}')

def licence(url):
    if 'publicdomain/zero' in url:
        return 'CC0 1.0'
    if 'licenses/by-nc/' in url:
        return 'CC BY-NC 4.0 (attribution required, non-commercial use)'
    if 'licenses/by/' in url:
        return 'CC BY 4.0 (attribution required)'
    return url

lines = ['# Audio licences', '',
'Every sound the game plays, where it came from, and its licence. Sources are kept in',
'`resources/audio/source/` (gitignored; re-fetch with the notes below) and packed into',
'`public/audio/{screen,phone}.{webm,mp3}` by `scripts/audio-pack.mjs` from `resources/audio/manifest.json`.',
'Regenerate this file with `python3 scripts/audio-licenses.py`.', '',
'Effects are CC0. Two music beds are CC BY-NC 4.0: their authors are credited below, and the game',
'is used for an internal, non-commercial training. Do not ship those two in anything sold.', '',
'## Freesound', '',
'Fetched as the high-quality MP3 previews through the Freesound API (`FREESOUND_API_KEY`).', '',
'| Cue(s) | File | Sound | Author | Licence |', '|---|---|---|---|---|']
for name, m in meta.items():
    key = 'freesound/' + m['file'].split('/')[-1]
    cues = ', '.join(used.get(key, []))
    if not cues:
        continue
    lines.append(f"| {cues} | `{key}` | [{m['name']}]({m['url']}) | {m['username']} | {licence(m['license'])} |")
lines += ['', '## Kenney (CC0)', '',
'From <https://kenney.nl/assets>: *Interface Sounds*, *UI Audio* and *Casino Audio*, all CC0 1.0 (see `License.txt` in each zip).',
'', '| Cue(s) | File | Pack |', '|---|---|---|']
for src, cues in sorted(used.items()):
    if src.startswith('kenney/'):
        pack = src.split('/')[1].replace('kenney_', '').replace('-', ' ').title()
        lines.append(f"| {', '.join(cues)} | `{src}` | {pack} |")
lines += ['', '## Not used', '',
'ElevenLabs was not used (`ELEVENLABS_API_KEY` is not set). Files in `source/freesound/` that no cue references are candidates that were auditioned and dropped.',
'', '## Re-fetching', '', '```bash',
'# Kenney packs',
'cd resources/audio/source/kenney && for p in interface-sounds ui-audio casino-audio; do curl -sL "$(curl -sL https://kenney.nl/assets/$p | grep -oE \'https://kenney\\.nl/[^"]*\\.zip\' | head -1)" -o kenney_$p.zip && unzip -qo kenney_$p.zip -d kenney_$p; done',
'# Freesound previews: the ids are in the table above',
'curl -sL "https://freesound.org/apiv2/sounds/<id>/?token=$FREESOUND_API_KEY&fields=previews"   # then fetch previews.preview-hq-mp3',
'```']
(root / 'resources/audio/LICENSES.md').write_text('\n'.join(lines) + '\n')
print('wrote LICENSES.md with', sum(1 for l in lines if l.startswith('| ') and 'freesound/' in l), 'freesound rows')
