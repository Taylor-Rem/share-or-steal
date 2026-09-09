<?php

/**
 * Score candidate archetype thresholds against real sessions, offline.
 *
 *   php artisan tinker --execute="require 'scripts/tune-thresholds.php';"
 *
 * Recomputes every stat from the decision log of each session in $codes (so internal
 * rates like match_rate are available), then runs the ladder under each candidate
 * threshold set and prints where every player landed and the largest group per room.
 * Names are matched to roster personalities by prefix when they have one (saint_1…).
 */

use App\Analysis\DecisionLog;
use App\Analysis\Ladder;
use App\Analysis\Stats;
use App\Models\GameSession;

$codes = $codes ?? ['XLHV', 'MFNG', 'QUFA', 'QJTL'];
$candidates = $candidates ?? [
    'current' => [],
    'mirror 0.90' => ['mirror.match_rate_min' => 0.90],
    'mirror 0.99' => ['mirror.match_rate_min' => 0.99],
    'saint 0.90' => ['saint.share_rate_min' => 0.90],
    'wildcard 0.15' => ['wildcard.predictability_bottom_fraction' => 0.15],
];

$rooms = [];
foreach ($codes as $code) {
    $session = GameSession::where('code', $code)->first();
    if (! $session) {
        echo "no session $code\n";

        continue;
    }
    $log = DecisionLog::for($session);
    foreach ($log->players as $id => $player) {
        if (! $player->is_bot) {
            $rooms[$code][$player->username] = Stats::compute($log->histories[$id]);
        }
    }
}

$base = config('game.thresholds');
foreach ($candidates as $label => $over) {
    config(['game.thresholds' => $base]);
    foreach ($over as $k => $v) {
        config(["game.thresholds.$k" => $v]);
    }
    $landed = [];
    $census = [];
    foreach ($rooms as $code => $room) {
        $cutoff = Ladder::cutoff(array_map(fn ($s) => $s['predictability'], array_values($room)));
        foreach ($room as $name => $s) {
            $arch = Ladder::assign($s, $cutoff)->value;
            $kind = preg_match('/^([a-z_]+)_\d+$/', $name, $m) ? $m[1] : $name;
            $landed[$kind][$arch] = ($landed[$kind][$arch] ?? 0) + 1;
            $census[$code][$arch] = ($census[$code][$arch] ?? 0) + 1;
        }
    }
    echo "\n== $label\n";
    ksort($landed);
    foreach ($landed as $kind => $where) {
        arsort($where);
        echo str_pad($kind, 16), json_encode($where), "\n";
    }
    foreach ($census as $code => $c) {
        arsort($c);
        echo "   $code: ", json_encode($c), "\n";
    }
}
