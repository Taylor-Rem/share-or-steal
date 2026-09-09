<?php

use App\Analysis\Awards;
use App\Analysis\Ladder;
use App\Enums\Archetype;
use App\Enums\AwardKey;
use Random\Engine\Mt19937;
use Random\Randomizer;

function baseStats(array $over = []): array
{
    return $over + [
        'total_points' => 100, 'decisions_count' => 50, 'timeouts' => 0, 'shares' => 25, 'steals' => 25,
        'share_rate' => 0.5, 'opening_move' => 0.5, 'retaliation' => 0.5, 'forgiveness' => 0.3, 'betrayals' => 2,
        'exploitation' => 5, 'exploitation_rate' => 0.2, 'endgame_shift' => 0.0, 'early_share_rate' => 0.5, 'late_share_rate' => 0.5,
        'predictability' => 0.6, 'partner_yield' => 2.0, 'sucker_count' => 4, 'times_stolen_from' => 8, 'match_rate' => 0.5,
        'post_steal_share_rate' => 0.5, 'olive_branch_share_rate' => 0.5, 'avg_response_ms' => 1500, 'eligible' => true,
    ];
}

it('walks the ladder top to bottom with the configured thresholds', function (array $over, Archetype $expected) {
    expect(Ladder::assign(baseStats($over), 0.3))->toBe($expected);
})->with([
    'saint' => [['share_rate' => 0.95], Archetype::Saint],
    'a mirror that slipped twice is not a saint' => [['share_rate' => 0.94, 'match_rate' => 1.0], Archetype::Mirror],
    'wall' => [['share_rate' => 0.15], Archetype::Wall],
    'backstabber' => [['endgame_shift' => -0.4, 'early_share_rate' => 0.8], Archetype::Backstabber],
    'not a backstabber if the early rate was low' => [['endgame_shift' => -0.4, 'early_share_rate' => 0.7], Archetype::Pragmatist],
    'grudge' => [['post_steal_share_rate' => 0.1, 'olive_branch_share_rate' => 0.0], Archetype::Grudge],
    'a mirror against a wall is not a grudge' => [['post_steal_share_rate' => 0.0, 'olive_branch_share_rate' => null, 'match_rate' => 1.0], Archetype::Mirror],
    'an opportunist refusing olive branches is not a grudge' => [['post_steal_share_rate' => 0.5, 'olive_branch_share_rate' => 0.0, 'exploitation_rate' => 1.0], Archetype::Opportunist],
    'mirror' => [['match_rate' => 0.95], Archetype::Mirror],
    'a diplomat who copies most of the time is not a mirror' => [['match_rate' => 0.93, 'forgiveness' => 0.8, 'share_rate' => 0.9, 'exploitation_rate' => null], Archetype::Diplomat],
    'diplomat' => [['forgiveness' => 0.6, 'share_rate' => 0.6, 'exploitation_rate' => 0.5], Archetype::Diplomat],
    'diplomat with no steals at all' => [['forgiveness' => 0.6, 'share_rate' => 0.6, 'exploitation_rate' => null], Archetype::Diplomat],
    'not a diplomat if the steals were pounces' => [['forgiveness' => 0.6, 'share_rate' => 0.6, 'exploitation_rate' => 0.6], Archetype::Pragmatist],
    'opportunist' => [['exploitation_rate' => 0.8, 'share_rate' => 0.4], Archetype::Opportunist],
    'opportunist needs a middling share rate' => [['exploitation_rate' => 0.8, 'share_rate' => 0.8], Archetype::Pragmatist],
    'a coin-flipper is not an opportunist' => [['exploitation_rate' => 0.6, 'share_rate' => 0.5, 'predictability' => 0.7], Archetype::Pragmatist],
    'wildcard' => [['predictability' => 0.3], Archetype::Wildcard],
    'pragmatist' => [[], Archetype::Pragmatist],
    'saint beats mirror' => [['share_rate' => 0.98, 'match_rate' => 1.0], Archetype::Saint],
    'null rates never match' => [['share_rate' => null, 'match_rate' => null, 'post_steal_share_rate' => null], Archetype::Pragmatist],
]);

it('reads the thresholds from config, so a change moves the ladder', function () {
    config(['game.thresholds.saint.share_rate_min' => 0.99]);
    expect(Ladder::assign(baseStats(['share_rate' => 0.95]), null))->toBe(Archetype::Pragmatist);
});

it('finds the wildcard cutoff at the bottom fraction of the room', function () {
    config(['game.thresholds.wildcard.predictability_bottom_fraction' => 0.10]);
    expect(Ladder::cutoff([0.9, 0.8, null, 0.4, 0.7, 0.5]))->toBeNull()   // 5 values * 0.10 -> 0 players
        ->and(Ladder::cutoff(array_map(fn ($i) => $i / 20, range(1, 20))))->toBe(0.10) // 2 of 20: 0.05, 0.10
        ->and(Ladder::cutoff([]))->toBeNull();
});

it('picks every award with the eligibility rules and records tie-breaks', function () {
    $stats = [
        1 => baseStats(['share_rate' => 0.9, 'forgiveness' => 0.8, 'times_stolen_from' => 5, 'total_points' => 120]),
        2 => baseStats(['share_rate' => 0.1, 'betrayals' => 6, 'endgame_shift' => -0.5, 'total_points' => 150]),
        3 => baseStats(['forgiveness' => 0.9, 'times_stolen_from' => 2, 'predictability' => 0.2, 'avg_response_ms' => 300, 'total_points' => 150]),
        4 => baseStats(['share_rate' => null, 'shares' => 0, 'timeouts' => 50, 'total_points' => 0]),
        5 => baseStats(['share_rate' => 0.9, 'total_points' => 90, 'sucker_count' => 10, 'partner_yield' => 3.5]),
        6 => baseStats(['eligible' => false, 'share_rate' => 1.0, 'total_points' => 999]),
    ];
    $awards = collect((new Awards(new Randomizer(new Mt19937(7))))->pick($stats))->keyBy(fn ($a) => $a['key']->value.($a['place'] > 1 ? $a['place'] : ''));

    expect($awards['kindest'])->toMatchArray(['player_id' => 1, 'tie_break' => 'points'])   // 1 and 5 tie at 0.9; 1 has more points
        ->and($awards['most_forgiving']['player_id'])->toBe(1)      // 3 forgave more but was stolen from only twice
        ->and($awards['most_ruthless']['player_id'])->toBe(2)
        ->and($awards['best_partner']['player_id'])->toBe(5)
        ->and($awards['most_betrayed']['player_id'])->toBe(5)
        ->and($awards['cold_blooded']['player_id'])->toBe(2)
        ->and($awards['endgame_assassin'])->toMatchArray(['player_id' => 2, 'value' => -0.5])
        ->and($awards['unreadable']['player_id'])->toBe(3)
        ->and($awards['fastest_thumb']['player_id'])->toBe(3)
        ->and($awards['champion']['tie_break'])->toBe('coin_flip')    // 2 and 3 tie at 150
        ->and(collect([$awards['champion']['player_id'], $awards['champion2']['player_id']])->sort()->values()->all())->toBe([2, 3])
        ->and($awards['champion3']['player_id'])->toBe(1)
        ->and($awards->keys()->filter(fn ($k) => str_contains($k, 'champion')))->toHaveCount(3)
        ->and(collect($awards)->pluck('player_id')->contains(6))->toBeFalse();
});

it('withholds count awards nobody earned', function () {
    $stats = [1 => baseStats(['betrayals' => 0, 'sucker_count' => 0, 'endgame_shift' => 0.1]), 2 => baseStats(['betrayals' => 0, 'sucker_count' => 0, 'endgame_shift' => 0.2])];
    $keys = collect((new Awards)->pick($stats))->map(fn ($a) => $a['key']->value)->all();
    expect($keys)->not->toContain('cold_blooded')->not->toContain('most_betrayed')->not->toContain('endgame_assassin')->toContain('kindest');
});

it('never gives an all-timeout player Kindest', function () {
    $stats = [1 => baseStats(['share_rate' => 1.0, 'shares' => 0, 'timeouts' => 50]), 2 => baseStats(['share_rate' => 0.6])];
    $kindest = collect((new Awards)->pick($stats))->first(fn ($a) => $a['key'] === AwardKey::Kindest);
    expect($kindest['player_id'])->toBe(2);
});
