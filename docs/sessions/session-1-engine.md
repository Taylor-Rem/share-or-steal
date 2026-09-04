# Session 1 — Engine

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Session 0 laid the foundation:
data model, `config/game.php`, channels, identity, a stub `game:run`, and `CONTRACT.md`.
Read `docs/sessions/_common.md` for the working notes, then the plan, then the contract.

You are **Session 1: Engine**. You own everything server-side that makes a game actually
run. Build it exactly to `CONTRACT.md` §4 (state machine), §9 (events), §10 (endpoints)
and §12 (reconnection). Work on branch `session/1-engine`.

## Build

1. **Session and player lifecycle.** `POST join` with all the rules in §10.2 (username
   uniqueness, device-token re-join, late joiner with `is_admitted: false`, full, finished).
   `GET me` as the reconnect snapshot. Director `start`, `pause`, `resume`, `next`, `end`,
   `admit`, `kick`, `screen/reload`, `login`, `sessions` list, `sessions` create, session
   detail (§10.3). Every mutation returns the new `state` and broadcasts its event.
2. **Pairing.** Random partners each round; no repeat partners at or above
   `avoid_repeat_partners_from` players, allowed below; The Machine fills an odd seat and
   rotates so nobody draws it twice; per-round codenames in anonymous rounds, unique within
   the round.
3. **The clock.** Fill in `GameRunCommand::tick()`: every `tick_ms`, load sessions in a timed
   status whose `phase_ends_at <= now`, and apply the transition inside a transaction with
   `lockForUpdate()`. Open decisions with `opened_at`/`deadline_at`, score at the deadline
   (timeouts become `share` with `timed_out`), reveal, next decision, round summary, next
   round, and hand off to analysis after the last round. Respect `fast_mode`.
4. **Choices.** `POST choice` with the rejection ladder in §10.2, in that order. First choice
   wins. `response_ms` measured on the server. The Machine's move is computed at scoring time
   (tit-for-tat: share first, then copy the partner's last move).
5. **Events.** Every event in §9 with its exact payload, normal and anonymous variants, via
   the `GameBroadcast` base class. Moments (`betrayal`, `mutual_steal`, `mutual_share_streak`,
   `comeback`) and leaderboard `movement` are yours. `you.nudged` after
   `nudge_after_timeouts`. `director.player_updated` on join, admit, kick, timeout changes.
6. **Analysis hand-off.** Define an `App\Analysis\Analyzer` contract with one method that takes
   a `GameSession` and writes `player_stats`, `awards` and `analysis_beats`. Ship a stub that
   produces total points, rank and a two-beat sequence (`room_share_rate`, `podium`) so the
   game can finish end to end. Session 5 replaces the stub. `next` walks the beats and sends
   each beat's `private` map as `you.card`.
7. **Tests.** Scoring as a four-case table. Pairing at 20, 21, 25 and 30 players across five
   rounds: one partner per person per round, no repeats above ten players, The Machine present
   only on odd counts and never twice on the same person. Choice rejection ladder, one test per
   reason. A full fast-mode game driven by calling `tick()` in a loop with `Carbon::setTestNow`,
   asserting the status sequence and that every decision row is scored. Channel events
   asserted with `Event::fake`.

## Out of scope

Any UI. Real stats, archetypes or awards (Session 5). Audio. The simulator (Session 7),
though keep the endpoints friendly to it: the simulator is thirty HTTP clients plus
WebSockets, nothing more.

## Definition of done

`composer dev` running, `php artisan game:run` advancing a session created through the
director endpoint, thirty `scripts/listen.mjs` clients (or a quick loop of `curl` joins)
playing a fast-mode game to the podium with no manual intervention, every event in §9
observed on the wire in the contract shape, Pest green, PR open.
