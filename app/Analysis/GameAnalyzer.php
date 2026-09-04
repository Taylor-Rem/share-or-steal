<?php

namespace App\Analysis;

use App\Game\Ranking;
use App\Models\Award;
use App\Models\GameSession;
use App\Models\PlayerStat;
use Illuminate\Support\Arr;

/**
 * The analysis, once, in one pass over the decision log: stats per player, the archetype
 * ladder, awards, and the beat sequence, stored on the session. Runs inside the engine's
 * locked transition from `round_summary` to `analysis` and never broadcasts.
 *
 * Who counts: every admitted human gets a stats row, a rank and an archetype (kicked ones
 * too, they played). The Machine gets a stats row with no rank and no archetype. Awards
 * and the podium go to humans who were not kicked.
 */
class GameAnalyzer implements Analyzer
{
    private const COLUMNS = [
        'total_points', 'decisions_count', 'timeouts', 'share_rate', 'opening_move', 'retaliation', 'forgiveness',
        'betrayals', 'exploitation', 'exploitation_rate', 'endgame_shift', 'predictability', 'partner_yield',
        'sucker_count', 'times_stolen_from', 'match_rate', 'post_steal_share_rate', 'avg_response_ms',
    ];

    public function __construct(private Awards $awards = new Awards) {}

    public function analyze(GameSession $session): void
    {
        $session->stats()->delete();
        $session->awards()->delete();

        $log = DecisionLog::for($session);

        $stats = [];
        foreach ($log->players as $id => $player) {
            $stats[$id] = Stats::compute($log->histories[$id]) + [
                'is_bot' => (bool) $player->is_bot,
                'eligible' => ! $player->is_bot && $player->kicked_at === null,
            ];
        }

        $humans = array_filter($stats, fn ($s) => ! $s['is_bot']);
        $ranks = Ranking::ranks(array_map(fn ($s) => $s['total_points'], $humans));
        $cutoff = Ladder::cutoff(array_map(fn ($s) => $s['predictability'], $humans));

        foreach ($stats as $id => $s) {
            PlayerStat::create(Arr::only($s, self::COLUMNS) + [
                'game_session_id' => $session->id,
                'player_id' => $id,
                'rank' => $s['is_bot'] ? null : $ranks[$id],
                'archetype' => $s['is_bot'] ? null : Ladder::assign($s, $cutoff),
            ]);
        }

        foreach ($this->awards->pick($stats) as $award) {
            Award::create([
                'game_session_id' => $session->id,
                'player_id' => $award['player_id'],
                'key' => $award['key'],
                'place' => $award['place'],
                'value' => $award['value'],
                'tie_break' => $award['tie_break'],
            ]);
        }

        $rows = $session->stats()->with('player')->whereHas('player', fn ($q) => $q->where('is_bot', false))->get();
        $awards = $session->awards()->with('player')->orderBy('id')->get();

        $session->analysis_beats = Beats::build($session, $rows, $awards, $this->room($session, $log, $humans), Comparison::build($session, $rows));
        $session->save();
    }

    /**
     * Room-wide numbers for the first two beats: human choices only, timeouts excluded.
     *
     * @param  array<int, array<string, mixed>>  $humans
     * @return array<string, mixed>
     */
    private function room(GameSession $session, DecisionLog $log, array $humans): array
    {
        $shares = 0;
        $chosen = 0;
        $byIndex = [];
        foreach (array_keys($humans) as $id) {
            foreach ($log->histories[$id] as $round) {
                foreach ($round['moves'] as $m) {
                    if ($m['timed_out']) {
                        continue;
                    }
                    $chosen++;
                    $byIndex[$m['i']][1] = ($byIndex[$m['i']][1] ?? 0) + 1;
                    if ($m['me'] === 'share') {
                        $shares++;
                        $byIndex[$m['i']][0] = ($byIndex[$m['i']][0] ?? 0) + 1;
                    }
                }
            }
        }

        $series = [];
        for ($i = 1; $i <= $session->decisions_per_round; $i++) {
            $series[] = ['decision' => $i, 'share_rate' => Stats::rate($byIndex[$i][0] ?? 0, $byIndex[$i][1] ?? 0)];
        }

        return [
            'share_rate' => Stats::rate($shares, $chosen),
            'series' => $series,
            'total_points' => array_sum(array_map(fn ($s) => $s['total_points'], $humans)),
            'max_cooperative_points' => count($humans) * $session->rounds_count * $session->decisions_per_round
                * (int) config('game.analysis.cooperative_points_per_decision'),
        ];
    }
}
