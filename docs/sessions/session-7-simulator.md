# Session 7 — Simulator

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan's "Testing" section (the personality roster is the spec for this session), then
`CONTRACT.md` §10 and §12. Session 1 (engine) is merged; the endpoints and events are real.
Work on branch `session/7-simulator`.

You are **Session 7: Simulator**. Build `php artisan game:simulate`, which plays a complete
thirty-player game against a real server over real HTTP and WebSockets and exits non-zero
if any expected result in the roster didn't happen.

## Build

1. **Personalities** in `App\Simulation\Personalities\`, one class each with
   `choose(History $history): ?Choice` (null = don't answer) and any timing behaviour
   (Speedster answers in 200 ms, Straggler 100 ms after the deadline, Double-tapper submits
   twice, Ghost drops its socket mid-round 3 and reconnects with the same device token,
   Sleeper never answers). Exactly the roster in the plan: 30 players, every archetype and
   award covered.
2. **The client.** A PHP WebSocket client (Ratchet/Pawl or Amp) plus Guzzle, one per player,
   driven by an event loop: join, subscribe to `session.{code}` and `player.{id}`, act on
   `decision.opened`, keep the history from `you.revealed`. Use `scripts/listen.mjs` as the
   reference for channel auth.
3. **Flags:** `--players=N` (drop Pragmatists first below 30), `--anonymous`, `--fast`
   (creates the session in fast mode), `--seed=` for reproducible Wildcards, `--url=` for
   local, staging or production, `--director-key=`. The command creates the session through
   the director endpoint, starts it when everyone has joined, presses Next through the
   analysis, and prints the full analysis at the end.
4. **Assertions** from the roster table, each reported by name: expected archetype per
   personality, expected award winners, Sleeper recorded as all timeouts and nudged,
   Straggler all rejected, Double-tapper's first choice kept, Ghost's missed decisions
   timeouts and nothing else changed, Speedster wins Fastest Thumb. Save the run's
   `player_stats` to `storage/simulations/<timestamp>.json` for threshold tuning.
5. **Run it three ways** and fix what breaks: 30 players fast mode locally; 29 players
   (The Machine appears); against the Cloud URL. Add a CI job that runs it in fast mode
   against a local Reverb if that is achievable in under five minutes; otherwise document
   the manual command.

## Out of scope

Changing game rules or thresholds to make assertions pass (report instead). UI.

## Definition of done

`php artisan game:simulate --fast` passes locally at 30 and 29 players and against
production; failures are named; the stats file is written. PR open with the list of
assertions that were close to their thresholds.
