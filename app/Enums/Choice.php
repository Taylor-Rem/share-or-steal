<?php

namespace App\Enums;

enum Choice: string
{
    case Share = 'share';
    case Steal = 'steal';

    /** Points earned by a player who chose $this against a partner who chose $them. */
    public function pointsAgainst(Choice $them): int
    {
        return (int) config("game.payoffs.{$this->value}.{$them->value}");
    }
}
