# Session 2 — Phone

Paste everything below the line into a fresh Claude Code session opened in this folder.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan, then `CONTRACT.md`. Session 0 built the Vue workspace (`resources/js/phone/`,
`shared/`), the Pinia `useGameStore`, Echo wiring and the clock offset. The phone page is a
placeholder that dumps raw state.

You are **Session 2: Phone**. Build the player's phone at `/` and `/play/{code}`, entirely
against the contract. Session 1 (engine) is being built in parallel; until its PR merges,
drive your UI from a scripted fixture, not from the server. Work on branch `session/2-phone`.

## Build

1. **Fixture player.** `resources/js/shared/fixtures/`: a scripted sequence of contract events
   (lobby → pairing → ten decisions with reveals → round summary → analysis beats → ended) and
   a `useFixture()` helper that feeds them to the store on a timer, honouring `deadline_at`
   so countdowns are real. Toggle with `?fixture=1`. Session 3 will reuse it.
2. **Join** (`/`): code and username, remembers both (`sos.last_code`, `sos.username`),
   device token from `shared/device.js`, calls `POST join`, handles every error in §10.2 with
   a human message, and the "game in progress" screen for a late joiner.
3. **Play** (`/play/{code}`), one component per status: lobby (you're in, N players),
   pairing (who you're playing, ten empty decision slots), deciding (two big buttons, Share
   green / Steal red, a draining five-second ring driven by the clock offset, lock-in disables
   and fills the button), revealing (both choices, points, round running total, the
   ten-slot track filling in), round summary, analysis (your card as `you.card` arrives),
   ended (final card). Paused overlay. "Still there?" nudge. Reconnect on load via `GET me`.
4. **Feel.** Vibration via `navigator.vibrate` (short on reveal, double when stolen from),
   a sound toggle in the corner that persists (`sos.sound`, default off) and exposes a
   `useAudio()` hook with named cues (`join`, `tick`, `lockin`, `mutual_share`, `betrayal`,
   `mutual_steal`, `round_end`, `card`, `award`) that Session 6 will implement; for now the
   hook is a no-op that logs. `prefers-reduced-motion` turns every animation into a cut.
   Big touch targets, no double-tap zoom, works in a phone's in-app browser.
5. **Tests.** Vitest for the store's event handling and the clock (add Vitest to the repo,
   `npm test`, and to CI). A visual pass on a real iPhone and a real Android on the same
   Wi-Fi (point `APP_URL`, `REVERB_HOST` and Vite at your LAN IP, or use the Cloud URL).

## Out of scope

The big screen, the director panel, audio files, the engine. Do not compute anything the
server sends you.

## Definition of done

A full fixture game plays through on a phone with correct countdowns; against Session 1's
engine, a real game plays from join to final card, survives a reload mid-decision, and the
DEMO session renders the analysis card. CI green, PR open.
