# Session 3 — Big screen

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan, then `CONTRACT.md` with particular attention to §9.1 and §11 (the analysis beats).
Session 5 has merged, so the beat payloads are settled; Session 2's fixture player exists in
`resources/js/shared/fixtures/`.

You are **Session 3: Big screen**. Build `/screen/{code}`: the projector. No controls, no
names in anonymous mode, everything animated. Work on branch `session/3-screen`.

## Build

1. **Lobby**: room code huge, a QR code for the join URL (a small QR library is fine), the
   join URL in text, avatars popping in with each `player.joined` (count only in anonymous
   mode). Locks visibly on `game.started`.
2. **Pairing reveal**: pairs animate together (or a "Round N" card with the count in
   anonymous mode). **During decisions** the screen never shows a live choice: it shows the
   round number, decision index, a countdown bar, the running leaderboard, and a feed that
   plays each `moments` item from `decision.revealed` as it arrives.
3. **Round scoreboard**: leaderboard reorders with movement arrows, biggest betrayal, most
   cooperative pair; anonymous variant shows aggregates only.
4. **Analysis beats**, one component per `type` in §11.3, each with its own entrance:
   room share rate with the comparison to the all-cooperate score; the share-rate-by-decision
   chart drawn live so the endgame cliff appears as it's drawn; archetype reveal as a card
   flip; census; stat leaders as a rotating board; award with a drumroll pause before the
   name; podium with three rising columns; comparison. Anonymous variants per §11.3.
5. **GSAP** for the set pieces (card flips, count-ups, podium rise), CSS transitions for the
   rest. Every beat is one timeline. Wire `useAudio()` cues at the exact moments in the
   plan's sound table so Session 6 only has to supply files. Paused overlay. Reconnect on
   load. Full-screen friendly, 16:9 and 16:10, readable from the back of a room.
6. **Long usernames** get clipped gracefully. Test with 30 players and 24-character names.

## Out of scope

The phone, the director panel, audio files, anything server-side.

## Definition of done

The fixture plays a whole game on the screen with every beat animated; the DEMO session
renders the analysis from `analysis_beat` 0 through the podium by pressing Next in the
director panel (or hitting the endpoint); anonymous mode shows no names anywhere. CI green,
PR open.
