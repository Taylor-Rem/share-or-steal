<?php

namespace Database\Seeders;

use App\Enums\Archetype;
use App\Enums\AwardKey;
use App\Enums\Choice;
use App\Enums\SessionStatus;
use App\Models\Award;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Round;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A finished two-round game with code DEMO, six players, every decision scored,
 * stats and awards filled in, and the analysis beat sequence populated, so the
 * front-end sessions have real-shaped data to render before the engine exists.
 *
 * The stats here are illustrative placeholders in the contract's shape. Session 5
 * replaces the numbers with real computation; the shape stays.
 */
class DemoSessionSeeder extends Seeder
{
    private const CODE = 'DEMO';

    public function run(): void
    {
        DB::transaction(function () {
            GameSession::where('code', self::CODE)->delete();

            $session = GameSession::factory()->create([
                'code' => self::CODE,
                'status' => SessionStatus::Analysis,
                'rounds_count' => 2,
                'decisions_per_round' => 10,
                'current_round' => 2,
                'current_decision' => 10,
                'analysis_beat' => 0,
                'started_at' => now()->subMinutes(6),
            ]);

            $names = ['Jordan', 'Priya', 'Sam', 'Alex', 'Morgan', 'Casey'];
            $players = collect($names)->map(fn (string $name) => Player::factory()->create([
                'game_session_id' => $session->id,
                'username' => $name,
                'device_token' => 'demo-'.strtolower($name),
            ]));

            // Scripted moves so the seeded decision log tells a story: Jordan is a saint,
            // Priya a wall, Sam a backstabber, Alex a mirror, Morgan an opportunist, Casey random-ish.
            $scripts = [
                'Jordan' => array_fill(0, 10, 'share'),
                'Priya' => array_fill(0, 10, 'steal'),
                'Sam' => ['share', 'share', 'share', 'share', 'share', 'share', 'share', 'share', 'steal', 'steal'],
                'Alex' => ['share', 'share', 'steal', 'share', 'share', 'steal', 'share', 'share', 'share', 'steal'],
                'Morgan' => ['share', 'steal', 'share', 'steal', 'steal', 'share', 'steal', 'steal', 'share', 'steal'],
                'Casey' => ['steal', 'share', 'share', 'steal', 'share', 'steal', 'steal', 'share', 'share', 'share'],
            ];

            $roundPairs = [
                1 => [['Jordan', 'Priya'], ['Sam', 'Alex'], ['Morgan', 'Casey']],
                2 => [['Jordan', 'Sam'], ['Priya', 'Morgan'], ['Alex', 'Casey']],
            ];

            foreach ($roundPairs as $number => $pairs) {
                $round = Round::factory()->create([
                    'game_session_id' => $session->id,
                    'number' => $number,
                    'started_at' => now()->subMinutes(6 - 2 * $number),
                    'ended_at' => now()->subMinutes(4 - 2 * $number),
                ]);

                foreach ($pairs as [$nameA, $nameB]) {
                    $a = $players->firstWhere('username', $nameA);
                    $b = $players->firstWhere('username', $nameB);

                    $pairing = Pairing::factory()->create([
                        'round_id' => $round->id,
                        'player_a_id' => $a->id,
                        'player_b_id' => $b->id,
                    ]);

                    $pointsA = $pointsB = 0;
                    for ($i = 1; $i <= 10; $i++) {
                        $choiceA = Choice::from($scripts[$nameA][$i - 1]);
                        $choiceB = Choice::from($scripts[$nameB][$i - 1]);
                        // One timeout in the log so the flag is exercised.
                        $timedOutB = $nameB === 'Casey' && $number === 1 && $i === 4;

                        $decision = Decision::factory()
                            ->scored($choiceA, $choiceB, false, $timedOutB)
                            ->create(['pairing_id' => $pairing->id, 'index' => $i]);

                        $pointsA += $decision->points_a;
                        $pointsB += $decision->points_b;
                    }

                    $pairing->update(['points_a' => $pointsA, 'points_b' => $pointsB]);
                    $a->increment('total_points', $pointsA);
                    $b->increment('total_points', $pointsB);
                }
            }

            // Stats and archetypes in the contract shape. Numbers are hand-picked to match the scripts.
            $archetypes = [
                'Jordan' => Archetype::Saint,
                'Priya' => Archetype::Wall,
                'Sam' => Archetype::Backstabber,
                'Alex' => Archetype::Mirror,
                'Morgan' => Archetype::Opportunist,
                'Casey' => Archetype::Wildcard,
            ];

            $ranked = $players->map(fn ($p) => $p->fresh())->sortByDesc('total_points')->values();
            foreach ($ranked as $rank => $player) {
                $shares = collect($scripts[$player->username])->filter(fn ($c) => $c === 'share')->count() * 2;
                PlayerStat::factory()->create([
                    'game_session_id' => $session->id,
                    'player_id' => $player->id,
                    'total_points' => $player->total_points,
                    'rank' => $rank + 1,
                    'decisions_count' => 20,
                    'timeouts' => $player->username === 'Casey' ? 1 : 0,
                    'share_rate' => round($shares / 20, 4),
                    'archetype' => $archetypes[$player->username],
                ]);
            }

            $byName = fn (string $name) => $players->firstWhere('username', $name)->id;
            $awards = [
                [AwardKey::Kindest, 'Jordan', 1.0],
                [AwardKey::MostForgiving, 'Alex', 0.67],
                [AwardKey::MostRuthless, 'Priya', 0.0],
                [AwardKey::BestPartner, 'Jordan', 3.4],
                [AwardKey::MostBetrayed, 'Jordan', 11],
                [AwardKey::ColdBlooded, 'Morgan', 4],
                [AwardKey::EndgameAssassin, 'Sam', -0.75],
                [AwardKey::Unreadable, 'Casey', 0.41],
                [AwardKey::FastestThumb, 'Alex', 812],
            ];
            foreach ($awards as [$key, $name, $value]) {
                Award::factory()->create([
                    'game_session_id' => $session->id,
                    'player_id' => $byName($name),
                    'key' => $key,
                    'value' => $value,
                ]);
            }
            foreach ($ranked->take(3) as $place => $player) {
                Award::factory()->create([
                    'game_session_id' => $session->id,
                    'player_id' => $player->id,
                    'key' => AwardKey::Champion,
                    'place' => $place + 1,
                    'value' => $player->total_points,
                    'tie_break' => null,
                ]);
            }

            $session->update(['analysis_beats' => $this->beats($session->fresh())]);
        });
    }

    /**
     * The beat sequence in the CONTRACT.md § Analysis shape. Session 5 generates this for real.
     *
     * @return array<int, array<string, mixed>>
     */
    private function beats(GameSession $session): array
    {
        $stats = $session->stats()->with('player')->orderBy('rank')->get();
        $awards = $session->awards()->with('player')->get();

        $beats = [
            ['type' => 'room_share_rate', 'screen' => [
                'share_rate' => round($stats->avg('share_rate'), 4),
                'total_points' => $stats->sum('total_points'),
                'max_cooperative_points' => $stats->count() * $session->rounds_count * $session->decisions_per_round * 3,
            ]],
            ['type' => 'share_rate_by_decision', 'screen' => [
                'series' => collect(range(1, 10))->map(fn ($i) => ['decision' => $i, 'share_rate' => round(0.72 - ($i >= 9 ? 0.3 : 0) - $i * 0.01, 4)])->all(),
            ]],
        ];

        foreach ($stats as $stat) {
            $beats[] = [
                'type' => 'archetype_reveal',
                'screen' => $stat->toContractArray(),
                'private' => [$stat->player_id => $stat->toContractArray()],
            ];
        }

        $beats[] = ['type' => 'archetype_census', 'screen' => [
            'counts' => $stats->groupBy(fn ($s) => $s->archetype->value)
                ->map(fn ($group, $key) => ['archetype' => Archetype::from($key)->toArray(), 'count' => $group->count()])
                ->values()->all(),
        ]];

        $beats[] = ['type' => 'stat_leaders', 'screen' => [
            'leaders' => [
                ['stat' => 'share_rate', 'label' => 'Share rate', 'player' => $stats->sortByDesc('share_rate')->first()->player->toPublicArray(), 'value' => $stats->max('share_rate'), 'value_label' => round($stats->max('share_rate') * 100).'%'],
                ['stat' => 'betrayals', 'label' => 'Betrayals', 'player' => $stats->sortByDesc('betrayals')->first()->player->toPublicArray(), 'value' => $stats->max('betrayals'), 'value_label' => (string) $stats->max('betrayals')],
            ],
        ]];

        foreach (AwardKey::cases() as $key) {
            if ($key === AwardKey::Champion) {
                continue;
            }
            $award = $awards->first(fn ($a) => $a->key === $key);
            if (! $award) {
                continue;
            }
            $payload = [
                'award' => $key->toArray() + [
                    'winner' => $award->player->toPublicArray(),
                    'value' => $award->value,
                    'value_label' => (string) $award->value,
                    'tie_break' => $award->tie_break,
                ],
            ];
            $beats[] = ['type' => 'award', 'screen' => $payload, 'private' => [$award->player_id => $payload]];
        }

        $podium = $awards->where('key', AwardKey::Champion)->sortBy('place')->values();
        $beats[] = ['type' => 'podium', 'screen' => [
            'places' => $podium->map(fn ($a) => [
                'place' => $a->place,
                'player' => $a->player->toPublicArray(),
                'total_points' => (int) $a->value,
                'archetype' => $stats->firstWhere('player_id', $a->player_id)?->archetype?->toArray(),
            ])->all(),
        ]];

        return $beats;
    }
}
