# Build sessions

Each file here is the prompt for one build session, meant to be pasted into a fresh Claude
Code session opened in this folder. Sessions are numbered as in the build plan
(`docs/PLAN.md`, "Build plan"). `_common.md` holds the working notes every prompt refers to.

| Session | Prompt | Can start when | Branch |
|---|---|---|---|
| 0 · Foundation | `session-0-foundation.md` | Done | `main` |
| 1 · Engine | `session-1-engine.md` | Now | `session/1-engine` |
| 2 · Phone | `session-2-phone.md` | Now (fixture-driven until 1 merges) | `session/2-phone` |
| 4 · Director | `session-4-director.md` | Now (mocked until 1 merges) | `session/4-director` |
| 5 · Analysis | `session-5-analysis.md` | Now | `session/5-analysis` |
| 3 · Big screen | `session-3-screen.md` | After 5 merges (uses 2's fixture) | `session/3-screen` |
| 7 · Simulator | `session-7-simulator.md` | After 1 merges | `session/7-simulator` |
| 6 · Audio | `session-6-audio.md` | After 2 and 3 merge; needs the sound-library keys in its header | `session/6-audio` |
| 8 · Integration | `session-8-integration.md` | After everything else | `session/8-integration` |

Every push to `main` deploys to production, so sessions work on their branch and merge
through a pull request once CI is green and the definition of done is met. Sessions 1, 2, 4
and 5 can run at the same time; the contract is what keeps them from colliding.
