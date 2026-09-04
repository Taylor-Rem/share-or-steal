<?php

namespace App\Console\Commands;

use App\Events\GamePing;
use App\Models\GameSession;
use Illuminate\Console\Command;

class GamePingCommand extends Command
{
    protected $signature = 'game:ping {code : Session code, e.g. DEMO} {--message=ping : Text to send}';

    protected $description = 'Broadcast a test event to every channel of a session (session, screen, director, and each player)';

    public function handle(): int
    {
        $session = GameSession::where('code', strtoupper($this->argument('code')))->first();

        if (! $session) {
            $this->error("No session with code {$this->argument('code')}.");

            return self::FAILURE;
        }

        $playerIds = $session->players()->where('is_bot', false)->orderBy('id')->pluck('id')->all();

        GamePing::dispatch($session, (string) $this->option('message'), $playerIds);

        $this->info(sprintf(
            'Sent game.ping to session.%1$s, screen.%1$s, director.%1$s and %2$d player channel(s).',
            $session->code,
            count($playerIds),
        ));

        return self::SUCCESS;
    }
}
