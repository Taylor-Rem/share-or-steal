# Share or Steal — Training Plan

A Jackbox-style prisoner's dilemma played on phones against a big screen, followed by a director-paced analysis that tells every player what kind of game theorist they turned out to be. The game is the whole training.

| | |
|---|---|
| Expected players | 20–25 (supports 30) |
| Structure | 5 rounds × 10 decisions per round |
| Decision clock | 10 s (5 s choose, 5 s reveal) |
| Total runtime | ~20 min, lobby to final award |

---

## Rules

Each decision, both players in a pair privately tap **Share** (green) or **Steal** (red). Points come from the classic matrix, with the sucker payoff at 0 so betrayal actually costs something.

| | They share | They steal |
|---|---|---|
| **You share** | 3 · 3 (both cooperate) | 0 · 5 (you get 0, they get 5) |
| **You steal** | 5 · 0 (you get 5, they get 0) | 1 · 1 (both defect) |

A round is one partner for ten decisions in a row, and players see their partner's name and choice after every decision. That turns this from a one-shot dilemma into an iterated one: reputation, retaliation, and forgiveness all have room to happen, and they're what the analysis phase measures. Maximum possible score is 250 (steal against a saint for 50 decisions); a room full of cooperators lands everyone at 150.

---

## How a game plays out

Every decision runs on a fixed ten-second clock that the server owns. Phones and the big screen just render it.

```
|------ Choose 5.0 s ------|--- Reveal + next-decision countdown 5.0 s ---|
```

1. **Director creates a session** (before the room fills). From the director panel: pick normal or anonymous mode, confirm rounds and decisions (defaults 5 × 10), and get a four-letter room code. The big screen shows the code, a QR code, and the join URL.
2. **Players join** (~3 min). Attendees open the URL on their phones, enter the code, pick a username, and pop onto the big screen with a join sound. The "tap to join" gesture also unlocks audio on the phone. The director starts the game when the room is in; the lobby locks.
3. **Pairing reveal** (8 s). Everyone is randomly matched. The big screen animates the pairs; each phone shows "You're playing against Jordan" with the ten-decision track empty.
4. **Ten decisions** (100 s). Phones show two big buttons and a five-second ring. Lock-in gives a click and disables the buttons. At zero, the server scores the decision and pushes the reveal to both phones: what each side chose, points earned, running total for the round. The big screen never shows a live decision, only the round scoreboard and a feed of notable moments once each reveal is public.
5. **Round scoreboard** (12 s). Big screen: leaderboard with movement arrows, the round's biggest betrayal, the most cooperative pair. Phones: your round summary. Then back to step 3 with fresh random pairs, avoiding repeat partners where the player count allows.
6. **Analysis** (director-paced). After round five the game hands control to the director's Next button. Each beat is one screen: room-wide stats, then archetype reveals, then stat leaders, then awards, then the podium. Phones show each player's own card as it's revealed on the screen.

Timing: five rounds at about two minutes each (100 s of decisions plus 20 s of reveal and scoreboard) is ten minutes, the lobby is about three, leaving seven or so for the analysis. Speeding up or slowing down the analysis is entirely in the director's hands.

---

## Edge rules

**No tap in 5 seconds.** Counts as Share, recorded with a `timed_out` flag so the analysis can separate slow thumbs from real choices. A player who times out three decisions in a row gets a "still there?" nudge on their phone.

**Odd player count.** A house bot named **The Machine** fills the empty seat each round. It plays tit-for-tat (shares first, then copies your last move), is labeled as a bot on the phone, and is excluded from all awards and archetypes. Which human draws The Machine rotates so nobody gets it twice.

**Player disconnects.** The phone keeps a device token, so reloading or reopening the page drops them straight back into the current decision. Decisions missed while away default to Share and count as timeouts.

**Late joiner.** Lobby locks at game start. A latecomer sees a "game in progress" screen and can watch the big screen; the director can admit them at the next pairing reveal if the count works.

**Director loses connection.** The game clock lives on the server, so decisions keep running. The director panel reconnects the same way phones do. There is also a Pause control for when someone needs the room's attention.

**Repeat partners.** With 20+ players and 5 rounds the pairing algorithm can always avoid repeats. Below 10 players it allows them rather than failing.

---

## Anonymous mode

Anonymous mode is a toggle on the session, chosen at creation. The plan is to run the first game normally, use its analysis as the lesson, and then, if the room wants to play again, run a second session anonymously and see what changes.

In an anonymous session, partners appear on the phone as a per-round codename ("Blue Heron") rather than a username, so within a round you can still track how *this* partner is treating you, but you never learn who it is. The big screen shows no names at all: no leaderboard, no feed with usernames, no archetype reveals. It shows only aggregate, room-level stats. Every individual result (your score, archetype, awards you'd have won) is delivered privately to your phone.

Because each phone keeps a device token across sessions, the app can link the same person's normal and anonymous games without ever exposing who they are. That enables the closing beat of the second session: a big-screen comparison like **"Playing anonymously, this room stole 31% more often and forgave 40% less"**, with per-player before-and-after cards sent to phones. Players can also opt to re-enter their username if they've switched phones.

> If the mixed version (alternating anonymous rounds inside one session) is ever wanted, the data model already supports it: anonymity is a flag on the round, not just the session. It's just not in the first build.

---

## Analysis & awards

Everything below is computed from the decision log once round five ends, in a single pass, before the analysis phase begins. Every stat is stored per player per session, so the anonymous comparison later is a lookup rather than a recomputation.

### Per-player stats

| Stat | Definition |
|---|---|
| Share rate | Shares ÷ decisions (timeouts excluded). |
| Opening move | Share rate on decision 1 of each round: do you extend trust before you have any information? |
| Retaliation | How often you stole immediately after being stolen from. |
| Forgiveness | How often you returned to sharing after a partner stole from you, measured within the following three decisions. |
| Betrayals | Times you stole right after a mutual share. The "stab in the back" count. |
| Exploitation | Steals against a partner who had shared on the previous decision. |
| Endgame shift | Share rate in decisions 9–10 minus share rate in decisions 1–8. Negative means you defect when the shadow of the future shrinks. |
| Predictability | How well your next move can be guessed from your previous move and your partner's. Low means chaotic. |
| Partner yield | Average points your partners earned while playing you. High means people did well by meeting you. |
| Sucker count | Decisions where you shared and got stolen from. |

### Archetypes

Each player is assigned exactly one archetype from a rule ladder evaluated top to bottom; the first rule that matches wins. Thresholds live in `config/game.php` and should be tuned against the dress-rehearsal data so the room gets a spread rather than twenty Pragmatists.

| Archetype | Rule | What it says about you |
|---|---|---|
| The Saint | Share rate ≥ 90% | You shared no matter what it cost you. |
| The Wall | Share rate ≤ 15% | You never let anyone in, and never got burned either. |
| The Backstabber | Endgame shift ≤ −40% and share rate in 1–8 ≥ 60% | Perfect partner right up until it stopped mattering. |
| The Grudge | After any steal against you, you share with that partner < 10% of the time | One strike and they're done. |
| The Mirror | Your move matches your partner's previous move ≥ 75% of the time | Tit-for-tat: nice, retaliatory, forgiving, clear. The tournament winner. |
| The Diplomat | Forgiveness ≥ 60% and share rate ≥ 60% | You got stolen from and came back to the table anyway. |
| The Opportunist | Exploitation ≥ 50% of your steals, share rate 30–70% | You shared until you smelled a sharer, then took the five. |
| The Wildcard | Predictability in bottom 15% of the room | Nobody could read you, including maybe you. |
| The Pragmatist | Everyone else | You read the room and adjusted. Boring, effective. |

### Awards

| Award | Goes to |
|---|---|
| Champion | Most total points. Revealed last, on a podium with second and third. |
| Kindest | Highest share rate. |
| Most Forgiving | Highest forgiveness rate, minimum three times stolen from. |
| Most Ruthless | Highest steal rate. |
| Best Partner | Highest partner yield: people who played you walked away richest. |
| Most Betrayed | Highest sucker count. Played for sympathy and a laugh. |
| Cold Blooded | Most betrayals after a mutual share. |
| Endgame Assassin | Most negative endgame shift. |
| Unreadable | Lowest predictability. |
| Fastest Thumb | Lowest average decision time. A throwaway award that gets a laugh. |

Ties break on total points, then on a coin flip that the screen shows as a coin flip. The Machine is never eligible.

### Analysis beats, in order

Each press of Next advances one beat, and every beat has its own sound and entrance. The order is: the room's overall share rate with a comparison to the "everyone cooperates" score of 150; a live-drawn chart of share rate by decision number (this is where the endgame defection shows up as a visible cliff and makes the point without a slide); the archetype reveals, one player at a time with a card flip on the screen and the same card landing on their phone; the archetype census for the room ("six Mirrors, four Opportunists, one Saint"); the stat leaders as a fast rotating board; the awards, one per beat with a drumroll; and finally the podium. In anonymous mode the archetype reveals and awards go to phones only and the screen shows the census and leaderboard-free aggregates instead.

---

## Tech stack

Laravel on Laravel Cloud, Vue on the front, Reverb for realtime.

| Layer | Choice | Why |
|---|---|---|
| App | Laravel 12, PHP 8.4 | Owns the game clock, scoring, pairing, stats. Nothing game-critical runs in a browser. |
| Realtime | Laravel Reverb (managed WebSocket cluster on Cloud, 100-connection tier) + Laravel Echo | Every reveal hits every phone in the same instant. Delete the cluster after the training; compute and DB scale to zero on their own. |
| Front end | Vue 3 + Vite, Pinia for state, Vue Router (no Inertia) | Three tiny SPAs (phone, screen, director) share one game store. Animation timing lives in the browser where it belongs. |
| Animation | CSS transitions for UI, GSAP for the screen's set pieces | Card flips, count-ups, the podium rise. GSAP's timeline makes each analysis beat a single orchestrated sequence. |
| Audio | Howler.js with one sprite sheet per client | Reliable mobile audio unlock, one HTTP request for all sound effects, gapless music loops. |
| Database | Serverless Postgres on Laravel Cloud | Scales to zero. Every decision is a row; the analysis is a query. |
| Hosting | Laravel Cloud, Flex compute, hibernation off on game day | Free `.laravel.cloud` subdomain; add a short custom domain later if wanted. |

### The game clock

The server is the metronome. When a decision opens, the server writes `deadline_at` to the database and broadcasts it; clients render a countdown toward that timestamp (with a one-time clock-offset correction so a phone with a wrong clock still counts down correctly). A single long-running `game:run` process (Laravel Cloud supports background processes) advances every active session on a 250 ms loop: open decision, wait for deadline, score, broadcast reveal, wait five seconds, repeat. A choice that arrives after the deadline is rejected. No client can stall or race the game, and a director whose laptop goes to sleep changes nothing.

The director panel talks to the server through a handful of commands (start, pause, resume, next analysis beat, admit late player, end session) and the server broadcasts the resulting state. The big screen and the phones are pure renderers of that state, which also makes them easy to test: point a fake state at them and watch.

---

## Screens & data

### Routes

| Route | Who | What |
|---|---|---|
| `/` | Players | Enter code, pick username. Remembers you. |
| `/play/{code}` | Players | The phone: lobby, decision, reveal, round summary, your analysis card. |
| `/screen/{code}` | Projector | The big screen. Full-screen, plays the music, no controls at all. |
| `/director/{code}` | Director | Control panel on your own phone or laptop: start, pause, Next, player list, kick, admit. Password-protected. |
| `/director` | Director | Create a session, choose mode, view past sessions and their stats. |

### Data model

`sessions` hold the code, mode, settings, and status. `players` belong to a session and carry a `device_token` that is also their link across sessions, plus a username and a bot flag. `rounds` belong to a session with a number and an `anonymous` flag. `pairings` join two players to a round and carry the anonymous codenames. `decisions` belong to a pairing with an index 1–10, both choices, both point awards, both timeout flags, both response times, and the deadline. `player_stats` is one row per player per session with every stat above plus the archetype, and `awards` records who won what. Nothing is ever computed from memory that can't be recomputed from `decisions`.

---

## Sound & animation

**The big screen carries the audio.** Twenty-five phones playing sound effects half a second apart is noise, not atmosphere. Phones default to vibration only (short buzz on reveal, double buzz when stolen from) with a sound toggle in the corner for anyone who wants it. The screen's music also does the pacing work: a lobby loop, a round loop whose intensity steps up in decisions 8–10, and a warmer bed under the analysis.

| Moment | Screen | Phone |
|---|---|---|
| Player joins | Pop + avatar bounces in | Confirmation chime (this is the audio-unlock tap) |
| Decision opens | Soft tick each second, last two louder | Ring drains; buttons pulse |
| Lock-in | — | Click, button fills, ring stops |
| Mutual share | Warm two-note chime | Green flash, both cards flip green, +3 counts up |
| Betrayal | Sharp sting; feed item "Sam stole from Priya" | Red flash, double buzz for the victim, +5 or +0 |
| Mutual steal | Dull thud | Both cards red, +1 each |
| Round end | Fanfare, leaderboard reorders with arrows | Round summary slides up |
| Archetype reveal | Card flip with a riser | Same card lands with a buzz |
| Award | Drumroll, then cymbal on the name | Winner's phone gets confetti |
| Podium | Three columns rise, music resolves | Final card with score and archetype |

Sound sources: CC0 effects from freesound.org or Pixabay, music from a royalty-free library (or generated), trimmed and packed into a sprite sheet during the build. The audio session lists the exact files it used and their licences in the repo. All motion respects `prefers-reduced-motion` on phones.

---

## Build plan

The build splits into sessions that can run in parallel once a shared contract exists. The contract (`CONTRACT.md`) is what makes parallel work possible: one document in the repo that names every broadcast event, its payload, every director command, and the state machine. Every later session reads it first and builds against it, not against each other's code.

| Session | Builds | Depends on |
|---|---|---|
| 0 · Foundation | Laravel app, migrations, models, `CONTRACT.md` (events, payloads, state machine, director commands), Vue workspace with the three entry points, Reverb wired up, deployed to Laravel Cloud with a hello-world broadcast reaching a phone. | This plan |
| 1 · Engine | Session and player lifecycle, pairing with repeat-avoidance and The Machine, the `game:run` loop, deadline handling, scoring, broadcasts, pause/resume, reconnect. | 0 |
| 2 · Phone | Join flow, device token, decision screen with the ring, reveal, round summary, analysis card, vibration, optional sound, reduced-motion. | 0 (builds against fake state until 1 lands) |
| 3 · Big screen | Lobby with QR, pairing reveal, round scoreboard and feed, every analysis beat, anonymous-mode variants, GSAP set pieces. | 0, and 5 for the analysis payloads |
| 4 · Director | Create session, mode toggle, control panel, player list, admit and kick, session history, password. | 0 |
| 5 · Analysis | Stats, archetype ladder, awards, tie-breaks, cross-session comparison, the analysis-beat sequence as data, thresholds in `config/game.php`. | 0 (pure functions over `decisions`; testable with generated games) |
| 6 · Audio | Source and licence the effects and music, build sprite sheets, Howler wrapper, hook every moment in the table above. | 2, 3 |
| 7 · Simulator | `game:simulate`: plays a full 30-player game against the real server using the scripted roster in Testing, asserts the expected archetypes and awards, and generates data for tuning thresholds. | 1 |
| 8 · Integration | Run the simulator end to end in fast mode with real phones watching, fix what breaks, tune thresholds, polish, then the rehearsal. | Everything |

Sessions 1, 2, 4 and 5 can start the moment 0 is merged. Session 3 is the biggest and benefits from starting after 5 has settled what the analysis payloads look like. Session 7 is worth its cost: the simulator is the only way to see a 30-player game before the real one, and it's also the threshold-tuning tool.

Rough scale: sessions 0 through 5 are each an afternoon of vibe-coding; 3 and 6 are the ones most likely to run long because taste takes iteration. Schedule the rehearsal at least a week before the training so there's time to fix what it finds.

---

## Testing

Four layers, cheapest first. Each catches what the one before it can't, and the simulator in the middle is the one to invest in.

### 1 · Logic tests

Plain PHP tests over the pure parts. Scoring is a four-case table; assert all four. Pairing runs with 20, 21, 25 and 30 players across five rounds and asserts one partner per person per round, no repeats above ten players, The Machine present only on odd counts and never on the same person twice. The analysis is tested with scripted decision logs whose archetype is known in advance (the roster below doubles as the fixture list), so any threshold change that breaks the ladder fails a test instead of surprising a room. These ship with sessions 1 and 5 and run in seconds.

### 2 · The simulator

Session 7 builds `php artisan game:simulate`, a command that plays a complete game against the real server, over real WebSockets, with thirty scripted players. It is the only way to see a full-size game before the real one, it's the load test, it generates the data used to tune archetype thresholds, and it's the game-day smoke test. Run it locally while building, against the Cloud environment before the rehearsal, and against production the morning of.

Each simulated player is a personality: a small class with a `choose(history)` method that sees the current round's decisions so far. The roster is chosen so every archetype and every award has at least one player who should win it, which turns the analysis output into a set of assertions.

| Personality | Count | Strategy | Expected result |
|---|---|---|---|
| Saint | 2 | Always shares. | The Saint; one wins Kindest, one wins Most Betrayed. |
| Wall | 2 | Always steals. | The Wall; Most Ruthless. |
| Mirror | 4 | Shares first, then copies the partner's last move. | The Mirror; likely Champion in a mixed room. |
| Grudge | 2 | Shares until stolen from once, then steals for the rest of the round. | The Grudge. |
| Diplomat | 2 | Mirror, but forgives: after retaliating once, offers a share. | The Diplomat; Most Forgiving. |
| Backstabber | 2 | Shares through decision 8, steals 9 and 10. | The Backstabber; Endgame Assassin. |
| Opportunist | 3 | Steals whenever the partner shared last time, shares otherwise. | The Opportunist; Cold Blooded. |
| Wildcard | 2 | Coin flip every decision. | The Wildcard; Unreadable. |
| Pragmatist | 5 | Shares 70% of the time, steals if the partner has stolen twice in a row. | The Pragmatist. Should be the largest group, but not a majority. |
| Sleeper | 2 | Never answers. | Every decision recorded as Share with `timed_out`; gets the "still there?" nudge; excluded from Kindest despite a 100% share rate. |
| Ghost | 1 | Drops the WebSocket mid-round 3 and reconnects 15 seconds later with the same device token. | Resumes on the current decision; missed decisions are timeouts; nothing else changes. |
| Straggler | 1 | Sends every choice 100 ms after the deadline. | All choices rejected, all recorded as timeouts. Proves the deadline is real. |
| Speedster | 1 | Mirror, but answers in 200 ms. | Fastest Thumb. |
| Double-tapper | 1 | Submits Share, then Steal 50 ms later, every decision. | First choice wins; second is ignored, not overwritten. |

That's thirty. Run it a second time with 29 (drop a Pragmatist) to exercise The Machine. The command prints the full analysis at the end and exits non-zero if any expected result above didn't happen, so it can run in CI as well as by hand. Flags: `--players=N`, `--anonymous`, `--fast` (uses the session's fast mode), `--seed=` for reproducible Wildcards, and `--url=` to point it at local, staging or production. Because the personalities are what a real room's behaviour is measured against, save each simulator run's `player_stats` to compare with rehearsal data when tuning thresholds.

### 3 · Eyes on it

Sessions get a **fast mode** setting (1 s choose, 1 s reveal, 3 s scoreboards) so a full five-round game takes under two minutes. With the simulator supplying the players, open the big screen in one tab, the director panel in another, and a couple of real phones (one iPhone, one Android, because audio unlock and vibration differ and desktop Chrome will lie about both) and watch whole games through. This is where the sound-before-reveal timing, the leaderboard reorder, the long-username clipping, and the anonymous-mode screen variants get checked, and it's the session 8 loop.

### 4 · Rehearsal

At least a week out: six to eight colleagues on their own phones, on the office Wi-Fi, with the real projector and speakers, at real speed. Watch three things: whether five seconds is enough for people who aren't staring at their phone (six or seven may feel better), whether the analysis holds attention or drags, and whether the archetype spread is interesting or everyone lands on Pragmatist. Keep the session's data; it's the baseline the thresholds get tuned against, and the simulator's roster gets adjusted to match what real people actually did.

---

## Game day

The morning of: turn hibernation off on the Cloud environment (or open the app an hour early and keep the screen tab alive) so the first player doesn't hit a cold start, confirm the Reverb cluster is up, and run the simulator once against production as a smoke test. In the room: open `/screen/{code}` on the projector in a full-screen browser with the sound routed through the room's speakers, open `/director/{code}` on your own phone, and put the join URL on the screen five minutes before start. Make sure everyone is on Wi-Fi or has signal; 30 WebSocket connections is nothing, but a dead zone is a dead zone. Backup plan: if a phone won't connect, that person pairs with a neighbour and calls their moves; if the whole thing dies, The Machine can be drafted into a chat-based version in about thirty seconds. After the training, delete the Reverb cluster and let the rest scale to zero; the session data stays for the anonymous replay.

---

## Still open

None of these block session 0. The archetype and award names are a first pass and should be edited for the room's sense of humour. Whether phones get sound at all, or vibration only, is a taste call for the rehearsal. Whether to buy a short custom domain for the join URL, or live with the `.laravel.cloud` one behind a QR code. The name of the game: this plan calls it **Share or Steal** because that's what the buttons say.

---

**Decisions locked in this plan:** payoffs 3/3, 0/5, 1/1 · 5 rounds × 10 decisions · 5 s choose + 5 s reveal · partners named in normal mode · anonymous mode as a session toggle with private phone stats and public aggregates · timeout = Share, flagged · odd count filled by The Machine (tit-for-tat, no awards) · up to 30 players · Laravel Cloud + Vue + Reverb · game is the whole training.
