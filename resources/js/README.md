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

Session 0 ships placeholder pages that show the raw state they receive. They are not the game UI.
