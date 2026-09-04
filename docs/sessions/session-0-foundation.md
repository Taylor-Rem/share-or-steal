# Session 0 — Foundation

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal**, a Jackbox-style game for a 20-minute game-theory training. Attendees (20–25 people, support up to 30) open a URL on their phones, enter a room code, pick a username, and play an iterated prisoner's dilemma against a big screen I run from a director panel. The game is followed by a director-paced analysis phase that assigns every player an archetype and hands out awards. The full plan is in `docs/PLAN.md`. Read it first, all of it, and treat it as the spec.

You are **Session 0: Foundation**. Other sessions will build the engine, phone client, big screen, director panel, analysis, audio, and simulator in parallel, so your job is the skeleton and the contract they all build against. Do not build game logic, UI, animation, or audio beyond what's listed here.

## Locked decisions (do not re-litigate)

- Payoffs: both share 3/3; share vs steal 0/5; both steal 1/1. Share is green, Steal is red.
- 5 rounds × 10 decisions per round. Each decision: 5 s to choose, 5 s reveal with a countdown to the next decision. Random new partner every round; same partner for all 10 decisions in a round; partner's username and each choice are visible in normal mode.
- Anonymous mode is a session-level toggle. Partners appear as per-round codenames; the big screen shows aggregates only; individual results go to phones. Anonymity is a flag on the round in the data model, so a mixed mode is possible later.
- No tap within 5 s counts as Share with a `timed_out` flag.
- Odd player count: a bot named "The Machine" plays tit-for-tat, is labeled as a bot, and is excluded from awards.
- Server owns the game clock. A long-running `game:run` process advances all active sessions on a ~250 ms loop; decisions carry a `deadline_at`; late choices are rejected; first choice wins, later submissions for the same decision are ignored.
- Phones keep a `device_token` in localStorage that identifies the player across sessions (for the normal-vs-anonymous comparison later) and lets them reconnect mid-game.
- Sessions have a `fast_mode` setting (1 s choose, 1 s reveal, 3 s scoreboards) for testing.
- Stack: Laravel 12 on PHP 8.4, serverless Postgres, Laravel Reverb + Echo for realtime, Vue 3 + Vite + Pinia + Vue Router (no Inertia, no Livewire), deployed on Laravel Cloud (Flex compute). Three front-end entry points sharing one store: `/play/{code}` (phone), `/screen/{code}` (projector), `/director` and `/director/{code}` (my control panel, password-protected).

## What to build

1. **Laravel app** with the migrations and models from the plan's data model: `sessions`, `players`, `rounds`, `pairings`, `decisions`, `player_stats`, `awards`. Include factories and a seeder that creates a sample session with 6 players, 2 completed rounds of decisions, and stats, so front-end sessions have real-shaped data to render immediately.
2. **`config/game.php`**: every tunable in one place — rounds, decisions per round, choose/reveal/scoreboard durations for normal and fast mode, payoffs, max players, bot name, and a `thresholds` block for the archetype ladder with the values from the plan.
3. **Reverb and Echo wired end to end**, with private channels per session (`session.{code}`) and per player (`player.{id}`), plus a `screen.{code}` and `director.{code}` channel. Ship a `php artisan game:ping {code}` command that broadcasts a test event, and a placeholder page on each of the three entry points that shows the raw state it receives, so we can prove a broadcast reaches a phone before anything else is built.
4. **`CONTRACT.md`** at the repo root. This is the most important deliverable. It must define, precisely enough that a session with no other context could implement against it:
   - The session state machine: `lobby → pairing → deciding → revealing → round_summary → (next round | analysis) → analysis_beat[n] → finished`, plus `paused` as an overlay state, with the transition rules and who triggers each (server clock vs director command).
   - Every broadcast event, its channel, and its full payload with types and an example. At minimum: player joined/left, game started, pairing revealed, decision opened (with `deadline_at`), decision revealed (choices, points, running totals, timeout flags), round summary, analysis beat, paused/resumed, session ended, and the private per-player variants used in anonymous mode.
   - Every director command as an HTTP endpoint with request/response shapes: create session, start, pause, resume, next beat, admit player, kick player, end session.
   - The player endpoints: join, submit choice (with the server's rejection rules), reconnect by device token.
   - Clock semantics: the server sends a `server_time` with every event so clients compute a one-time offset and count down toward `deadline_at` correctly on phones with wrong clocks.
   - The analysis payload shapes (stats, archetype, awards, beat sequence), even though Session 5 computes them — define the shape now so Session 3 can render against it.
5. **Repo hygiene**: a `README.md` with one-command local setup (Sail or Herd, your call, say which), how to run Reverb and `game:run` locally, how to run tests, and the Laravel Cloud deployment notes. Add Pest, Pint, and a GitHub Actions workflow that runs both. Put the Vue app under `resources/js` with `phone/`, `screen/`, `director/`, and `shared/` folders and a Pinia store in `shared/` that every entry point uses.
6. **Deploy**: prepare everything Laravel Cloud needs (env template, `reverb` config, a background process definition for `game:run`, Postgres). Stop and ask me before doing anything that needs my Cloud credentials; I'll handle the dashboard steps and tell you when it's live, then verify `game:ping` reaches a phone in production.

## Definition of done

- `php artisan migrate --seed` works from a clean clone and Pest passes.
- Opening the three placeholder pages locally and running `game:ping` shows the event on all three.
- `CONTRACT.md` is complete enough that you would be comfortable handing it to someone who has never seen the plan.
- Nothing in the repo pretends to be finished game UI. Placeholder screens should say so.

Work in small commits with clear messages. If the plan is ambiguous anywhere that affects the contract, make a reasonable call, write it down in a "Decisions made in Session 0" section at the bottom of `CONTRACT.md`, and tell me at the end.
