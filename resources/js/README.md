# Front end layout

| Folder | Entry | Served at | Purpose |
|---|---|---|---|
| `phone/` | `phone/main.js` | `/`, `/play/{code}` | The player's phone. |
| `screen/` | `screen/main.js` | `/screen/{code}` | The projector. |
| `director/` | `director/main.js` | `/director`, `/director/{code}` | The control panel. |
| `shared/` | — | — | Echo + API client, clock offset, and the Pinia `useGameStore` every entry uses. |

Each entry is its own Vue 3 app with its own Vue Router and a Pinia instance, but all three
import the same `shared/stores/game.js`. Everything a client knows about the game arrives
either from `GET /api/sessions/{code}` or from a broadcast; see `CONTRACT.md` at the repo root.

The phone (`phone/`) is real: `pages/Home.vue` joins, `pages/Play.vue` reconnects from
`GET me` and renders one component per status from `components/`. `shared/fixtures/` is a
scripted game in the contract's shapes; `?fixture=1` on `/play/{code}` plays it without a
server (`?fixture=fast` for fast clocks). `shared/audio.js` exposes `useAudio()` with the
named cues Session 6 implements; `shared/haptics.js` wraps `navigator.vibrate`.

The director (`director/`) is real too: `useDirector.js` keeps the key and wraps the § 10.3
endpoints, `logic.js` holds the pure rules (which button, how to name a beat), and the
three pages are the gate + create/history, the control panel, and the analysis tables.

The screen page is still a Session 0 placeholder showing raw state.
