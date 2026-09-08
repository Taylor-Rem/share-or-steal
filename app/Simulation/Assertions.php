<?php

namespace App\Simulation;

/**
 * What the roster promises (docs/PLAN.md "Testing"), checked against a finished game.
 * Each check is named. Hard failures exit non-zero; a "soft" pass is a result inside a
 * documented overlap of the archetype ladder (see tests/Feature/AnalyzerRosterTest.php)
 * and is reported so thresholds can be tuned, not failed.
 */
final class Assertions
{
    /** The documented landing ranges per personality kind. */
    public const RANGES = [
        'saint' => ['saint'],
        'wall' => ['wall'],
        'mirror' => ['mirror', 'saint'],
        'speedster' => ['mirror', 'saint'],
        'double_tapper' => ['mirror', 'saint'],
        'ghost' => ['mirror', 'saint'],
        'grudge' => ['grudge', 'mirror', 'backstabber'],
        'diplomat' => ['diplomat', 'mirror', 'saint'],
        'backstabber' => ['backstabber'],
        'opportunist' => ['opportunist'],
        'wildcard' => ['wildcard', 'pragmatist', 'diplomat'],
        'pragmatist' => ['pragmatist', 'wildcard', 'diplomat', 'mirror', 'opportunist'],
        'sleeper' => ['pragmatist'],
        'straggler' => ['pragmatist'],
    ];

    /** @var list<array{name: string, ok: bool, soft: bool, detail: string}> */
    public array $results = [];

    /**
     * @param  list<array<string, mixed>>  $stats  PlayerStats rows from the analysis endpoint
     * @param  list<array<string, mixed>>  $awards  Award rows
     * @param  array<string, array<string, mixed>>  $records  player name => SimulatedPlayer::$record
     * @param  array<string, string>  $kinds  player name => personality kind
     */
    public function run(array $stats, array $awards, array $records, array $kinds, int $rounds, int $decisions, bool $expectBot, array $directorPlayers): array
    {
        $byName = collect($stats)->keyBy(fn ($s) => $s['player']['username']);
        $awardsByKey = collect($awards)->keyBy(fn ($a) => $a['key'].((($a['place'] ?? 1) > 1) ? $a['place'] : ''));
        $winner = fn (string $key) => $awardsByKey[$key]['winner']['username'] ?? null;
        $statOf = fn (?string $name, string $stat) => $name === null ? null : ($byName[$name][$stat] ?? null);
        $total = $rounds * $decisions;

        // Archetypes.
        foreach ($kinds as $name => $kind) {
            $expected = Personality::from($kind)->expectedArchetype();
            $got = $byName[$name]['archetype']['key'] ?? null;
            $range = self::RANGES[$kind] ?? [];
            if ($expected === null) {
                $this->add("archetype {$name}", true, false, "{$got} (no promise)");
            } elseif ($got === $expected) {
                $this->add("archetype {$name}", true, false, $got);
            } elseif (in_array($got, $range, true)) {
                $this->add("archetype {$name}", true, true, "{$got}, wanted {$expected} (documented overlap)");
            } else {
                $this->add("archetype {$name}", false, false, "{$got}, wanted {$expected}; share {$byName[$name]['share_rate']} match ".($byName[$name]['match_rate'] ?? '?')." forgive {$byName[$name]['forgiveness']} exploit {$byName[$name]['exploitation_rate']} predict {$byName[$name]['predictability']}");
            }
        }

        // Everyone played every decision.
        foreach ($kinds as $name => $kind) {
            $count = $byName[$name]['decisions_count'] ?? null;
            $this->add("decisions {$name}", $count === $total, false, "{$count} of {$total}");
        }

        // The Machine only in an odd room.
        $bots = collect($directorPlayers)->where('is_bot', true)->count();
        $this->add('the machine', $bots === ($expectBot ? 1 : 0), false, $expectBot ? "{$bots} bot in an odd room" : "{$bots} bots in an even room");

        // Awards.
        $starts = fn (?string $name, string $prefix) => $name !== null && str_starts_with($name, $prefix);
        $this->add('kindest shares 100%', $statOf($winner('kindest'), 'share_rate') == 1.0, false, ($winner('kindest') ?? 'nobody').' at '.($statOf($winner('kindest'), 'share_rate') ?? '—'));
        $this->add('most ruthless is a wall', $starts($winner('most_ruthless'), 'wall_'), false, $winner('most_ruthless') ?? 'nobody');
        $this->add('most forgiving forgave 100%', $statOf($winner('most_forgiving'), 'forgiveness') == 1.0, false, ($winner('most_forgiving') ?? 'nobody').' at '.($statOf($winner('most_forgiving'), 'forgiveness') ?? '—'));
        $this->add('endgame assassin is a backstabber', $starts($winner('endgame_assassin'), 'backstabber_'), false, $winner('endgame_assassin') ?? 'nobody');
        $this->add('cold blooded is an opportunist', $starts($winner('cold_blooded'), 'opportunist_'), false, $winner('cold_blooded') ?? 'nobody');
        $unreadable = $winner('unreadable');
        $this->add('unreadable is a wildcard', $starts($unreadable, 'wildcard_') || $starts($unreadable, 'pragmatist_'), ! $starts($unreadable, 'wildcard_'), $unreadable ?? 'nobody');
        $this->add('fastest thumb is the speedster', $winner('fastest_thumb') === 'speedster_1', false, ($winner('fastest_thumb') ?? 'nobody').' at '.($statOf($winner('fastest_thumb'), 'avg_response_ms') ?? '—').' ms');
        foreach (['most_betrayed', 'best_partner'] as $key) {
            $w = $winner($key);
            $this->add("{$key} is not a sleeper", $w !== null && ! $starts($w, 'sleeper_') && ! $starts($w, 'straggler_'), false, $w ?? 'nobody');
        }
        $this->add('podium has three places', isset($awardsByKey['champion'], $awardsByKey['champion2'], $awardsByKey['champion3']), false, implode(', ', array_filter([$winner('champion'), $winner('champion2'), $winner('champion3')])));

        // The special phones.
        foreach ($kinds as $name => $kind) {
            $r = $records[$name] ?? [];
            $s = $byName[$name] ?? [];
            switch ($kind) {
                case 'sleeper':
                    $this->add("{$name} all timeouts", ($s['timeouts'] ?? null) === $total, false, "{$s['timeouts']} timeouts, {$r['attempts']} attempts");
                    $this->add("{$name} nudged", ($r['nudges'] ?? 0) >= 1, false, "{$r['nudges']} nudges");
                    $this->add("{$name} wins nothing", ! collect($awards)->contains(fn ($a) => $a['winner']['username'] === $name), false, '');
                    break;
                case 'straggler':
                    $this->add("{$name} all rejected", ($r['accepted'] ?? 0) === 0 && ($r['attempts'] ?? 0) === $total, false, "{$r['attempts']} attempts, {$r['accepted']} accepted, ".json_encode($r['rejections']));
                    $this->add("{$name} all timeouts", ($s['timeouts'] ?? null) === $total, false, "{$s['timeouts']} timeouts");
                    break;
                case 'double_tapper':
                    // Every second tap is refused (already_chosen, or too late on a slow box); the first stands.
                    $rejected = array_sum($r['rejections'] ?? []);
                    $this->add("{$name} second tap ignored", $rejected >= $total && ($r['accepted'] ?? 0) >= $total - 2, false, "{$r['accepted']} accepted, {$rejected} rejected of {$r['attempts']} taps: ".json_encode($r['rejections']));
                    $this->add("{$name} first choice kept", ($r['first_choice_lost'] ?? 0) === 0 && ($r['first_choice_kept'] ?? 0) >= $total - 2, false, "{$r['first_choice_kept']} kept, {$r['first_choice_lost']} lost");
                    break;
                case 'ghost':
                    [$gr, $from, $to] = Personality::Ghost->strategy()->offline();
                    $missed = $to - $from + 1;
                    $this->add("{$name} reconnected", ($r['reconnects'] ?? 0) === 1, false, "{$r['reconnects']} reconnects");
                    // The missed decisions are timeouts; a slow reconnect may cost one or two more.
                    $timeouts = $s['timeouts'] ?? -1;
                    $this->add("{$name} missed decisions are timeouts, nothing else", $timeouts >= $missed && $timeouts <= $missed + 2 && ($s['decisions_count'] ?? null) === $total, false, "{$timeouts} timeouts (expected {$missed}), {$r['accepted']} accepted");
                    break;
                case 'speedster':
                    $this->add("{$name} answers fast", ($s['avg_response_ms'] ?? PHP_INT_MAX) < 600, false, "{$s['avg_response_ms']} ms");
                    break;
            }
            if (($r['auth_failures'] ?? 0) > 0) {
                $this->add("{$name} channel auth", false, false, "{$r['auth_failures']} auth failures");
            }
        }

        return $this->results;
    }

    private function add(string $name, bool $ok, bool $soft, string $detail): void
    {
        $this->results[] = ['name' => $name, 'ok' => $ok, 'soft' => $soft, 'detail' => $detail];
    }

    public function failed(): int
    {
        return count(array_filter($this->results, fn ($r) => ! $r['ok']));
    }

    public function soft(): int
    {
        return count(array_filter($this->results, fn ($r) => $r['ok'] && $r['soft']));
    }
}
