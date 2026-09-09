# Game day

## The morning of

- [ ] Laravel Cloud → production environment: turn **scale to zero off** on compute *and*
      on the Postgres database (it sleeps after 300 s). Or open the app an hour early and
      keep the screen tab alive; the cold start is the thing to avoid.
- [ ] Confirm the **Reverb cluster** is up (Cloud dashboard) and that a page connects:
      `node scripts/listen.mjs DEMO --url=<prod> --ws=wss://<reverb host>:443 --key=<REVERB_APP_KEY>`
      then `game:ping DEMO` from the Commands tab; the listener should print it.
- [ ] Run the **simulator against production** as the smoke test (about four minutes):
      ```bash
      php artisan game:simulate --fast --seed=1 \
        --url=https://share-or-steal-production-lh9czz.laravel.cloud \
        --ws=wss://ws-a2aade02-de75-4607-9deb-e80ea30a44c2-reverb.laravel.cloud:443 \
        --key=<VITE_REVERB_APP_KEY from the Cloud env> \
        --director-key=<the Cloud DIRECTOR_PASSWORD>
      ```
      Expect `OK: 84 checks passed`. It leaves a finished session behind; that is fine.
- [ ] If `config/game.php` changed since the last deploy, deploy: `game:run` reads the
      config once at start and a deploy restarts it.
- [ ] Charge the director's phone. Put the director URL and password somewhere you can
      read them without the phone.

## In the room (30 minutes before)

- [ ] Projector: open `/screen/{code}` in a full-screen browser (F11 / ⌃⌘F), **click once**
      to enable sound, route the browser's audio through the room's speakers, set the
      volume from the lobby loop. Nothing else runs on that machine.
- [ ] Director: `/director` on your phone → create the session (normal mode, 5 × 10) →
      open the control panel. The code and QR are on the screen from the create card.
- [ ] Put the join URL up five minutes before start. Ask people to keep the tab open and to
      turn their ringer on if they want sound (it defaults to vibration).
- [ ] Wi-Fi or signal for everyone. Thirty WebSocket connections are nothing; a dead zone
      is a dead zone.

## Running it

| Phase | How long | You do |
|---|---|---|
| Lobby | ~3 min | Watch the count on the screen and the list on your panel. Kick a duplicate if someone joined twice. |
| Rounds 1–5 | ~2 min each, 10 min | Nothing. The server runs the clock. Pause only if the room needs your attention. Admit a latecomer from the panel; they join at the next round. |
| Analysis | see below | Press **Next** per beat. The panel names what is on screen and what is next. |
| Podium | | Last Next is **Finish game** (tap twice). |

**Analysis pacing.** The beat count is 5 + one per player + up to 9 awards + the podium:
25 players is about 40 beats. At 10 s a beat that is 7 minutes; at a comfortable 15 s it is
10. Total run: 3 + 10 + 7…10 = **20 to 23 minutes**. To land on twenty, move through the
archetype reveals at about 8 s each (the card flip takes 2 s) and linger on the chart,
the census and the podium instead. If that still feels long in the rehearsal, the change
to propose is revealing archetypes three to a beat rather than one.

## If something breaks

- A phone won't connect: they pair with a neighbour and call their moves.
- A phone reloads or comes back from a lock screen: it lands on the current decision by
  itself; missed decisions are timeouts. Nothing to do.
- The director's phone sleeps or loses signal: the game keeps running; the panel catches up
  on wake. Reload it if the state line looks stale.
- The screen looks stuck: Reload screen from the panel (or F5). It reconnects mid-analysis on
  the current beat.
- The whole thing dies: The Machine can be drafted into a chat-based version in about
  thirty seconds (post the matrix, pair people, collect moves by hand).

## Afterwards

- [ ] `php artisan game:export CODE` from the Commands tab (or the director analysis
      endpoint) and keep the file with the rehearsal's; it is the baseline for an
      anonymous replay and for tuning.
- [ ] Delete the Reverb cluster (Cloud dashboard → Reverb) and turn scale to zero back on.
      Leave the database: the session data stays for the anonymous replay's comparison.
