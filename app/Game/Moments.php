<?php

namespace App\Game;

use App\Enums\Choice;
use App\Models\Pairing;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * The screen's feed. Text is written here, once, so every client shows the same words.
 * Thresholds are in config('game.moments').
 */
final class Moments
{
    /**
     * @param  Collection<int, Pairing>  $pairings  with players and decisions (ordered by index) loaded
     * @return list<array{type: string, text: string, player_ids: list<int>}>
     */
    public function forDecision(Collection $pairings, int $index, int $decisionsPerRound): array
    {
        $streakAt = (int) config('game.moments.share_streak_at');
        $deficit = (int) config('game.moments.comeback_deficit');
        $moments = [];

        foreach ($pairings as $pairing) {
            $decisions = $pairing->decisions->filter(fn ($d) => $d->isRevealed() && $d->index <= $index)->sortBy('index')->values();
            $current = $decisions->last();
            if ($current === null || $current->index !== $index) {
                continue;
            }
            $a = $pairing->playerA;
            $b = $pairing->playerB;

            switch (Payloads::outcome($current->choice_a, $current->choice_b)) {
                case 'betrayer':
                    $moments[] = self::moment('betrayal', "{$a->username} stole from {$b->username}", [$a, $b]);
                    break;
                case 'betrayed':
                    $moments[] = self::moment('betrayal', "{$b->username} stole from {$a->username}", [$b, $a]);
                    break;
                case 'mutual_steal':
                    $moments[] = self::moment('mutual_steal', "{$a->username} and {$b->username} both stole", [$a, $b]);
                    break;
                case 'mutual_share':
                    $streak = 0;
                    foreach ($decisions->reverse() as $d) {
                        if ($d->choice_a === Choice::Share && $d->choice_b === Choice::Share) {
                            $streak++;
                        } else {
                            break;
                        }
                    }
                    if ($streak === $streakAt || ($streak === $decisionsPerRound && $streak > $streakAt)) {
                        $moments[] = self::moment('mutual_share_streak', "{$a->username} and {$b->username} have shared {$streak} in a row", [$a, $b]);
                    }
                    break;
            }

            // Comeback: trailing by at least the deficit before this decision, level or ahead after it.
            $beforeA = (int) $pairing->points_a - (int) $current->points_a;
            $beforeB = (int) $pairing->points_b - (int) $current->points_b;
            if ($beforeB - $beforeA >= $deficit && $pairing->points_a >= $pairing->points_b) {
                $moments[] = self::moment('comeback', "{$a->username} came back against {$b->username}", [$a, $b]);
            } elseif ($beforeA - $beforeB >= $deficit && $pairing->points_b >= $pairing->points_a) {
                $moments[] = self::moment('comeback', "{$b->username} came back against {$a->username}", [$b, $a]);
            }
        }

        return $moments;
    }

    /** @param  list<Player>  $players */
    private static function moment(string $type, string $text, array $players): array
    {
        return ['type' => $type, 'text' => $text, 'player_ids' => array_map(fn (Player $p) => $p->id, $players)];
    }
}
