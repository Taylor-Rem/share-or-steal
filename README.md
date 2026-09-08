# Share or Steal

A Jackbox-style prisoner's dilemma for a 20-minute game-theory training. Players join on
their phones with a room code, play five rounds of ten Share/Steal decisions against random
partners, and then a director-paced analysis assigns everyone an archetype and hands out
awards.

- `docs/PLAN.md` — the full plan and spec. Every build session reads this first.
- `CONTRACT.md` — the state machine, every event and payload, every endpoint, the clock.
  The source of truth that all parallel sessions build against.
- `docs/sessions/` — the prompt for each build session.

**Status:** Session 0 (foundation) is done. The three entry points are placeholders that
show the raw state they receive; nothing here is finished game UI yet.

Stack: Laravel 12 · PHP 8.4 · Postgres · Laravel Reverb · Vue 3 + Vite + Pinia + Vue Router · Laravel Cloud.

---

## Local setup (Herd)

This repo uses [Laravel Herd](https://herd.laravel.com) for HTTP and a local Postgres.
It needs **PHP 8.4 on your PATH** for the CLI; Herd ships one.

```bash
# 1. PHP 8.4 for this shell (Herd's binaries). Put this in your shell profile if you like.
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"
alias php=php84

# 2. Dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate
# Edit .env: DB_USERNAME/DB_PASSWORD for your Postgres, and pick a DIRECTOR_PASSWORD.
# Fill REVERB_APP_ID / KEY / SECRET with any random values (they only need to match themselves).

# 4. Database
createdb steal_or_share
php artisan migrate --seed          # seeds a finished demo game with code DEMO

# 5. Serve
herd link steal-or-share            # once; then http://steal-or-share.test
herd isolate --site=steal-or-share 8.4
```

### Running it

One command starts Reverb (WebSockets), the game clock, and Vite:

```bash
composer dev
```

That is `php artisan reverb:start`, `php artisan game:run`, and `npm run dev` under
`concurrently`. Herd serves the app itself at <http://steal-or-share.test>.

Then open the pages:

| Page | URL |
|---|---|
| Phone | <http://steal-or-share.test/> (join), <http://steal-or-share.test/play/DEMO?fixture=1> (scripted game, no server needed; `?fixture=fast` for the fast clocks, `&anonymous=1` for codenames) |
| Big screen | <http://steal-or-share.test/screen/DEMO> (the DEMO analysis; press Next in the director panel), <http://steal-or-share.test/screen/DEMO?fixture=1> (a whole scripted game; `?fixture=fast`, `&anonymous=1`, `&comparison=1`) |
| Director | <http://steal-or-share.test/director> → `DIRECTOR_PASSWORD`, then create a session or open `DEMO`; `/director/DEMO/analysis` has the tables |

And prove a broadcast reaches all three:

```bash
php artisan game:ping DEMO
```

Each page should show a `game.ping` event within a moment. There is also a headless client
for the same check from a terminal (Node 22+), which is the tool for smoke-testing a
deployed environment:

```bash
node scripts/listen.mjs DEMO --director=$DIRECTOR_PASSWORD
```

### Playing a game from the terminal

With `composer dev` running, thirty scripted phones and a director can play a whole
fast-mode game over the real API and real WebSockets (Node 22+):

```bash
node scripts/play.mjs --director=$DIRECTOR_PASSWORD
```

It creates a session, joins the players, starts, answers (or sleeps, double-taps, or
straggles) every decision, kicks one player, admits a late joiner, pauses once, walks the
analysis beats and prints the podium, then lists every contract event it saw per channel
and exits non-zero if one is missing. Useful flags: `--players=9`, `--anonymous`,
`--rounds=2 --decisions=3`, `--slow` (game-day clocks), `--code=ABCD` to join an existing
lobby, and `--url=`/`--ws=` for a deployed environment.

By hand, the same thing is: `POST /api/director/sessions` with `X-Director-Key`, a few
`POST /api/sessions/{code}/join`, then `POST .../start`; `php artisan game:run` does the
rest and prints each transition. See `CONTRACT.md` § 10 for every endpoint.

Phones on the same Wi-Fi can open the page too if you point `APP_URL`, `REVERB_HOST` and the Vite dev server at your
machine's LAN address.

### Sound

The screen carries the room's audio (music beds and every sting); phones default to
vibration with a sound toggle. Effects and loops are CC0 from Freesound and Kenney, listed
with their sources in `resources/audio/LICENSES.md`. The sprite sheets in `public/audio/`
are committed; to rebuild them after editing `resources/audio/manifest.json`:

```bash
brew install ffmpeg            # once
node scripts/audio-pack.mjs    # -> public/audio/{screen,phone}.{webm,mp3,json}
```

Sources live in `resources/audio/source/` (gitignored); `LICENSES.md` says how to re-fetch
them with `FREESOUND_API_KEY` from `.env`. The screen page needs one click to unlock audio.

### Tests and style

```bash
composer test          # Pest
vendor/bin/pint        # Laravel Pint (--test to check only)
npm test               # Vitest: the store's event handling, the clock, the fixture
```

Pest runs on SQLite in memory and needs no database setup. `tests/Support/ScriptedGame.php`
plays a roster of personalities (or explicit move lists) straight into the database, so an
analysis test is a few lines; `AnalyzerRosterTest` pins the archetype every personality lands
on and prints the new snapshot when a threshold in `config/game.php` moves. CI (`.github/workflows/ci.yml`)
runs Pint, Pest, Vitest and `npm run build` on every push and pull request.

To try the phone on a real handset, point `APP_URL`, `REVERB_HOST` and the Vite dev server
at your machine's LAN IP (or use the Cloud URL), open `/` on the phone, and drive the other
players with `scripts/play.mjs --code=XXXX --players=3` once you have joined. Add `--follow`
to make the script phones-only, so you run the game from the director panel yourself.

---

## Project layout

```
app/Auth/            header-based identity (players, director, screen) — CONTRACT.md § 2
app/Enums/           SessionStatus, SessionMode, Choice, Archetype, AwardKey
app/Events/          GameBroadcast base (envelope + state) and one class per CONTRACT.md § 9 event
app/Game/            the engine: Engine (state machine, commands, broadcasts), Pairer, Moments, Payloads
app/Analysis/        the analysis: DecisionLog -> Stats -> Ladder + Awards -> Beats (+ Comparison)
app/Simulation/      Personality: the scripted roster the analysis is tested against and the simulator plays
app/Http/            join / me / choice and the director endpoints — CONTRACT.md § 10
app/Console/         game:ping, game:run (the clock: one tick every game.tick_ms)
resources/js/phone/  the player's phone: join page, one component per status, countdown ring
resources/js/director/ the control panel: password gate, create + history, one-button panel, analysis tables
resources/js/screen/ the projector: lobby with QR, pairing, decisions + feed, scoreboard, one GSAP beat per analysis type
resources/js/shared/ store (events -> state), clock offset, fixture player, audio/haptics hooks
app/Models/          GameSession, Player, Round, Pairing, Decision, PlayerStat, Award
config/game.php      every tunable: clocks, payoffs, thresholds, codenames
database/            migrations, factories, DemoSessionSeeder (code DEMO)
routes/              web.php (3 SPAs), api.php, channels.php (channel auth rules)
resources/js/        phone/, screen/, director/, shared/ — see resources/js/README.md
tests/               Pest: schema + seed, state endpoint, channel auth, game:ping, config
```

---

## Laravel Cloud

Nothing in the repo is Cloud-specific; the dashboard holds the configuration. Production is
`https://share-or-steal-production-lh9czz.laravel.cloud`, deployed from `main` on every push.

**Environment**

- Flex compute. Turn hibernation **off** on game day so the first phone doesn't hit a
  cold start; leave it on otherwise.
- Database: serverless Postgres, attached to the environment (Cloud injects `DB_*`).
- Reverb: add a Reverb cluster (100-connection tier is plenty for 30 phones + screen +
  director). Attaching it injects `BROADCAST_CONNECTION`, the `REVERB_*` variables and the
  four `VITE_REVERB_*` variables, so nothing needs copying by hand.
- Variables to set by hand: `APP_KEY`, `APP_URL`, `DIRECTOR_PASSWORD`,
  `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=database`,
  and either `SESSION_DRIVER` value (the app doesn't use sessions; the database attach
  offers `database`, which works because the default Laravel migration creates the table).
  Uncheck the attach dialog's `QUEUE_CONNECTION=database` suggestion: there is no queue worker.

**Build and deploy commands**

```
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

**Background process** — add one worker running

```
php artisan game:run
```

It is the game clock. One instance per environment, always on. It is safe to restart at
any time: everything it needs is in the database.

**Smoke test** — after the first deploy, open `/screen/DEMO` (the seeder runs only if you
run `php artisan db:seed` in a deploy command or a one-off command) and run
`php artisan game:ping DEMO` from a Cloud command; the event should appear on the
screen and on a phone.

After the training, delete the Reverb cluster; compute and database scale to zero.
