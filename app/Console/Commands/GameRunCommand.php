<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * The game clock. Session 1 (Engine) fills this in: on every tick it loads
 * every session whose status is timed and whose phase_ends_at has passed, and
 * advances it (open decision -> score -> reveal -> next), broadcasting as it goes.
 *
 * Session 0 ships only the loop shape so the Laravel Cloud background process
 * definition (`php artisan game:run`) is valid from day one.
 */
class GameRunCommand extends Command
{
    protected $signature = 'game:run {--once : Run a single tick and exit}';

    protected $description = 'Advance every active session on a fixed tick. Long-running; one process per deployment.';

    public function handle(): int
    {
        $tickMs = (int) config('game.tick_ms', 250);
        $this->info("game:run started, tick {$tickMs} ms. (Session 0 stub: ticks, advances nothing.)");

        do {
            $this->tick();
            usleep($tickMs * 1000);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    protected function tick(): void
    {
        // Session 1: advance every GameSession whose status->isTimed() and phase_ends_at <= now().
    }
}
