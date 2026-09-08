<?php

namespace App\Console\Commands;

use App\Simulation\Personality;
use App\Simulation\Simulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Play a complete game against a real server with the plan's roster of scripted phones,
 * over real HTTP and WebSockets, and check every promise the roster makes. Needs
 * `php artisan game:run` and Reverb at the target; locally `composer dev` starts both.
 *
 *   php artisan game:simulate --fast
 *   php artisan game:simulate --fast --players=29                    # The Machine appears
 *   php artisan game:simulate --fast --anonymous --seed=7
 *   php artisan game:simulate --url=https://x.laravel.cloud --ws=wss://ws-host:443 --key=KEY --director-key=…
 */
class GameSimulateCommand extends Command
{
    protected $signature = 'game:simulate
        {--players=30 : How many of the roster to play (Pragmatists are dropped first)}
        {--anonymous : Create the session in anonymous mode}
        {--fast : Create the session in fast mode (1 s clocks); game-day clocks otherwise}
        {--seed=1 : Seed for the Wildcards, Pragmatists and tap timings}
        {--rounds= : Override rounds (default from config)}
        {--decisions= : Override decisions per round}
        {--url= : Server URL (default APP_URL)}
        {--ws= : Reverb URL, e.g. wss://host:443 (default from REVERB_*)}
        {--key= : Reverb app key (default REVERB_APP_KEY)}
        {--director-key= : Director password (default DIRECTOR_PASSWORD)}
        {--events : Log every event the director sees}';

    protected $description = 'Play a thirty-player game against a running server and check the roster\'s expectations';

    public function handle(): int
    {
        $url = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $key = (string) ($this->option('key') ?: config('reverb.apps.apps.0.key') ?: env('REVERB_APP_KEY'));
        $ws = $this->option('ws') ?: sprintf('%s://%s:%s', env('REVERB_SCHEME', 'http') === 'https' ? 'wss' : 'ws', env('REVERB_HOST', 'localhost'), env('REVERB_PORT', 8080));
        $wsUrl = rtrim((string) $ws, '/')."/app/{$key}?protocol=7&client=php&version=1";
        $directorKey = (string) ($this->option('director-key') ?: config('game.director_password'));
        $players = max(2, (int) $this->option('players'));

        $this->line("<info>game:simulate</info> → {$url} via {$ws}");
        $this->line('Roster: '.collect(Personality::roster($players))->map(fn ($r) => "{$r[0]}×{$r[1]}")->implode(', '));

        $simulator = new Simulator(
            url: $url,
            wsUrl: $wsUrl,
            directorKey: $directorKey,
            playerCount: $players,
            anonymous: (bool) $this->option('anonymous'),
            fast: (bool) $this->option('fast'),
            seed: (int) $this->option('seed'),
            rounds: $this->option('rounds') !== null ? (int) $this->option('rounds') : null,
            decisions: $this->option('decisions') !== null ? (int) $this->option('decisions') : null,
            log: fn (string $line) => $this->line('  '.$line),
            verbose: (bool) $this->option('events'),
        );

        $started = microtime(true);
        $assertions = $simulator->run();
        $result = $simulator->result;

        // The analysis.
        $this->newLine();
        $this->line("<info>Analysis of {$result['code']}</info> ({$result['ended']}, ".round(microtime(true) - $started).' s)');
        $this->table(
            ['#', 'Player', 'Pts', 'Archetype', 'Share', 'Open', 'Retal', 'Forgive', 'Betray', 'Exploit', 'Endgame', 'Predict', 'Yield', 'Sucker', 'T/O', 'Avg ms'],
            collect($result['stats'])->map(fn ($s) => [
                $s['rank'] ?? '—', $s['player']['username'], $s['total_points'], $s['archetype']['label'] ?? '—',
                $this->pct($s['share_rate']), $this->pct($s['opening_move']), $this->pct($s['retaliation']), $this->pct($s['forgiveness']),
                $s['betrayals'], $s['exploitation'].' ('.$this->pct($s['exploitation_rate']).')', $this->pct($s['endgame_shift']), $this->pct($s['predictability']),
                $s['partner_yield'] === null ? '—' : number_format($s['partner_yield'], 2), $s['sucker_count'], "{$s['timeouts']}/{$s['decisions_count']}", $s['avg_response_ms'] ?? '—',
            ])->all(),
        );
        $this->table(['Award', 'Winner', 'Value', 'Tie-break'], collect($result['awards'])->map(fn ($a) => [$a['label'].(isset($a['place']) ? " · {$a['place']}" : ''), $a['winner']['username'], $a['value_label'], $a['tie_break'] ?? '—'])->all());
        $this->line('Beats: '.implode(', ', $result['beats']));
        $this->line('Events seen by the director: '.collect($result['events'])->map(fn ($n, $e) => "{$e}×{$n}")->implode('  '));

        // The assertions.
        $this->newLine();
        foreach ($assertions->results as $r) {
            $mark = $r['ok'] ? ($r['soft'] ? '<comment>~</comment>' : '<info>✓</info>') : '<error>✗</error>';
            $this->line("  {$mark} {$r['name']}".($r['detail'] !== '' ? "  <fg=gray>{$r['detail']}</>" : ''));
        }
        $this->newLine();

        // The stats file, for threshold tuning.
        $path = storage_path('simulations/'.now()->format('Y-m-d_His')."_{$result['code']}.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("Saved {$path}");

        $failed = $assertions->failed();
        $soft = $assertions->soft();
        $this->line($failed === 0
            ? '<info>OK</info>: '.count($assertions->results).' checks passed'.($soft ? ", {$soft} inside a documented overlap" : '')
            : "<error>FAIL</error>: {$failed} of ".count($assertions->results).' checks failed');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function pct(mixed $v): string
    {
        return $v === null ? '—' : round($v * 100).'%';
    }
}
