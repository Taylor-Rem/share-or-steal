<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Snapshot one session's data to storage/simulations as JSON: state, players, every
 * decision, the computed stats, awards and beats. Run it after the rehearsal (and after
 * the training) so thresholds can be tuned against what real people did.
 */
class GameExportCommand extends Command
{
    protected $signature = 'game:export {code : Session code} {--out= : File path (default storage/simulations/<date>_<code>_export.json)}';

    protected $description = 'Write a session\'s decisions, stats, awards and beats to a JSON file for tuning';

    public function handle(): int
    {
        $session = GameSession::where('code', strtoupper($this->argument('code')))->first();
        if (! $session) {
            $this->error("No session with code {$this->argument('code')}.");

            return self::FAILURE;
        }

        $decisions = [];
        foreach ($session->rounds()->with('pairings.decisions')->get() as $round) {
            foreach ($round->pairings as $pairing) {
                foreach ($pairing->decisions as $d) {
                    $decisions[] = [
                        'round' => $round->number, 'pairing_id' => $pairing->id, 'a' => $pairing->player_a_id, 'b' => $pairing->player_b_id,
                        'index' => $d->index, 'choice_a' => $d->choice_a?->value, 'choice_b' => $d->choice_b?->value,
                        'points_a' => $d->points_a, 'points_b' => $d->points_b, 'timed_out_a' => $d->timed_out_a, 'timed_out_b' => $d->timed_out_b,
                        'response_ms_a' => $d->response_ms_a, 'response_ms_b' => $d->response_ms_b,
                    ];
                }
            }
        }

        $data = [
            'exported_at' => GameSession::iso(now()),
            'state' => $session->toSummaryArray(),
            'players' => $session->players()->orderBy('id')->get()->map->toDirectorArray()->values(),
            'decisions' => $decisions,
            'stats' => $session->stats()->with('player')->orderBy('rank')->get()->map->toContractArray()->values(),
            'awards' => $session->awards()->with('player')->get()->map->toContractArray()->values(),
            'beats' => $session->analysis_beats ?? [],
        ];

        $path = $this->option('out') ?: storage_path('simulations/'.now()->format('Y-m-d_His')."_{$session->code}_export.json");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Wrote {$path} (".count($decisions).' decisions, '.count($data['stats']).' stat rows)');

        return self::SUCCESS;
    }
}
