<?php

use App\Enums\Archetype;
use App\Enums\Choice;

it('scores the four payoff cases from config', function (Choice $you, Choice $them, int $points) {
    expect($you->pointsAgainst($them))->toBe($points);
})->with([
    'both share' => [Choice::Share, Choice::Share, 3],
    'you share, they steal' => [Choice::Share, Choice::Steal, 0],
    'you steal, they share' => [Choice::Steal, Choice::Share, 5],
    'both steal' => [Choice::Steal, Choice::Steal, 1],
]);

it('has a threshold block for every archetype except the fallback', function () {
    $keys = array_keys(config('game.thresholds'));
    $expected = collect(Archetype::cases())->reject(fn ($a) => $a === Archetype::Pragmatist)->map->value->all();

    expect($keys)->toBe($expected);
});

it('has both clock profiles with every phase', function () {
    foreach (['normal', 'fast'] as $profile) {
        expect(config("game.durations.$profile"))->toHaveKeys(['pairing_reveal', 'choose', 'reveal', 'round_summary']);
    }
    expect(config('game.durations.fast.choose'))->toBeLessThan(config('game.durations.normal.choose'));
});
