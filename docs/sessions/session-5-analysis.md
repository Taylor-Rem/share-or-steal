# Session 5 — Analysis

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan's "Analysis & awards" section, then `CONTRACT.md` §11. The stat definitions,
the archetype ladder, the awards and the beat sequence are all specified; your job is to
make them true. Work on branch `session/5-analysis`.

You are **Session 5: Analysis**. Pure functions over the `decisions` table, run once when the
last round ends, stored in `player_stats`, `awards` and `game_sessions.analysis_beats`.

## Build

1. **Stats** per player per the table in the plan and the types in §11.1, in
   `App\Analysis\`. Timeouts excluded where the plan says so. `predictability` is the
   accuracy of the best single rule of (my last move, their last move) → my next move,
   measured over that player's decisions. `partner_yield` averages the partner's points per
   decision. Every rate is `null` when its denominator is zero.
2. **Archetype ladder** in the plan's order, thresholds read from `config('game.thresholds')`
   and nothing else. The Machine gets no archetype.
3. **Awards** per the plan's table and `AwardKey`, with eligibility rules
   (`most_forgiving_min_times_stolen_from`, `kindest_excludes_all_timeouts`, no bots),
   tie-break on total points then a coin flip recorded as `tie_break: "coin_flip"`. Champion
   records places 1–3.
4. **Beats** exactly per §11.3, both normal and anonymous sequences, including `comparison`
   when players' device tokens link to an earlier finished session (share rate, forgiveness,
   betrayals before and after, with a one-line `text`).
5. **Implement `App\Analysis\Analyzer`** (Session 1's contract; if Session 1 hasn't merged
   yet, define the interface yourself in the same namespace and note it) and make the engine
   call it. `GET director/sessions/{code}/analysis` returns what you stored.
6. **Fixtures and tests.** Turn the simulator roster in the plan's Testing section into
   scripted decision logs (a small DSL: a player's move sequence per round against a known
   partner strategy), one fixture per personality, and assert the archetype each one lands
   on plus the award each is expected to win. Assert every stat on at least one hand-checked
   game. Run the DEMO seeder's game through the real analyzer and replace the seeder's
   hand-written numbers with the computed ones.

## Out of scope

Anything a client renders. Anything the engine broadcasts.

## Definition of done

`DemoSessionSeeder` uses the real analyzer; every roster personality's fixture asserts its
archetype and award; a threshold change in `config/game.php` breaks a test rather than a
room; PR open with a short note on which thresholds looked fragile.
