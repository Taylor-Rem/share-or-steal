<?php

namespace Database\Seeders;

use App\Analysis\Analyzer;
use App\Enums\Choice;
use App\Enums\SessionStatus;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A finished two-round game with code DEMO, six players, every decision scored,
 * stats and awards filled in, and the analysis beat sequence populated, so the
 * front-end sessions have real-shaped data to render before the engine exists.
 *
 * The decision log is scripted (Jordan a saint, Priya a wall, Sam a backstabber, Alex a
 * mirror, Morgan an opportunist, Casey random-ish); everything after it is computed by the
 * real analyzer, so the demo shows exactly what a game would.
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

            // The real analysis: stats, archetypes, awards and the beat sequence from the log above.
            app(Analyzer::class)->analyze($session->fresh());
        });
    }
}
