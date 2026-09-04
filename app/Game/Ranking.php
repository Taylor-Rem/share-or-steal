<?php

namespace App\Game;

/** Standard competition ranking: ties share the higher rank (1, 1, 3). */
final class Ranking
{
    /**
     * @param  array<int, int|float>  $scores  id => score, higher is better
     * @return array<int, int> id => rank
     */
    public static function ranks(array $scores): array
    {
        arsort($scores, SORT_NUMERIC);

        $ranks = [];
        $position = 0;
        $previous = null;
        $rank = 0;
        foreach ($scores as $id => $score) {
            $position++;
            if ($previous === null || $score < $previous) {
                $rank = $position;
                $previous = $score;
            }
            $ranks[$id] = $rank;
        }

        return $ranks;
    }
}
