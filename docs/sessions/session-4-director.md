# Session 4 — Director

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan, then `CONTRACT.md` §10.3 (director endpoints) and §9.4 (director channel).
Session 1 is building the endpoints in parallel; until it merges, mock them with MSW or a
simple fixture layer so the panel is fully clickable.

You are **Session 4: Director**. Build `/director` and `/director/{code}`: my control panel,
usable on a phone in one hand while I talk. Work on branch `session/4-director`.

## Build

1. **Password gate**: `POST login`, key kept in `sos.director_key`, sent as `X-Director-Key`
   on every request and on channel auth. Wrong key shows a clear message.
2. **Home** (`/director`): create a session (mode normal/anonymous, rounds, decisions per
   round, fast mode, max players; defaults from the create response), showing the room code
   and the four URLs from the response with copy buttons; a list of past sessions with
   status, player count, date, and a link into each.
3. **Control panel** (`/director/{code}`): the big state line (status, round, decision,
   countdown), one obvious primary button that is whatever the game needs next (Start /
   Pause / Resume / Next beat / End), all with confirmation for the destructive ones. Player
   list from `GET sessions/{code}` and `director.player_updated`: connection age, consecutive
   timeouts, points, admit and kick. A "Reload screen" button (`screen/reload`). In
   analysis, show the current beat type and index out of the count, and what the next beat
   will be, so I can pace the room.
4. **History and analysis view**: `GET sessions/{code}/analysis` rendered as tables (stats
   per player, awards, the beat list), so I can look at a past session's numbers when tuning
   thresholds.
5. **Robustness**: reconnects like everything else; a lost connection is obvious; every
   command's failure (`409` reasons) is shown, never swallowed.

## Out of scope

Game rules, the phone, the screen, audio.

## Definition of done

I can run a complete game from my phone: create, watch players join, start, pause and
resume mid-decision, step through every analysis beat, end; kick and admit work; the DEMO
session's analysis view shows its stats and awards. CI green, PR open.
