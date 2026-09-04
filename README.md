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

Then open the three placeholder pages:

| Page | URL |
|---|---|
| Phone | <http://steal-or-share.test/play/DEMO> |
| Big screen | <http://steal-or-share.test/screen/DEMO> |
| Director | <http://steal-or-share.test/director> → enter the `DIRECTOR_PASSWORD` and `DEMO` |

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

### Tests and style

```bash
composer test          # Pest
vendor/bin/pint        # Laravel Pint (--test to check only)
```

Tests run on SQLite in memory and need no database setup. CI (`.github/workflows/ci.yml`)
runs Pint, Pest and `npm run build` on every push and pull request.

---

## Project layout

```
app/Auth/            header-based identity (players, director, screen) — CONTRACT.md § 2
app/Enums/           SessionStatus, SessionMode, Choice, Archetype, AwardKey
app/Events/          GameBroadcast base (envelope + state) and one class per CONTRACT.md § 9 event
app/Game/            the engine: Engine (state machine, commands, broadcasts), Pairer, Moments, Payloads
app/Analysis/        Analyzer contract and the StubAnalyzer that Session 5 replaces
app/Http/            join / me / choice and the director endpoints — CONTRACT.md § 10
app/Console/         game:ping, game:run (the clock: one tick every game.tick_ms)
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
