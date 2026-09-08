<?php

namespace App\Analysis;

use App\Models\GameSession;
use App\Models\PlayerStat;
use Illuminate\Support\Collection;

/**
 * The closing beat of a replay: how the same people played last time versus now, matched
 * by device token to their most recent earlier finished session (docs/PLAN.md "Anonymous
 * mode"). Null when nobody in the room has an earlier game.
 */
final class Comparison
{
    /**
     * @param  Collection<int, PlayerStat>  $stats  this session's human rows with `player`
     * @return array{screen: array<string, mixed>, private: array<int, array<string, mixed>>}|null
     */
    public static function build(GameSession $session, Collection $stats): ?array
    {
        $private = [];
        $pairs = [];

        foreach ($stats as $now) {
            $token = $now->player->device_token;
            if (! $token) {
                continue;
            }
            $before = PlayerStat::query()
                ->whereHas('player', fn ($q) => $q->where('device_token', $token))
                ->whereHas('session', fn ($q) => $q->where('status', 'finished')->where('id', '<', $session->id))
                ->orderByDesc('game_session_id')
                ->first();
            if (! $before) {
                continue;
            }
            $pairs[] = [$before, $now];
            $private[$now->player_id] = self::shape($before, $now, 'You');
        }

        if ($pairs === []) {
            return null;
        }

        $avg = fn (string $stat, int $which) => round(collect($pairs)->map(fn ($p) => $p[$which]->$stat)->filter(fn ($v) => $v !== null)->avg() ?? 0, 4);
        $room = [
            'share_rate' => ['before' => $avg('share_rate', 0), 'after' => $avg('share_rate', 1)],
            'forgiveness' => ['before' => $avg('forgiveness', 0), 'after' => $avg('forgiveness', 1)],
            'betrayals' => ['before' => $avg('betrayals', 0), 'after' => $avg('betrayals', 1)],
        ];

        return [
            'screen' => ['text' => self::text($room, $session->isAnonymous() ? 'Playing anonymously, this room' : 'Playing again, this room')] + $room,
            'private' => $private,
        ];
    }

    private static function shape(PlayerStat $before, PlayerStat $now, string $subject): array
    {
        $rows = [
            'share_rate' => ['before' => $before->share_rate, 'after' => $now->share_rate],
            'forgiveness' => ['before' => $before->forgiveness, 'after' => $now->forgiveness],
            'betrayals' => ['before' => $before->betrayals, 'after' => $now->betrayals],
        ];

        return ['text' => self::text($rows, $subject)] + $rows;
    }

    /** "Playing anonymously, this room stole 31% more often and forgave 40% less" */
    private static function text(array $rows, string $subject): string
    {
        $steal = self::change(1 - ($rows['share_rate']['before'] ?? 0), 1 - ($rows['share_rate']['after'] ?? 0));
        $forgave = self::change($rows['forgiveness']['before'], $rows['forgiveness']['after']);

        $parts = [];
        $parts[] = $steal === null ? 'stole about as often' : ($steal === 0 ? 'stole just as often' : 'stole '.abs($steal).'% '.($steal > 0 ? 'more' : 'less').' often');
        $parts[] = $forgave === null ? 'forgave about as much' : ($forgave === 0 ? 'forgave just as much' : 'forgave '.abs($forgave).'% '.($forgave > 0 ? 'more' : 'less'));

        return "$subject ".implode(' and ', $parts);
    }

    /** Relative change in whole percent, or null when there is no baseline. */
    private static function change(?float $before, ?float $after): ?int
    {
        if ($before === null || $after === null || $before <= 0) {
            return null;
        }

        return (int) round(($after - $before) / $before * 100);
    }
}
