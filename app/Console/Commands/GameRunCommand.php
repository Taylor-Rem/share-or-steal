<?php

namespace App\Console\Commands;

use App\Game\Engine;
use Illuminate\Console\Command;
use Throwable;

/**
 * The game clock. Every tick it asks the engine to advance every session whose timed
 * phase has ended (open decision -> score -> reveal -> next -> summary -> next round ->
 * analysis), broadcasting as it goes. One process per deployment; Laravel Cloud runs it
 * as a background process.
 */
class GameRunCommand extends Command
{
    protected $signature = 'game:run {--once : Run a single tick and exit}';

    protected $description = 'Advance every active session on a fixed tick. Long-running; one process per deployment.';

    public function handle(Engine $engine): int
    {
        $tickMs = (int) config('game.tick_ms', 250);
        $this->info("game:run started, tick {$tickMs} ms.");

        do {
            $this->tick($engine);
            usleep($tickMs * 1000);
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    protected function tick(Engine $engine): void
    {
        try {
            foreach ($engine->tick() as $session) {
                $this->line(sprintf(
                    '%s  %s -> %s%s',
                    now()->format('H:i:s.v'),
                    $session->code,
                    $session->status->value,
                    $session->current_round ? sprintf(' (round %d%s)', $session->current_round, $session->current_decision ? ', decision '.$session->current_decision : '') : '',
                ));
            }
        } catch (Throwable $e) {
            // One bad session must not stop the clock for the others.
            report($e);
            $this->error(now()->format('H:i:s.v').'  tick failed: '.$e->getMessage());
        }
    }
}
