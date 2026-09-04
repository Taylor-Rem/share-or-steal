<?php

use App\Game\Pairer;

/**
 * Plays `$rounds` rounds for `$humans` players and returns every round's pairs.
 *
 * @return list<list<array{0: int, 1: int}>>
 */
function playPairings(int $humans, int $rounds, ?bool $avoidRepeats = null): array
{
    $pairer = new Pairer;
    $ids = range(1, $humans);
    $bot = $humans % 2 === 1 ? 1000 : null;
    if ($bot) {
        $ids[] = $bot;
    }
    $avoid = $avoidRepeats ?? $humans >= 10;

    $previous = [];
    $all = [];
    for ($r = 0; $r < $rounds; $r++) {
        $pairs = $pairer->pair($ids, $bot, $previous, $avoid);
        foreach ($pairs as [$a, $b]) {
            $previous[$a][] = $b;
            $previous[$b][] = $a;
        }
        $all[] = $pairs;
    }

    return $all;
}

it('pairs everyone once per round with no repeats and a fair Machine', function (int $humans) {
    $rounds = playPairings($humans, 5);
    $bot = $humans % 2 === 1 ? 1000 : null;
    $expected = range(1, $humans);
    if ($bot) {
        $expected[] = $bot;
    }

    $seen = [];
    $botPartners = [];
    foreach ($rounds as $pairs) {
        $ids = collect($pairs)->flatten()->sort()->values()->all();
        expect($ids)->toBe($expected, 'every player has exactly one partner');

        foreach ($pairs as [$a, $b]) {
            $edge = Pairer::edge($a, $b);
            expect($seen)->not->toHaveKey($edge, "$a and $b were paired twice");
            $seen[$edge] = true;
            if ($a === $bot) {
                $botPartners[] = $b;
            } elseif ($b === $bot) {
                $botPartners[] = $a;
            }
        }
    }

    if ($bot) {
        expect($botPartners)->toHaveCount(5)
            ->and(array_unique($botPartners))->toHaveCount(5, 'nobody draws The Machine twice');
    } else {
        expect($botPartners)->toBeEmpty();
    }
})->with([20, 21, 25, 30]);

it('never puts The Machine in an even room', function () {
    foreach (playPairings(20, 5) as $pairs) {
        expect(collect($pairs)->flatten()->contains(1000))->toBeFalse();
    }
});

it('allows repeat partners below the threshold rather than failing', function () {
    $rounds = playPairings(4, 5, avoidRepeats: false);
    expect($rounds)->toHaveCount(5);
    foreach ($rounds as $pairs) {
        expect(collect($pairs)->flatten()->sort()->values()->all())->toBe([1, 2, 3, 4]);
    }
});

it('still avoids repeats for ten players across five rounds', function () {
    $seen = [];
    foreach (playPairings(10, 5) as $pairs) {
        foreach ($pairs as [$a, $b]) {
            $edge = Pairer::edge($a, $b);
            expect($seen)->not->toHaveKey($edge);
            $seen[$edge] = true;
        }
    }
});

it('rotates The Machine even when repeats are allowed', function () {
    $partners = [];
    foreach (playPairings(5, 5, avoidRepeats: false) as $pairs) {
        foreach ($pairs as [$a, $b]) {
            if ($a === 1000 || $b === 1000) {
                $partners[] = $a === 1000 ? $b : $a;
            }
        }
    }
    // Five humans, five rounds: every human draws The Machine exactly once.
    expect(collect($partners)->sort()->values()->all())->toBe([1, 2, 3, 4, 5]);
});

it('refuses an odd number of players', function () {
    (new Pairer)->pair([1, 2, 3], null, [], false);
})->throws(InvalidArgumentException::class);
