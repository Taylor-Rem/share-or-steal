<?php

use App\Game\Ranking;

it('ranks with ties sharing the higher rank', function () {
    expect(Ranking::ranks([7 => 10, 8 => 30, 9 => 30, 10 => 5]))->toBe([8 => 1, 9 => 1, 7 => 3, 10 => 4]);
});

it('ranks an empty room', function () {
    expect(Ranking::ranks([]))->toBe([]);
});
