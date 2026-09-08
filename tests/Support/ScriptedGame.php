<?php

namespace Tests\Support;

use App\Enums\Choice;
use App\Game\Pairer;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Round;
use App\Simulation\Personality;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A small DSL for scripted decision logs: name the players (each a Personality, or an
 * explicit list of moves per round), and the game is played straight into the database
 * with the real payoff matrix, timeouts and response times. Pairings come from the real
 * Pairer under a fixed seed, so a run is reproducible.
 */
final class ScriptedGame
{
    /** @var array<string, Personality|list<list<?string>>> */
    private array $roster = [];

    private ?Personality $filler = null;

    private array $fixedPairs = [];

    public function __construct(
        private int $rounds = 5,
        private int $decisions = 10,
        private int $seed = 1,
        private bool $anonymous = false,
    ) {}

    public static function make(int $rounds = 5, int $decisions = 10, int $seed = 1, bool $anonymous = false): self
    {
        return new self($rounds, $decisions, $seed, $anonymous);
    }

    /** Add a player: `->add('Priya', Personality::Wall)` or `->add('Sam', [['share', 'steal', ...], ...])` (null = no answer). */
    public function add(string $name, Personality|array $script): self
    {
        $this->roster[$name] = $script;

        return $this;
    }

    /** The whole plan roster, numbered (saint_1, saint_2, ...). */
    public function roster(array $roster = Personality::ROSTER): self
    {
        foreach ($roster as [$kind, $count]) {
            for ($i = 1; $i <= $count; $i++) {
                $this->add("{$kind}_{$i}", Personality::from($kind));
            }
        }

        return $this;
    }

    /** Force a partner for every round: `->pairs([['Sam', 'Priya'], ...])`. */
    public function pairs(array $pairs): self
    {
        $this->fixedPairs = $pairs;

        return $this;
    }

    public function play(array $sessionAttributes = []): GameSession
    {
        mt_srand($this->seed);
        $rng = new Randomizer(new Mt19937($this->seed));

        $session = GameSession::factory()->create([
            'mode' => $this->anonymous ? 'anonymous' : 'normal',
            'status' => 'round_summary',
            'rounds_count' => $this->rounds,
            'decisions_per_round' => $this->decisions,
            'current_round' => $this->rounds,
            'started_at' => now()->subMinutes(20),
        ] + $sessionAttributes);

        $players = [];
        foreach ($this->roster as $name => $script) {
            $players[$name] = Player::factory()->create([
                'game_session_id' => $session->id,
                'username' => $name,
                'device_token' => 'tok-'.$name.'-'.$session->id,
            ]);
        }
        $byId = collect($players)->keyBy('id');
        $scripts = collect($this->roster)->mapWithKeys(fn ($script, $name) => [$players[$name]->id => $script]);

        $bot = null;
        if (count($players) % 2 === 1) {
            $bot = Player::factory()->bot()->create(['game_session_id' => $session->id]);
            $byId[$bot->id] = $bot;
        }

        $previous = [];
        $pairer = new Pairer;
        $t = now()->subMinutes(15);

        for ($r = 1; $r <= $this->rounds; $r++) {
            $round = Round::factory()->create(['game_session_id' => $session->id, 'number' => $r, 'anonymous' => $this->anonymous, 'started_at' => $t, 'ended_at' => $t->copy()->addMinutes(2)]);

            if ($this->fixedPairs) {
                $pairs = array_map(fn ($p) => [$players[$p[0]]->id, $players[$p[1]]->id], $this->fixedPairs);
            } else {
                $ids = array_map(fn ($p) => $p->id, array_values($players));
                if ($bot) {
                    $ids[] = $bot->id;
                }
                // Seats from the Pairer come from random_int (unseedable); fix them so a seed replays exactly.
                $pairs = array_map(fn ($p) => [min($p), max($p)], $pairer->pair($ids, $bot?->id, $previous, count($players) >= (int) config('game.avoid_repeat_partners_from')));
            }

            foreach ($pairs as [$a, $b]) {
                $previous[$a][] = $b;
                $previous[$b][] = $a;
                $pairing = Pairing::factory()->create(['round_id' => $round->id, 'player_a_id' => $a, 'player_b_id' => $b]);
                $history = ['a' => [], 'b' => []];
                $totals = ['a' => 0, 'b' => 0];

                for ($i = 1; $i <= $this->decisions; $i++) {
                    $choice = [];
                    $timedOut = [];
                    $response = [];
                    foreach (['a' => $a, 'b' => $b] as $seat => $id) {
                        $other = $seat === 'a' ? 'b' : 'a';
                        $pick = $this->move($byId[$id], $scripts[$id] ?? null, $history[$seat], $history[$other], $i, $r, $rng);
                        $timedOut[$seat] = $pick === null;
                        $choice[$seat] = Choice::from($pick ?? config('game.timeout_choice'));
                        $response[$seat] = $pick === null ? null : (($scripts[$id] ?? null) instanceof Personality ? $scripts[$id]->responseMs($rng) : $rng->getInt(600, 3000));
                    }
                    $pointsA = $choice['a']->pointsAgainst($choice['b']);
                    $pointsB = $choice['b']->pointsAgainst($choice['a']);
                    $opened = $t->copy()->addSeconds($i * 10);
                    Decision::create([
                        'pairing_id' => $pairing->id,
                        'index' => $i,
                        'choice_a' => $choice['a'],
                        'choice_b' => $choice['b'],
                        'points_a' => $pointsA,
                        'points_b' => $pointsB,
                        'timed_out_a' => $timedOut['a'],
                        'timed_out_b' => $timedOut['b'],
                        'response_ms_a' => $response['a'],
                        'response_ms_b' => $response['b'],
                        'opened_at' => $opened,
                        'deadline_at' => $opened->copy()->addSeconds(5),
                        'revealed_at' => $opened->copy()->addSeconds(5),
                    ]);
                    $history['a'][] = ['me' => $choice['a']->value, 'them' => $choice['b']->value];
                    $history['b'][] = ['me' => $choice['b']->value, 'them' => $choice['a']->value];
                    $totals['a'] += $pointsA;
                    $totals['b'] += $pointsB;
                }

                $pairing->update(['points_a' => $totals['a'], 'points_b' => $totals['b']]);
                $byId[$a]->increment('total_points', $totals['a']);
                $byId[$b]->increment('total_points', $totals['b']);
            }
            $t = $t->copy()->addMinutes(2);
        }

        return $session->fresh();
    }

    private function move(Player $player, Personality|array|null $script, array $mine, array $theirs, int $i, int $r, Randomizer $rng): ?string
    {
        if ($player->is_bot) {
            return $mine === [] ? 'share' : $mine[array_key_last($mine)]['them'];
        }
        if (is_array($script)) {
            return $script[$r - 1][$i - 1] ?? 'share';
        }

        return $script->choose($mine, $i, $r, $this->decisions, $rng);
    }

    public static function player(GameSession $session, string $name): Player
    {
        return $session->players()->where('username', $name)->firstOrFail();
    }

    public static function statsOf(GameSession $session, string $name): PlayerStat
    {
        return $session->stats()->where('player_id', self::player($session, $name)->id)->firstOrFail();
    }

    public static function awardsOf(GameSession $session, string $name): array
    {
        return $session->awards()->where('player_id', self::player($session, $name)->id)->get()->map(fn ($a) => $a->key->value)->all();
    }
}
