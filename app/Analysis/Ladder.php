<?php

namespace App\Analysis;

use App\Enums\Archetype;

/**
 * The archetype ladder from docs/PLAN.md, evaluated top to bottom; the first rule that
 * matches wins. Every threshold comes from config('game.thresholds') and nowhere else.
 */
final class Ladder
{
    /**
     * @param  array<string, mixed>  $s  a Stats::compute() array
     * @param  float|null  $wildcardCutoff  the room's predictability at the bottom fraction (see cutoff())
     */
    public static function assign(array $s, ?float $wildcardCutoff): Archetype
    {
        $t = config('game.thresholds');
        $ge = fn ($value, $min) => $value !== null && $value >= $min;
        $le = fn ($value, $max) => $value !== null && $value <= $max;

        return match (true) {
            $ge($s['share_rate'], $t['saint']['share_rate_min']) => Archetype::Saint,
            $le($s['share_rate'], $t['wall']['share_rate_max']) => Archetype::Wall,
            $le($s['endgame_shift'], $t['backstabber']['endgame_shift_max'])
                && $ge($s['early_share_rate'], $t['backstabber']['early_share_rate_min']) => Archetype::Backstabber,
            $le($s['post_steal_share_rate'], $t['grudge']['post_steal_share_rate_max'])
                && $le($s['olive_branch_share_rate'], $t['grudge']['olive_branch_share_rate_max']) => Archetype::Grudge,
            $ge($s['match_rate'], $t['mirror']['match_rate_min']) => Archetype::Mirror,
            $ge($s['forgiveness'], $t['diplomat']['forgiveness_min'])
                && $ge($s['share_rate'], $t['diplomat']['share_rate_min'])
                && ($s['exploitation_rate'] === null || $s['exploitation_rate'] <= $t['diplomat']['exploitation_rate_max']) => Archetype::Diplomat,
            $ge($s['exploitation_rate'], $t['opportunist']['exploitation_share_of_steals_min'])
                && $ge($s['share_rate'], $t['opportunist']['share_rate_min'])
                && $le($s['share_rate'], $t['opportunist']['share_rate_max']) => Archetype::Opportunist,
            $wildcardCutoff !== null && $le($s['predictability'], $wildcardCutoff) => Archetype::Wildcard,
            default => Archetype::Pragmatist,
        };
    }

    /**
     * The predictability value at the room's bottom fraction: players at or below it are
     * Wildcards. Null when the room is too small for the fraction to name anyone.
     *
     * @param  list<float|null>  $predictabilities
     */
    public static function cutoff(array $predictabilities): ?float
    {
        $values = array_values(array_filter($predictabilities, fn ($v) => $v !== null));
        sort($values);
        $k = (int) floor(count($values) * (float) config('game.thresholds.wildcard.predictability_bottom_fraction'));

        return $k > 0 ? $values[$k - 1] : null;
    }
}
