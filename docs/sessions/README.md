# Build sessions

Each file here is the prompt for one build session, meant to be pasted into a fresh Claude Code session opened in this folder. Sessions are numbered as in the build plan (`docs/PLAN.md`, "Build plan").

| Session | Prompt | Can start when |
|---|---|---|
| 0 · Foundation | `session-0-foundation.md` | Now |
| 1 · Engine | to be written | `CONTRACT.md` exists |
| 2 · Phone | to be written | `CONTRACT.md` exists |
| 4 · Director | to be written | `CONTRACT.md` exists |
| 5 · Analysis | to be written | `CONTRACT.md` exists |
| 3 · Big screen | to be written | Session 5 has settled the analysis payloads |
| 7 · Simulator | to be written | Session 1 is merged |
| 6 · Audio | to be written | Sessions 2 and 3 are merged |
| 8 · Integration | to be written | Everything else |

Every prompt after Session 0 should be short: point the session at `docs/PLAN.md` and `CONTRACT.md`, name the scope from the build-plan table, list what is explicitly out of scope, and state a definition of done.
