# Session 6 — Audio

Paste everything below the line into a fresh Claude Code session opened in this folder.

## Before you start: sound library access

The session needs to fetch sound effects and music itself. Set this up once, before
pasting the prompt. None of these keys are committed; they live in the local `.env`.

1. **Freesound** (CC0 effects, searchable by API). Create an account at
   <https://freesound.org>, then at <https://freesound.org/apiv2/apply> create an API
   credential (any name, any URL). Copy the **client secret / API key** into `.env`:
   ```
   FREESOUND_API_KEY=...
   ```
   The key allows search and download of the high-quality MP3/OGG previews, which is all a
   sprite sheet needs. Downloading originals needs an OAuth browser flow; skip it.
2. **Kenney asset packs** (CC0, no account): nothing to set up. The session fetches zips
   directly from <https://kenney.nl/assets> (Interface Sounds, UI Audio, Casino Audio).
3. **ElevenLabs** (optional, for bespoke stings and the three music loops). Create an
   account at <https://elevenlabs.io>, Profile → API keys → create one, and add:
   ```
   ELEVENLABS_API_KEY=...
   ```
   The free tier covers a training's worth of generation. If you skip this, the session
   uses royalty-free music from Kevin MacLeod (CC BY, attributed in the licence file).
4. **ffmpeg** for trimming and packing: `brew install ffmpeg`.

---

I'm building **Share or Steal** (see `docs/PLAN.md`). Read `docs/sessions/_common.md`, then
the plan's "Sound & animation" section. Sessions 2 (phone) and 3 (screen) are merged and
call a `useAudio()` hook with named cues at every moment in the plan's table; today the hook
is silent. Work on branch `session/6-audio`.

You are **Session 6: Audio**. Make the room sound like a game show.

## Build

1. **Source and licence.** Using the credentials above (`FREESOUND_API_KEY`,
   `ELEVENLABS_API_KEY` in `.env`, Kenney zips), collect every effect in the plan's table plus
   the three music beds (lobby loop, round loop whose intensity steps up for decisions 8–10,
   analysis bed). Prefer CC0. Write `resources/audio/LICENSES.md` listing every file, its
   source URL, author and licence, and keep the originals in `resources/audio/source/`
   (gitignored if large; note where they are).
2. **Sprite sheets.** Trim, normalise loudness, and pack one sprite per client
   (`public/audio/screen.{webm,mp3}`, `public/audio/phone.{webm,mp3}`) with a JSON map of
   offsets, using ffmpeg and a small Node script in `scripts/`. Music loops must be gapless.
3. **Howler wrapper** implementing `useAudio()`: unlock on the first tap (the join tap on
   phones, the first click on the screen page), play by cue name, music layers with
   crossfades, volume ducking under a sting, respects the phone's sound toggle and
   `prefers-reduced-motion` (no change to audio, but no visual pulse to sync to).
4. **Hook every moment** in the plan's table on both clients, at the exact frame the
   contract event arrives, and tune the screen's round loop so its intensity step matches
   decisions 8–10 by reading `state.decision`.
5. **Verify on hardware**: an iPhone in Safari and in the in-app browsers of Slack and
   Messages (audio unlock differs), an Android in Chrome, and the screen page through real
   speakers. Document what needed a workaround.

## Out of scope

New UI. Changing any cue's timing (raise it with a note instead).

## Definition of done

A fixture game on the screen plays the lobby loop, ticks, stings and the fanfare; a phone
with sound on chimes on join and clicks on lock-in; `LICENSES.md` is complete; PR open.
