<?php

namespace App\Game;

use App\Analysis\Analyzer;
use App\Enums\Choice;
use App\Enums\SessionStatus;
use App\Events\AnalysisBeat;
use App\Events\AnalysisStarted;
use App\Events\DecisionOpened;
use App\Events\DecisionRevealed;
use App\Events\DirectorPlayerUpdated;
use App\Events\DirectorWarning;
use App\Events\GameBroadcast;
use App\Events\GameStarted;
use App\Events\PairingRevealed;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerUpdated;
use App\Events\RoundSummary;
use App\Events\ScreenReload;
use App\Events\SessionEnded;
use App\Events\SessionPaused;
use App\Events\SessionResumed;
use App\Events\YouAdmitted;
use App\Events\YouCard;
use App\Events\YouKicked;
use App\Events\YouNudged;
use App\Events\YouPaired;
use App\Events\YouRevealed;
use App\Events\YouRoundSummary;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Pairing;
use App\Models\Player;
use App\Models\Round;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Everything that changes a session: the state machine of CONTRACT.md § 4, the player
 * lifecycle of § 10, and every broadcast of § 9.
 *
 * Every mutation runs through transition(): a transaction with the session row locked,
 * so a director command and a clock tick can never both advance the same session.
 * Broadcasts raised during a transition are queued in an outbox and sent after the
 * commit, so a rolled-back transition never leaks a phantom event.
 */
class Engine
{
    /** @var list<GameBroadcast> */
    private array $outbox = [];

    private int $depth = 0;

    public function __construct(
        private Pairer $pairer,
        private Codenames $codenames,
        private Moments $moments,
        private Analyzer $analyzer,
    ) {}

    // -----------------------------------------------------------------------------
    // The clock
    // -----------------------------------------------------------------------------

    /**
     * One tick of game:run. Advances every unpaused session whose timed phase has ended.
     *
     * @return list<GameSession> the sessions that moved
     */
    public function tick(): array
    {
        $due = GameSession::query()
            ->whereIn('status', array_map(fn (SessionStatus $s) => $s->value, self::timedStatuses()))
            ->whereNull('paused_at')
            ->where('phase_ends_at', '<=', GameSession::dbTime(now()))
            ->orderBy('id')
            ->get();

        $moved = [];
        foreach ($due as $session) {
            if ($this->advance($session)) {
                $moved[] = $session->refresh();
            }
        }

        return $moved;
    }

    /** Apply the timed transition for one session if it is due. Returns whether it moved. */
    public function advance(GameSession $session): bool
    {
        $moved = false;

        $this->transition($session, function (GameSession $s) use (&$moved) {
            if (! $s->status->isTimed() || $s->isPaused() || $s->phase_ends_at === null || $s->phase_ends_at->gt(now())) {
                return;
            }
            $moved = true;

            match ($s->status) {
                SessionStatus::Pairing => $this->openDecision($s, 1),
                SessionStatus::Deciding => $this->scoreDecision($s),
                SessionStatus::Revealing => $s->current_decision < $s->decisions_per_round
                    ? $this->openDecision($s, $s->current_decision + 1)
                    : $this->summarizeRound($s),
                SessionStatus::RoundSummary => $s->current_round < $s->rounds_count
                    ? $this->startRound($s, $s->current_round + 1)
                    : $this->startAnalysis($s),
            };
        });

        return $moved;
    }

    /** @return list<SessionStatus> */
    public static function timedStatuses(): array
    {
        return array_values(array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->isTimed()));
    }

    // -----------------------------------------------------------------------------
    // Players
    // -----------------------------------------------------------------------------

    /**
     * POST join. Returns the (possibly pre-existing) player and whether it was created.
     *
     * @return array{session: GameSession, player: Player, created: bool}
     */
    public function join(GameSession $session, string $username, string $deviceToken, ?array $avatar = null): array
    {
        $player = null;
        $created = false;

        $session = $this->transition($session, function (GameSession $s) use (&$player, &$created, $username, $deviceToken, $avatar) {
            if ($s->status === SessionStatus::Finished) {
                throw GameException::conflict('session_finished', 'This game is over.');
            }

            $existing = $s->players()->where('device_token', $deviceToken)->first();
            if ($existing) {
                $existing->forceFill(['last_seen_at' => now()] + ($avatar ? ['avatar_emoji' => $avatar['emoji'], 'avatar_color' => $avatar['color']] : []))->save();
                $player = $existing;

                return;
            }

            if ($s->players()->whereRaw('lower(username) = ?', [mb_strtolower($username)])->exists()) {
                throw ValidationException::withMessages(['username' => 'username taken']);
            }
            if ($s->players()->where('is_bot', false)->whereNull('kicked_at')->count() >= $s->max_players) {
                throw GameException::conflict('session_full', 'The room is full.');
            }

            $admitted = $s->status === SessionStatus::Lobby;
            $player = $s->players()->create([
                'username' => $username,
                'device_token' => $deviceToken,
                'avatar_emoji' => $avatar['emoji'] ?? null,
                'avatar_color' => $avatar['color'] ?? null,
                'is_bot' => false,
                'is_admitted' => $admitted,
                'last_seen_at' => now(),
            ]);
            $created = true;

            if ($admitted) {
                $this->emit(new PlayerJoined($s, [
                    'player' => $s->isAnonymous() ? null : $player->toPublicArray(),
                    'player_count' => $s->playerCount(),
                ]));
            }
            $this->emit(new DirectorPlayerUpdated($s, $player->toDirectorUpdateArray()));
        });

        return ['session' => $session, 'player' => $player, 'created' => $created];
    }

    /**
     * POST avatar: change a player's look. The room hears about it unless it is anonymous.
     *
     * @param  array{emoji: string, color: string}  $avatar
     */
    public function setAvatar(GameSession $session, Player $player, array $avatar): Player
    {
        $this->transition($session, function (GameSession $s) use ($player, $avatar) {
            $player->refresh();
            $player->forceFill(['avatar_emoji' => $avatar['emoji'], 'avatar_color' => $avatar['color'], 'last_seen_at' => now()])->save();

            if (! $s->isAnonymous() && $player->isActive()) {
                $this->emit(new PlayerUpdated($s, ['player' => $player->toPublicArray()]));
            }
            $this->emit(new DirectorPlayerUpdated($s, $player->toDirectorUpdateArray()));
        });

        return $player;
    }

    /**
     * POST choice. Returns `[choice, response_ms]` or throws a GameException whose
     * reason is one of the contract's rejection ladder, checked in that order.
     *
     * @return array{choice: Choice, response_ms: int}
     */
    public function choose(GameSession $session, Player $player, Choice $choice, int $round, int $decision): array
    {
        $arrived = now();
        $result = null;

        $this->transition($session, function (GameSession $s) use (&$result, $player, $choice, $round, $decision, $arrived) {
            $player->refresh();
            if (! $player->isActive()) {
                throw GameException::conflict('not_admitted');
            }
            if ($s->isPaused()) {
                throw GameException::conflict('paused');
            }
            if ($s->status !== SessionStatus::Deciding) {
                throw GameException::conflict('not_deciding');
            }
            if ($s->current_round !== $round || $s->current_decision !== $decision) {
                throw GameException::conflict('wrong_decision');
            }

            $pairing = $this->currentRound($s)->pairings()
                ->where(fn ($q) => $q->where('player_a_id', $player->id)->orWhere('player_b_id', $player->id))
                ->first();
            if ($pairing === null) {
                throw GameException::conflict('not_in_pairing');
            }

            $row = $pairing->decisions()->where('index', $decision)->lockForUpdate()->firstOrFail();
            if ($arrived->gt($row->deadline_at)) {
                throw GameException::conflict('deadline_passed');
            }

            $seat = $pairing->seatOf($player);
            if ($row->{"choice_$seat"} !== null) {
                throw GameException::conflict('already_chosen');
            }

            $responseMs = max(0, (int) $row->opened_at->diffInMilliseconds($arrived, false));
            $row->forceFill([
                "choice_$seat" => $choice,
                "timed_out_$seat" => false,
                "response_ms_$seat" => $responseMs,
            ])->save();
            $player->forceFill(['last_seen_at' => $arrived])->save();

            $result = ['choice' => $choice, 'response_ms' => $responseMs];
        });

        return $result;
    }

    // -----------------------------------------------------------------------------
    // Director commands
    // -----------------------------------------------------------------------------

    public function start(GameSession $session): GameSession
    {
        return $this->transition($session, function (GameSession $s) {
            if ($s->status !== SessionStatus::Lobby) {
                throw GameException::conflict('already_started');
            }
            $count = $s->participants()->count();
            $min = (int) config('game.min_players');
            if ($count < $min) {
                throw GameException::conflict('not_enough_players', "Need at least $min players.");
            }

            $s->forceFill(['started_at' => now()])->save();
            $this->emit(new GameStarted($s, [
                'rounds_count' => $s->rounds_count,
                'decisions_per_round' => $s->decisions_per_round,
                'player_count' => $count,
                'has_bot' => $count % 2 === 1,
            ]));

            $this->startRound($s, 1);
        });
    }

    public function pause(GameSession $session): GameSession
    {
        return $this->transition($session, function (GameSession $s) {
            if ($s->isPaused() || $s->status === SessionStatus::Finished) {
                return;
            }
            $remaining = $s->status->isTimed() && $s->phase_ends_at
                ? max(0, (int) now()->diffInMilliseconds($s->phase_ends_at, false))
                : null;

            $s->forceFill([
                'paused_at' => now(),
                'paused_from_status' => $s->status->value,
                'paused_remaining_ms' => $remaining,
            ])->save();

            $this->emit(new SessionPaused($s, ['paused_from' => $s->status->value, 'remaining_ms' => $remaining]));
        });
    }

    public function resume(GameSession $session): GameSession
    {
        return $this->transition($session, function (GameSession $s) {
            if (! $s->isPaused()) {
                throw GameException::conflict('not_paused');
            }

            if ($s->status->isTimed() && $s->paused_remaining_ms !== null) {
                $endsAt = now()->addMilliseconds((int) $s->paused_remaining_ms);
                $s->phase_ends_at = $endsAt;
                if ($s->status === SessionStatus::Deciding) {
                    Decision::query()
                        ->whereIn('pairing_id', $this->currentRound($s)->pairings()->select('id'))
                        ->where('index', $s->current_decision)
                        ->update(['deadline_at' => Decision::dbTime($endsAt)]);
                }
            }
            $s->forceFill(['paused_at' => null, 'paused_from_status' => null, 'paused_remaining_ms' => null])->save();

            $this->emit(new SessionResumed($s, ['status' => $s->status->value, 'phase_ends_at' => GameSession::iso($s->phase_ends_at)]));
        });
    }

    /** Director Next: the next analysis beat, or the end after the last one. */
    public function next(GameSession $session): GameSession
    {
        return $this->transition($session, function (GameSession $s) {
            if ($s->status !== SessionStatus::Analysis) {
                throw GameException::conflict('not_in_analysis');
            }
            $count = count($s->analysis_beats ?? []);
            if ($s->analysis_beat === null || $s->analysis_beat >= $count - 1) {
                $this->finish($s, 'completed');

                return;
            }
            $s->forceFill(['analysis_beat' => $s->analysis_beat + 1])->save();
            $this->emitBeat($s);
        });
    }

    public function end(GameSession $session): GameSession
    {
        return $this->transition($session, function (GameSession $s) {
            if ($s->status === SessionStatus::Finished) {
                return;
            }
            $this->finish($s, 'ended_by_director');
        });
    }

    public function admit(GameSession $session, Player $player): GameSession
    {
        return $this->transition($session, function (GameSession $s) use ($player) {
            $player->refresh();
            if ($player->is_admitted) {
                return;
            }
            if ($s->participants()->count() >= $s->max_players) {
                throw GameException::conflict('session_full', 'The room is full.');
            }
            $player->forceFill(['is_admitted' => true])->save();

            $this->emit(new PlayerJoined($s, [
                'player' => $s->isAnonymous() ? null : $player->toPublicArray(),
                'player_count' => $s->playerCount(),
            ]));
            $this->emit(new YouAdmitted($s, $player->id, []));
            $this->emit(new DirectorPlayerUpdated($s, $player->toDirectorUpdateArray()));
        });
    }

    public function kick(GameSession $session, Player $player): GameSession
    {
        return $this->transition($session, function (GameSession $s) use ($player) {
            $player->refresh();
            if ($player->kicked_at !== null) {
                return;
            }
            $player->forceFill(['kicked_at' => now()])->save();

            $this->emit(new PlayerLeft($s, ['player_id' => $player->id, 'reason' => 'kicked', 'player_count' => $s->playerCount()]));
            $this->emit(new YouKicked($s, $player->id, ['reason' => 'kicked']));
            $this->emit(new DirectorPlayerUpdated($s, $player->toDirectorUpdateArray()));
        });
    }

    public function screenReload(GameSession $session): void
    {
        $this->emit(new ScreenReload($session, []));
        $this->flush();
    }

    // -----------------------------------------------------------------------------
    // Timed transitions (all called with the session row locked)
    // -----------------------------------------------------------------------------

    /** Create round `$number` with fresh pairings and enter `pairing`. */
    private function startRound(GameSession $s, int $number): void
    {
        $humans = $s->participants()->orderBy('id')->get();
        $ids = $humans->pluck('id')->all();

        $bot = null;
        if (count($ids) % 2 === 1) {
            $bot = $s->players()->where('is_bot', true)->first() ?? $s->players()->create([
                'username' => config('game.bot.name'),
                'device_token' => null,
                'is_bot' => true,
                'is_admitted' => true,
            ]);
            $ids[] = $bot->id;
        }

        $previous = [];
        foreach (Pairing::query()->whereIn('round_id', $s->rounds()->select('id'))->get(['player_a_id', 'player_b_id']) as $p) {
            $previous[$p->player_a_id][] = $p->player_b_id;
            $previous[$p->player_b_id][] = $p->player_a_id;
        }

        $pairs = $this->pairer->pair(
            $ids,
            $bot?->id,
            $previous,
            $humans->count() >= (int) config('game.avoid_repeat_partners_from'),
        );

        $anonymous = $s->isAnonymous();
        $round = $s->rounds()->create(['number' => $number, 'anonymous' => $anonymous, 'started_at' => now()]);
        $names = $anonymous ? $this->codenames->draw(count($pairs) * 2) : [];

        $players = $humans->keyBy('id');
        if ($bot) {
            $players[$bot->id] = $bot;
        }

        $pairings = [];
        foreach ($pairs as $i => [$a, $b]) {
            $pairing = $round->pairings()->create([
                'player_a_id' => $a,
                'player_b_id' => $b,
                'codename_a' => $anonymous && ! $players[$a]->is_bot ? $names[2 * $i] : null,
                'codename_b' => $anonymous && ! $players[$b]->is_bot ? $names[2 * $i + 1] : null,
            ]);
            $pairing->setRelation('playerA', $players[$a]);
            $pairing->setRelation('playerB', $players[$b]);
            $pairings[] = $pairing;
        }

        $s->forceFill([
            'status' => SessionStatus::Pairing,
            'current_round' => $number,
            'current_decision' => null,
            'phase_ends_at' => now()->addMilliseconds($s->durations()['pairing_reveal']),
        ])->save();

        $this->emit(new PairingRevealed($s, [
            'round' => $number,
            'anonymous' => $anonymous,
            'ends_at' => GameSession::iso($s->phase_ends_at),
            'pairs' => $anonymous ? [] : array_map(fn (Pairing $p) => [
                'a' => $p->playerA->toPublicArray(),
                'b' => $p->playerB->toPublicArray(),
            ], $pairings),
        ]));

        foreach ($pairings as $pairing) {
            foreach (['a', 'b'] as $seat) {
                $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                if ($player->is_bot) {
                    continue;
                }
                $this->emit(new YouPaired($s, $player->id, [
                    'round' => $number,
                    'anonymous' => $anonymous,
                    'decisions_per_round' => $s->decisions_per_round,
                    'seat' => $seat,
                    'partner' => Payloads::partner($pairing, $seat, $anonymous),
                ]));
            }
        }
    }

    /** Open decision `$index` for every pairing of the current round and enter `deciding`. */
    private function openDecision(GameSession $s, int $index): void
    {
        $round = $this->currentRound($s);
        $opened = now();
        $deadline = $opened->copy()->addMilliseconds($s->durations()['choose']);

        foreach ($round->pairings()->get() as $pairing) {
            $pairing->decisions()->create(['index' => $index, 'opened_at' => $opened, 'deadline_at' => $deadline]);
        }

        $s->forceFill(['status' => SessionStatus::Deciding, 'current_decision' => $index, 'phase_ends_at' => $deadline])->save();

        $this->emit(new DecisionOpened($s, [
            'round' => $s->current_round,
            'decision' => $index,
            'opened_at' => GameSession::iso($opened),
            'deadline_at' => GameSession::iso($deadline),
            'choose_ms' => $s->durations()['choose'],
        ]));
    }

    /** Deadline reached: fill timeouts, play The Machine, score, and enter `revealing`. */
    private function scoreDecision(GameSession $s): void
    {
        $round = $this->currentRound($s);
        $index = (int) $s->current_decision;
        $pairings = $this->pairingsWithHistory($round);
        $revealedAt = now();
        $timeoutChoice = Choice::from(config('game.timeout_choice'));
        $nudgeAt = (int) config('game.nudge_after_timeouts');

        $timeoutChanged = [];
        foreach ($pairings as $pairing) {
            $decision = $pairing->decisions->firstWhere('index', $index);

            foreach (['a', 'b'] as $seat) {
                if ($decision->{"choice_$seat"} !== null) {
                    continue;
                }
                $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                if ($player->is_bot) {
                    $decision->{"choice_$seat"} = $this->machineChoice($pairing, $seat, $index);
                    $decision->{"timed_out_$seat"} = false;
                } else {
                    $decision->{"choice_$seat"} = $timeoutChoice;
                    $decision->{"timed_out_$seat"} = true;
                }
                $decision->{"response_ms_$seat"} = null;
            }

            $decision->points_a = $decision->choice_a->pointsAgainst($decision->choice_b);
            $decision->points_b = $decision->choice_b->pointsAgainst($decision->choice_a);
            $decision->revealed_at = $revealedAt;
            $decision->save();

            $pairing->points_a += $decision->points_a;
            $pairing->points_b += $decision->points_b;
            $pairing->save();

            foreach (['a', 'b'] as $seat) {
                $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                $before = (int) $player->consecutive_timeouts;
                $player->total_points += $decision->{"points_$seat"};
                $player->consecutive_timeouts = $decision->{"timed_out_$seat"} ? $before + 1 : 0;
                $player->save();
                if (! $player->is_bot && $player->consecutive_timeouts !== $before) {
                    $timeoutChanged[] = $player;
                }
            }
        }

        $isLast = $index >= $s->decisions_per_round;
        $nextAt = $revealedAt->copy()->addMilliseconds($s->durations()['reveal']);
        $s->forceFill(['status' => SessionStatus::Revealing, 'phase_ends_at' => $nextAt])->save();

        $decisions = $pairings->map(fn (Pairing $p) => $p->decisions->firstWhere('index', $index));
        $anonymous = (bool) $round->anonymous;
        $participants = $s->participants()->orderBy('id')->get();

        $this->emit(new DecisionRevealed($s, [
            'round' => $round->number,
            'decision' => $index,
            'next_at' => GameSession::iso($nextAt),
            'is_last' => $isLast,
            'results' => $anonymous ? [] : $pairings->map(fn (Pairing $p) => [
                'pairing_id' => $p->id,
                'a' => Payloads::seatResult($p->decisions->firstWhere('index', $index), $p, 'a'),
                'b' => Payloads::seatResult($p->decisions->firstWhere('index', $index), $p, 'b'),
            ])->values()->all(),
            'aggregate' => Payloads::aggregate($decisions),
            'moments' => $anonymous ? [] : $this->moments->forDecision($pairings, $index, $s->decisions_per_round),
            // The ticker board: the top of the table only, so the payload stays under Reverb's
            // 10 KB message limit with 30 players. The full board rides on round.summary.
            'leaderboard' => $anonymous ? [] : array_slice(Payloads::leaderboard($participants, $pairings, $round), 0, (int) config('game.leaderboard_size')),
        ]));

        foreach ($pairings as $pairing) {
            $decision = $pairing->decisions->firstWhere('index', $index);
            foreach (['a', 'b'] as $seat) {
                $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                if ($player->is_bot || $player->kicked_at !== null) {
                    continue;
                }
                $this->emit(new YouRevealed($s, $player->id, Payloads::youRevealed($decision, $pairing, $seat, $round->number, $nextAt, $isLast)));
                if ($player->consecutive_timeouts >= $nudgeAt) {
                    $this->emit(new YouNudged($s, $player->id, ['consecutive_timeouts' => (int) $player->consecutive_timeouts]));
                }
            }
        }

        foreach ($timeoutChanged as $player) {
            $this->emit(new DirectorPlayerUpdated($s, $player->toDirectorUpdateArray()));
        }
        $silent = collect($timeoutChanged)->filter(fn (Player $p) => $p->consecutive_timeouts >= $nudgeAt && $p->kicked_at === null)->count();
        if ($silent > 0) {
            $this->emit(new DirectorWarning($s, [
                'message' => sprintf('%d player%s ha%s not answered for %d decisions', $silent, $silent === 1 ? '' : 's', $silent === 1 ? 's' : 've', $nudgeAt),
            ]));
        }
    }

    /** Tit-for-tat: share first, then copy the partner's previous move. */
    private function machineChoice(Pairing $pairing, string $seat, int $index): Choice
    {
        if ($index <= 1) {
            return Choice::Share;
        }
        $previous = $pairing->decisions->firstWhere('index', $index - 1);
        $other = Payloads::otherSeat($seat);

        return $previous?->{"choice_$other"} ?? Choice::Share;
    }

    /** The last decision was revealed: close the round and enter `round_summary`. */
    private function summarizeRound(GameSession $s): void
    {
        $round = $this->currentRound($s);
        $pairings = $this->pairingsWithHistory($round);
        $round->forceFill(['ended_at' => now()])->save();

        $isLast = $round->number >= $s->rounds_count;
        $endsAt = now()->addMilliseconds($s->durations()['round_summary']);
        $s->forceFill(['status' => SessionStatus::RoundSummary, 'current_decision' => null, 'phase_ends_at' => $endsAt])->save();

        $anonymous = (bool) $round->anonymous;
        $participants = $s->participants()->orderBy('id')->get();
        $leaderboard = Payloads::leaderboard($participants, $pairings, $round);
        $decisions = $pairings->flatMap(fn (Pairing $p) => $p->decisions->filter->isRevealed());
        $aggregate = Payloads::aggregate($decisions);

        // Biggest betrayal: the seat that took the most points by stealing from a sharing partner.
        $betrayal = null;
        $cooperative = null;
        foreach ($pairings as $pairing) {
            $mutual = 0;
            $taken = ['a' => 0, 'b' => 0];
            foreach ($pairing->decisions->filter->isRevealed() as $d) {
                if ($d->choice_a === Choice::Share && $d->choice_b === Choice::Share) {
                    $mutual++;
                }
                foreach (['a', 'b'] as $seat) {
                    $other = Payloads::otherSeat($seat);
                    if ($d->{"choice_$seat"} === Choice::Steal && $d->{"choice_$other"} === Choice::Share) {
                        $taken[$seat] += (int) $d->{"points_$seat"};
                    }
                }
            }
            foreach (['a', 'b'] as $seat) {
                if ($taken[$seat] > 0 && $taken[$seat] > ($betrayal['points'] ?? 0)) {
                    $thief = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                    $victim = $pairing->{$seat === 'a' ? 'playerB' : 'playerA'};
                    $betrayal = [
                        'text' => "{$thief->username} took {$taken[$seat]} points off {$victim->username}",
                        'player_ids' => [$thief->id, $victim->id],
                        'points' => $taken[$seat],
                    ];
                }
            }
            if ($mutual > 0 && $mutual > ($cooperative['mutual_shares'] ?? 0)) {
                $cooperative = [
                    'text' => "{$pairing->playerA->username} and {$pairing->playerB->username} shared {$mutual} of {$s->decisions_per_round}",
                    'player_ids' => [$pairing->playerA->id, $pairing->playerB->id],
                    'mutual_shares' => $mutual,
                ];
            }
        }

        $this->emit(new RoundSummary($s, [
            'round' => $round->number,
            'is_last' => $isLast,
            'ends_at' => GameSession::iso($endsAt),
            'leaderboard' => $anonymous ? [] : $leaderboard,
            'biggest_betrayal' => $anonymous ? null : $betrayal,
            'most_cooperative_pair' => $anonymous ? null : $cooperative,
            'aggregate' => [
                'share_rate' => $aggregate['share_rate'],
                'mutual_share' => $aggregate['mutual_share'],
                'mutual_steal' => $aggregate['mutual_steal'],
                'betrayals' => $aggregate['betrayals'],
            ],
        ]));

        $ranks = collect($leaderboard)->mapWithKeys(fn ($entry) => [$entry['player']['id'] => $entry['rank']]);
        foreach ($pairings as $pairing) {
            foreach (['a', 'b'] as $seat) {
                $player = $pairing->{$seat === 'a' ? 'playerA' : 'playerB'};
                if ($player->is_bot || $player->kicked_at !== null) {
                    continue;
                }
                $other = Payloads::otherSeat($seat);
                $mine = $pairing->decisions->filter->isRevealed();
                $this->emit(new YouRoundSummary($s, $player->id, [
                    'round' => $round->number,
                    'is_last' => $isLast,
                    'round_points' => (int) $pairing->{"points_$seat"},
                    'total_points' => (int) $player->total_points,
                    'rank' => $ranks[$player->id] ?? null,
                    'player_count' => count($leaderboard),
                    'partner' => Payloads::partner($pairing, $seat, $anonymous),
                    'shares' => $mine->filter(fn ($d) => $d->{"choice_$seat"} === Choice::Share)->count(),
                    'steals' => $mine->filter(fn ($d) => $d->{"choice_$seat"} === Choice::Steal)->count(),
                    'stolen_from' => $mine->filter(fn ($d) => $d->{"choice_$other"} === Choice::Steal)->count(),
                ]));
            }
        }
    }

    /** After the last round: run the analyzer, enter `analysis`, show beat 0. */
    private function startAnalysis(GameSession $s): void
    {
        $this->analyzer->analyze($s);
        $s->refresh();

        $s->forceFill([
            'status' => SessionStatus::Analysis,
            'current_decision' => null,
            'phase_ends_at' => null,
            'analysis_beat' => 0,
        ])->save();

        $this->emit(new AnalysisStarted($s, ['beat_count' => count($s->analysis_beats ?? [])]));
        $this->emitBeat($s);
    }

    /** `analysis.beat` for the current beat, plus a `you.card` for every player it names. */
    private function emitBeat(GameSession $s): void
    {
        $beats = $s->analysis_beats ?? [];
        $index = (int) $s->analysis_beat;
        $beat = $beats[$index] ?? null;
        if ($beat === null) {
            return;
        }

        $this->emit(new AnalysisBeat($s, [
            'index' => $index,
            'count' => count($beats),
            'type' => $beat['type'],
            'payload' => $beat['screen'],
        ]));

        foreach ($beat['private'] ?? [] as $playerId => $payload) {
            $this->emit(new YouCard($s, (int) $playerId, ['index' => $index, 'type' => $beat['type'], 'payload' => $payload]));
        }
    }

    private function finish(GameSession $s, string $reason): void
    {
        $s->forceFill([
            'status' => SessionStatus::Finished,
            'phase_ends_at' => null,
            'paused_at' => null,
            'paused_from_status' => null,
            'paused_remaining_ms' => null,
            'ended_at' => now(),
        ])->save();

        $this->emit(new SessionEnded($s, ['reason' => $reason]));
    }

    // -----------------------------------------------------------------------------
    // Plumbing
    // -----------------------------------------------------------------------------

    /**
     * Run `$fn` with the session row locked inside a transaction, then broadcast what it
     * emitted. Returns the locked, up-to-date session instance.
     */
    public function transition(GameSession $session, Closure $fn): GameSession
    {
        $this->depth++;
        try {
            $locked = DB::transaction(function () use ($session, $fn) {
                $locked = GameSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
                $fn($locked);

                return $locked;
            });
        } catch (Throwable $e) {
            $this->outbox = [];
            throw $e;
        } finally {
            $this->depth--;
        }

        if ($this->depth === 0) {
            $this->flush();
        }

        return $locked;
    }

    private function emit(GameBroadcast $event): void
    {
        $this->outbox[] = $event;
    }

    /**
     * Send what the transition emitted, in order. A broadcast that fails (Reverb down, a
     * payload over the 10 KB message limit) is reported and skipped; the rest still go out,
     * and every one of them carries the new state.
     */
    private function flush(): void
    {
        $events = $this->outbox;
        $this->outbox = [];
        foreach ($events as $event) {
            try {
                event($event);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function currentRound(GameSession $s): Round
    {
        return $s->rounds()->where('number', $s->current_round)->firstOrFail();
    }

    /** @return Collection<int, Pairing> pairings with both players and every decision of the round */
    private function pairingsWithHistory(Round $round): Collection
    {
        return $round->pairings()->with(['playerA', 'playerB', 'decisions'])->orderBy('id')->get();
    }
}
