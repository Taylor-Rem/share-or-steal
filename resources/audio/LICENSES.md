# Audio licences

Every sound the game plays, where it came from, and its licence. Sources are kept in
`resources/audio/source/` (gitignored; re-fetch with the notes below) and packed into
`public/audio/{screen,phone}.{webm,mp3}` by `scripts/audio-pack.mjs` from `resources/audio/manifest.json`.
Regenerate this file with `python3 scripts/audio-licenses.py`.

Effects are CC0. Two music beds are CC BY-NC 4.0: their authors are credited below, and the game
is used for an internal, non-commercial training. Do not ship those two in anything sold.

## Freesound

Fetched as the high-quality MP3 previews through the Freesound API (`FREESOUND_API_KEY`).

| Cue(s) | File | Sound | Author | Licence |
|---|---|---|---|---|
| screen:betrayal, phone:betrayal | `freesound/betrayal-timpani-sting-578588.mp3` | [Timpani Sting](https://freesound.org/people/nomiqbomi/sounds/578588/) | nomiqbomi | CC0 1.0 |
| screen:mutual_steal, phone:mutual_steal | `freesound/thud-653370.mp3` | [DullThud.wav](https://freesound.org/people/TriqyStudio/sounds/653370/) | TriqyStudio | CC0 1.0 |
| screen:round_end, phone:round_end | `freesound/fanfare-677858.mp3` | [Game Success Fanfare Short](https://freesound.org/people/el_boss/sounds/677858/) | el_boss | CC0 1.0 |
| screen:award_hit | `freesound/cymbal-151811.mp3` | [Orch 003 crash.wav](https://freesound.org/people/Karma-Ron/sounds/151811/) | Karma-Ron | CC0 1.0 |
| screen:join | `freesound/pop-744144.mp3` | [bubble pop 6](https://freesound.org/people/nomentero/sounds/744144/) | nomentero | CC0 1.0 |
| screen:mutual_share, phone:mutual_share | `freesound/chime-bell-625174.mp3` | [[UI Sound] Approval - High Pitched Bell Synth](https://freesound.org/people/GabFitzgerald/sounds/625174/) | GabFitzgerald | CC0 1.0 |
| screen:card | `freesound/riser-hit-754771.mp3` | [Riser Hit sfx 097](https://freesound.org/people/AudioPapkin/sounds/754771/) | AudioPapkin | CC0 1.0 |
| screen:award | `freesound/drumroll-smooth-440829.mp3` | [Sting, rimshot, drum roll (smooth)](https://freesound.org/people/tlwmdbt/sounds/440829/) | tlwmdbt | CC0 1.0 |
| screen:podium, phone:award | `freesound/tadaa-415504.mp3` | [Tadaa.wav](https://freesound.org/people/Exchanger/sounds/415504/) | Exchanger | CC0 1.0 |
| screen:music_intense | `freesound/round-industrial-drums-849726.mp3` | [120 BPM Industrial Drum Loop #16197 (WAV)](https://freesound.org/people/looplicator/sounds/849726/) | looplicator | CC0 1.0 |
| screen:music_lobby | `freesound/lobby-foolboy-347848.mp3` | [New York Jazz Loop](https://freesound.org/people/FoolBoyMedia/sounds/347848/) | FoolBoyMedia | CC BY-NC 4.0 (attribution required, non-commercial use) |
| screen:music_round | `freesound/round-69611-69611.mp3` | [Mix0786BPM.wav](https://freesound.org/people/deleted_user_984625/sounds/69611/) | deleted_user_984625 | CC0 1.0 |
| screen:music_analysis | `freesound/analysis-sergequadrado-786249.mp3` | [Funky and Jazzy Gang Loop](https://freesound.org/people/SergeQuadrado/sounds/786249/) | SergeQuadrado | CC BY-NC 4.0 (attribution required, non-commercial use) |

## Kenney (CC0)

From <https://kenney.nl/assets>: *Interface Sounds*, *UI Audio* and *Casino Audio*, all CC0 1.0 (see `License.txt` in each zip).

| Cue(s) | File | Pack |
|---|---|---|
| phone:card | `kenney/kenney_casino-audio/Audio/card-place-1.ogg` | Casino Audio |
| screen:tick_loud | `kenney/kenney_interface-sounds/Audio/bong_001.ogg` | Interface Sounds |
| phone:join | `kenney/kenney_interface-sounds/Audio/confirmation_002.ogg` | Interface Sounds |
| screen:tick, phone:tick | `kenney/kenney_interface-sounds/Audio/tick_004.ogg` | Interface Sounds |
| phone:lockin | `kenney/kenney_ui-audio/Audio/click1.ogg` | Ui Audio |

## Not used

ElevenLabs was not used (`ELEVENLABS_API_KEY` is not set). Files in `source/freesound/` that no cue references are candidates that were auditioned and dropped.

## Re-fetching

```bash
# Kenney packs
cd resources/audio/source/kenney && for p in interface-sounds ui-audio casino-audio; do curl -sL "$(curl -sL https://kenney.nl/assets/$p | grep -oE 'https://kenney\.nl/[^"]*\.zip' | head -1)" -o kenney_$p.zip && unzip -qo kenney_$p.zip -d kenney_$p; done
# Freesound previews: the ids are in the table above
curl -sL "https://freesound.org/apiv2/sounds/<id>/?token=$FREESOUND_API_KEY&fields=previews"   # then fetch previews.preview-hq-mp3
```
