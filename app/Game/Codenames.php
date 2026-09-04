<?php

namespace App\Game;

/** "Blue Heron" and friends, from config('game.codenames'), unique within one draw. */
final class Codenames
{
    /** @return list<string> */
    public function draw(int $count): array
    {
        $adjectives = config('game.codenames.adjectives');
        $animals = config('game.codenames.animals');

        $all = [];
        foreach ($adjectives as $adjective) {
            foreach ($animals as $animal) {
                $all[] = "$adjective $animal";
            }
        }

        if ($count > count($all)) {
            throw new \InvalidArgumentException('Only '.count($all)." codenames exist; $count requested.");
        }

        shuffle($all);

        return array_slice($all, 0, $count);
    }
}
