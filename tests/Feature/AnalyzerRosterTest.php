<?php

use App\Analysis\Analyzer;
use App\Models\GameSession;
use App\Simulation\Personality;
use Tests\Support\ScriptedGame;

/**
 * The plan's simulator roster (docs/PLAN.md "Testing"), played into a decision log under a
 * fixed seed and run through the real analyzer.
 *
 * Two kinds of assertion. The seed-1 snapshot is exact: if a threshold in config/game.php
 * moves, it fails here rather than surprising a room. The cross-seed checks are what the
 * ladder can promise regardless of who met whom, with the documented overlaps:
 *
 *   - a copycat (mirror, speedster, double-tapper, ghost) that met only sharers is a saint;
 *   - a diplomat that was never stolen from twice in a row never had to forgive, so it is a mirror
 *     (and a saint in a kind draw);
 *   - a grudge that was never offered an olive branch is a mirror, one that shared
 *     generously until it was stabbed on decision 8 or 9 is a backstabber, and one that was
 *     never stolen from at all is a saint;
 *   - wildcards and pragmatists trade places at the predictability cutoff, and a coin-flipper
 *     who happened to forgive is a diplomat;
 *   - a pragmatist who forgave and never pounced is a diplomat; one whose steals all landed on
 *     sharers is an opportunist.
 */
const OVERLAPS = [
    'saint' => ['saint'],
    'wall' => ['wall'],
    'mirror' => ['mirror', 'saint'],
    'speedster' => ['mirror', 'saint'],
    'double_tapper' => ['mirror', 'saint'],
    'ghost' => ['mirror', 'saint'],
    'grudge' => ['grudge', 'mirror', 'backstabber', 'saint'],
    'diplomat' => ['diplomat', 'mirror', 'saint'],
    'backstabber' => ['backstabber'],
    'opportunist' => ['opportunist'],
    'wildcard' => ['wildcard', 'pragmatist', 'diplomat'],
    'pragmatist' => ['pragmatist', 'wildcard', 'diplomat', 'mirror', 'opportunist'],
    'sleeper' => ['pragmatist'],
    'straggler' => ['pragmatist'],
];

const SEED_ONE = [
    'saint_1' => 'saint', 'saint_2' => 'saint', 'wall_1' => 'wall', 'wall_2' => 'wall',
    'mirror_1' => 'saint', 'mirror_2' => 'mirror', 'mirror_3' => 'mirror', 'mirror_4' => 'saint',
    'grudge_1' => 'grudge', 'grudge_2' => 'grudge', 'diplomat_1' => 'saint', 'diplomat_2' => 'mirror',
    'backstabber_1' => 'backstabber', 'backstabber_2' => 'backstabber', 'opportunist_1' => 'opportunist', 'opportunist_2' => 'opportunist',
    'opportunist_3' => 'opportunist', 'wildcard_1' => 'wildcard', 'wildcard_2' => 'wildcard', 'pragmatist_1' => 'pragmatist',
    'pragmatist_2' => 'pragmatist', 'pragmatist_3' => 'pragmatist', 'pragmatist_4' => 'pragmatist', 'pragmatist_5' => 'pragmatist',
    'sleeper_1' => 'pragmatist', 'sleeper_2' => 'pragmatist', 'ghost_1' => 'mirror', 'straggler_1' => 'pragmatist',
    'speedster_1' => 'mirror', 'double_tapper_1' => 'mirror',
];

function rosterSession(int $seed = 1, array $roster = Personality::ROSTER): GameSession
{
    $session = ScriptedGame::make(seed: $seed)->roster($roster)->play();
    app(Analyzer::class)->analyze($session);

    return $session->fresh();
}

function archetypesOf(GameSession $session): array
{
    return $session->stats()->with('player')->get()
        ->filter(fn ($s) => ! $s->player->is_bot)
        ->mapWithKeys(fn ($s) => [$s->player->username => $s->archetype->value])
        ->all();
}

it('lands every personality exactly where the thresholds put it (seed 1 snapshot)', function () {
    $map = archetypesOf(rosterSession(1));
    if ($map !== SEED_ONE) {
        // Thresholds moved. If that was on purpose, this is the new snapshot:
        fwrite(STDERR, "\nSEED_ONE = ".var_export($map, true)."\n");
    }
    expect($map)->toBe(SEED_ONE);
});

it('lands every personality inside its documented range, whoever it met', function (int $seed) {
    $session = rosterSession($seed);
    $wrong = [];
    foreach (archetypesOf($session) as $name => $got) {
        $kind = preg_replace('/_\d+$/', '', $name);
        if (! in_array($got, OVERLAPS[$kind], true)) {
            $s = ScriptedGame::statsOf($session, $name);
            $wrong[] = "$name => $got (share {$s->share_rate}, match {$s->match_rate}, forgive {$s->forgiveness}, exploit {$s->exploitation_rate}, predict {$s->predictability})";
        }
    }
    expect($wrong)->toBe([]);

    // The room gets a spread, not twenty Pragmatists.
    $census = collect(archetypesOf($session))->countBy();
    expect($census->max())->toBeLessThan(15)->and($census->count())->toBeGreaterThanOrEqual(7);
})->with([1, 2, 3, 4]);

it('hands out the awards the plan promises', function (int $seed) {
    $session = rosterSession($seed);
    $awards = $session->awards()->with('player')->get()->keyBy(fn ($a) => $a->key->value.($a->place > 1 ? $a->place : ''));
    $name = fn (string $key) => $awards[$key]->player->username;
    $stat = fn (string $key) => ScriptedGame::statsOf($session, $name($key));

    expect($stat('kindest')->share_rate)->toBe(1.0)                         // a saint, or a copycat nobody ever stole from
        ->and($name('most_ruthless'))->toStartWith('wall_')
        ->and($stat('most_forgiving')->forgiveness)->toBe(1.0)              // a diplomat, unless a saint or an opportunist out-pointed it
        ->and($name('endgame_assassin'))->toStartWith('backstabber_')
        ->and($name('cold_blooded'))->toStartWith('opportunist_')
        ->and($name('unreadable'))->toMatch('/^(wildcard|pragmatist)_/')
        ->and($name('fastest_thumb'))->toBe('speedster_1')
        ->and($name('most_betrayed'))->not->toMatch('/^(sleeper|straggler)_/')
        ->and($name('best_partner'))->not->toMatch('/^(sleeper|straggler)_/')
        ->and($awards)->toHaveKeys(['champion', 'champion2', 'champion3']);
})->with([1, 2, 3]);

it('treats sleepers, stragglers and the ghost as the plan says', function () {
    $session = rosterSession();
    foreach (['sleeper_1', 'sleeper_2', 'straggler_1'] as $name) {
        $stat = ScriptedGame::statsOf($session, $name);
        expect($stat->timeouts)->toBe(50)->and($stat->share_rate)->toBeNull()->and($stat->avg_response_ms)->toBeNull();
        expect(ScriptedGame::awardsOf($session, $name))->toBe([]);
    }
    $ghost = ScriptedGame::statsOf($session, 'ghost_1');
    expect($ghost->timeouts)->toBe(3)->and($ghost->decisions_count)->toBe(50);
    expect(ScriptedGame::statsOf($session, 'speedster_1')->avg_response_ms)->toBe(200);
});

it('gives The Machine a stats row but no rank, archetype or award', function () {
    $session = rosterSession(1, [['saint', 2], ['wall', 1]]); // three humans: the bot fills the seat
    $bot = $session->players()->where('is_bot', true)->sole();
    $stat = $session->stats()->where('player_id', $bot->id)->sole();
    expect($stat->rank)->toBeNull()->and($stat->archetype)->toBeNull()->and($stat->decisions_count)->toBe(50);
    expect($session->awards()->where('player_id', $bot->id)->count())->toBe(0);
    expect(collect($session->analysis_beats)->where('type', 'archetype_reveal'))->toHaveCount(3);
});

it('keeps a kicked player in the stats but out of the awards', function () {
    $session = ScriptedGame::make(rounds: 2, decisions: 4)->add('a', Personality::Saint)->add('b', Personality::Saint)->add('c', Personality::Wall)->add('d', Personality::Wall)->play();
    ScriptedGame::player($session, 'a')->update(['kicked_at' => now()]);
    app(Analyzer::class)->analyze($session);
    expect(ScriptedGame::statsOf($session, 'a')->archetype->value)->toBe('saint')
        ->and(ScriptedGame::awardsOf($session, 'a'))->toBe([])
        ->and(ScriptedGame::awardsOf($session, 'b'))->toContain('kindest');
});
