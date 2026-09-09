# Rehearsal

At least a week before the training: six to eight colleagues on their own phones, on the
office Wi-Fi, with the real projector and speakers, at real speed (not fast mode). Keep the
session's data; it is the baseline the thresholds get tuned against.

## Setup (10 minutes before)

1. Either production (<https://share-or-steal-production-lh9czz.laravel.cloud>, run the
   Game Day morning list first) or local (`composer dev`, phones on the LAN address).
2. Director: open `/director` on your phone, unlock with the password, **Create session**
   (normal mode, 5 × 10, fast mode off). Leave the panel open.
3. Projector: open the **Screen** URL from the create card in a full-screen browser, click
   once to enable sound, set the room's volume from the lobby loop.
4. Put the join URL and QR on the screen. Ask everyone to join and pick a name (one person
   should pick a 24-character name on purpose).

## Run sheet: what to watch

| Moment | Watch for | Plan says |
|---|---|---|
| Joining | Every phone chimes on the join tap with sound on; avatars pop on the screen; a name that is too long clips cleanly on the screen and stays whole on the phone. | Long usernames clip gracefully. |
| Start → pairing | The lobby locks; pairs animate on the screen; each phone shows its partner. | 8 s pairing reveal. |
| Deciding | **Is five seconds enough** for people who are not staring at the phone? Count how many time out on decisions 1–3 versus later. Ticks audible, last two louder. | Six or seven may feel better. |
| Reveal | The sting/chime/thud lands *with* the reveal, not before or after. Phones buzz (Android) on reveal, double-buzz when stolen from. | Sound-before-reveal timing. |
| Feed and leaderboard | Moments read as sentences from the back of the room; the leaderboard rows slide to their new places; arrows make sense. | Leaderboard reorder. |
| Round scoreboard | Biggest betrayal and most cooperative pair are the ones people are talking about. | 12 s scoreboard. |
| Pause | Pause mid-decision from the director's phone: phones and screen show the overlay, the countdown freezes; resume: it picks up with the same seconds left. | Pause and resume mid-decision. |
| Ghost | One person turns on airplane mode for 15 s during a round, then off: the phone comes back on the current decision, the missed ones are timeouts, nothing else changes. | Ghost reconnect. |
| Director's phone | Let it go to sleep for a minute mid-round; wake it: the panel catches up and the game never paused. | The clock lives on the server. |
| Analysis | Does it hold attention or drag? Note which beats got a reaction. Time each Next. | ~7 minutes budgeted. |
| Archetypes | Is the spread interesting, or is it all Pragmatists and Mirrors? Ask two people whether their card felt right. | Tune thresholds. |
| Podium and end | Fanfare, columns rise, phones show the final card. | |

Note the time at start, at the first analysis beat, and at the podium.

## Feedback form

Hand this out (or read it aloud) right after the podium:

1. Was five seconds enough to choose? Too little, about right, too long.
2. Did you ever not know what to do? When?
3. Did your archetype card feel right? Which would you have picked?
4. What did you look at more: your phone or the screen?
5. Which moment got the biggest reaction in the room?
6. Which part of the analysis dragged?
7. Anything the sound did that annoyed you?
8. Would you play again anonymously? Why?

## Afterwards

Snapshot the data before anyone touches the session again:

```bash
php artisan game:export CODE            # -> storage/simulations/<date>_CODE_export.json
```

(On Cloud, run it from the Commands tab and download the file; or `curl` the director
analysis endpoint with `X-Director-Key` for the stats, awards and beats only.)

Then retune against what real people did:

1. Run the simulator's threshold scoring over the rehearsal session and the saved
   simulator runs: `php artisan tinker --execute="require 'scripts/tune-thresholds.php';"`
   with the codes at the top of that file. It prints, for each candidate threshold set,
   where every player lands and the largest group per room.
2. Change `config/game.php` → `thresholds`, run `php artisan test`, and re-pin the
   snapshot in `tests/Feature/AnalyzerRosterTest.php` (the test prints the new map).
3. If real people behaved unlike the roster (a common one: nobody is a pure Wall), adjust
   the personalities in `app/Simulation/Personalities/` to match, so the simulator keeps
   measuring the real room.
4. Remember `game:run` reads `config/game.php` once at start: restart it (a Cloud deploy
   does) or a threshold change does nothing.
