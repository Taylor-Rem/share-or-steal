# Session 8 — Integration

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan's "Testing" (layers 3 and 4), "Game day" and "Still open" sections. Every other
session has merged. Work on branch `session/8-integration`, merging small fixes as you go.

You are **Session 8: Integration**. Watch whole games, fix what breaks, tune, polish, and
get the rehearsal and game day ready.

## Do

1. **Eyes on it.** `game:simulate --fast` supplying the players, big screen in one tab,
   director panel in another, one real iPhone and one real Android as extra players. Watch
   at least five full games. Check the plan's list explicitly: sound-before-reveal timing,
   leaderboard reorder, long usernames, anonymous-mode screen variants, pause and resume
   mid-decision, the Ghost reconnect on a real phone (airplane mode for 15 s), the
   director's phone going to sleep.
2. **Tune thresholds** in `config/game.php` from the simulator's saved stats so the roster
   lands on its archetypes with margin and a room doesn't collapse into Pragmatists. Record
   the before/after in the PR.
3. **Polish** whatever felt wrong, in priority order of what the room will notice.
4. **Rehearsal kit**: `docs/REHEARSAL.md` with the run sheet (what to watch for, per the
   plan), a feedback form, and the command to snapshot the session's data afterwards.
   After the rehearsal, retune from real data and adjust the simulator roster to match what
   people actually did.
5. **Game day**: `docs/GAME-DAY.md` as a checklist: turn scale-to-zero off (compute and
   database) the morning of, confirm the Reverb cluster, run the simulator against
   production as the smoke test, projector and speaker setup, the backup plans from the
   plan, and the tear-down (delete the Reverb cluster, leave the data). Time the whole run
   and confirm it fits twenty minutes with the analysis at a comfortable pace.

## Out of scope

New features. If something in the plan turns out to be a bad idea in the room, write it up
and propose the change rather than quietly building around it.

## Definition of done

Five clean fast-mode games with real phones, thresholds tuned and documented, both
checklists written, rehearsal scheduled at least a week before the training, and a final
production smoke test green.
