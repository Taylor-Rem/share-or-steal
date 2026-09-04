# Working notes for every session after 0

Every prompt in this folder says "read the working notes". This is them.

- **Read first:** `docs/PLAN.md` (the spec) and `CONTRACT.md` (the interfaces). Build to the
  contract, not to another session's code. If you need something the contract doesn't define,
  add it to `CONTRACT.md` in the same style and append a dated line under "Changes".
- **Branches, not main.** Every push to `main` deploys to production on Laravel Cloud. Work on
  `session/N-name` (for example `session/1-engine`), open a pull request, and let CI (Pint,
  Pest, Vite build) go green before merging. Merge when the session's definition of done is met.
- **PHP 8.4 lives in Herd**, not on the default PATH (that is PHP 7.4 for other work):
  `export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"` then use `php84` (or alias
  `php=php84`). The site is served at <http://steal-or-share.test>; `composer dev` starts Reverb,
  `game:run` and Vite. Postgres is local (`steal_or_share`); tests use SQLite in memory.
- **Realtime verification:** the Claude in-app browser pane cannot open WebSockets. Verify
  broadcasts with `node scripts/listen.mjs CODE --director=$DIRECTOR_PASSWORD` or in Taylor's
  real Chrome.
- **Tests and style:** Pest for everything server-side, `vendor/bin/pint` before committing.
  Small commits with clear messages. Don't leave placeholder UI pretending to be finished.
- **Config lives in `config/game.php`.** No magic numbers in code for anything that is a rule,
  a clock, a payoff or a threshold.
- **Seed data:** `php artisan migrate:fresh --seed` gives a finished two-round game with code
  `DEMO`, six players, stats, awards and a 20-beat analysis sequence in the contract shape.
- **When you finish:** update `README.md` if setup or commands changed, list the decisions you
  made that the plan or contract didn't settle, and say what the next session needs to know.
