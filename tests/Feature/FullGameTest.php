<?php

use App\Enums\SessionStatus;
use App\Events\AnalysisBeat;
use App\Events\AnalysisStarted;
use App\Events\DecisionOpened;
use App\Events\DecisionRevealed;
use App\Events\DirectorPlayerUpdated;
use App\Events\DirectorWarning;
use App\Events\GameStarted;
use App\Events\PairingRevealed;
use App\Events\RoundSummary;
use App\Events\SessionEnded;
use App\Events\YouCard;
use App\Events\YouNudged;
use App\Events\YouPaired;
use App\Events\YouRevealed;
use App\Events\YouRoundSummary;
use App\Models\Decision;
use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Support\Carbon;

beforeEach(fn () => fakeGameEvents());

/** The status sequence a game of this shape must walk, lobby to analysis. */
function expectedStatuses(int $rounds, int $decisions): array
{
    $seen = ['lobby'];
    for ($r = 0; $r < $rounds; $r++) {
        $seen[] = 'pairing';
        for ($d = 0; $d < $decisions; $d++) {
            $seen[] = 'deciding';
            $seen[] = 'revealing';
        }
        $seen[] = 'round_summary';
    }
    $seen[] = 'analysis';

    return $seen;
}

it('plays a full five-round fast-mode game to the podium on the server clock alone', function () {
    // Five humans: an odd room, so The Machine plays. Player1 always steals, Player2 always
    // shares, Player3 double-taps, Player4 and Player5 never answer.
    [$session, $players] = lobby(5, ['code' => 'GAME']);
    $rounds = $session->rounds_count;
    $decisions = $session->decisions_per_round;

    asDirector()->postJson('/api/director/sessions/GAME/start')->assertOk()->assertJsonPath('state.status', 'pairing');
    expect($session->refresh()->players()->where('is_bot', true)->count())->toBe(1);
    expect(payloadsOf(GameStarted::class)->sole())->toMatchArray(['rounds_count' => $rounds, 'decisions_per_round' => $decisions, 'player_count' => 5, 'has_bot' => true]);

    $statuses = ['lobby'];
    $lastKey = null;
    $tick = (int) config('game.tick_ms');
    for ($i = 0; $i < 20_000; $i++) {
        $session->refresh();
        $key = $session->status->value.'/'.$session->current_round.'/'.$session->current_decision;
        if ($key !== $lastKey) {
            $lastKey = $key;
            $statuses[] = $session->status->value;
            if ($session->status === SessionStatus::Deciding) {
                choose($players[0], $session, 'steal')->assertOk();
                choose($players[1], $session, 'share')->assertOk();
                choose($players[2], $session, 'share')->assertOk();
                choose($players[2], $session, 'steal')->assertStatus(409)->assertJsonPath('reason', 'already_chosen');
            }
        }
        if ($session->status === SessionStatus::Analysis) {
            break;
        }
        advanceClock($tick);
        engine()->tick();
    }

    expect($statuses)->toBe(expectedStatuses($rounds, $decisions));

    // Every decision row is scored.
    $all = Decision::with('pairing')->get();
    expect($all)->toHaveCount($rounds * 3 * $decisions)
        ->and($all->every(fn ($d) => $d->isRevealed() && $d->choice_a && $d->choice_b && $d->points_a !== null && $d->points_b !== null))->toBeTrue();

    // Totals agree with the log.
    foreach (Player::all() as $player) {
        $fromLog = $all->sum(fn ($d) => match ($player->id) {
            $d->pairing->player_a_id => $d->points_a,
            $d->pairing->player_b_id => $d->points_b,
            default => 0,
        });
        expect($player->total_points)->toBe($fromLog);
    }

    // Player1 stole every time; the sleepers timed out every time and were nudged.
    $steals = $all->filter(fn ($d) => $d->pairing->seatOf($players[0]) !== null && $d->{'choice_'.$d->pairing->seatOf($players[0])}->value === 'steal');
    expect($steals)->toHaveCount($rounds * $decisions);
    expect($players[3]->refresh()->consecutive_timeouts)->toBe($rounds * $decisions);
    Event::assertDispatched(YouNudged::class, fn ($e) => $e->playerId === $players[3]->id && $e->broadcastWith()['consecutive_timeouts'] === config('game.nudge_after_timeouts'));
    Event::assertDispatched(DirectorWarning::class);
    Event::assertDispatched(DirectorPlayerUpdated::class, fn ($e) => $e->broadcastWith()['player']['id'] === $players[3]->id && $e->broadcastWith()['consecutive_timeouts'] === 1);

    // Nobody drew The Machine twice, and everyone had one partner per round.
    $bot = Player::where('is_bot', true)->sole();
    $botPartners = $session->rounds()->with('pairings')->get()->flatMap->pairings
        ->filter(fn ($p) => $p->player_a_id === $bot->id || $p->player_b_id === $bot->id)
        ->map(fn ($p) => $p->player_a_id === $bot->id ? $p->player_b_id : $p->player_a_id);
    expect($botPartners)->toHaveCount($rounds)->and($botPartners->unique())->toHaveCount($rounds);

    // The wire: every event, in the contract shape.
    Event::assertDispatchedTimes(PairingRevealed::class, $rounds);
    Event::assertDispatchedTimes(DecisionOpened::class, $rounds * $decisions);
    Event::assertDispatchedTimes(DecisionRevealed::class, $rounds * $decisions);
    Event::assertDispatchedTimes(RoundSummary::class, $rounds);
    Event::assertDispatchedTimes(YouPaired::class, $rounds * 5);
    Event::assertDispatchedTimes(YouRevealed::class, $rounds * $decisions * 5);
    Event::assertDispatchedTimes(YouRoundSummary::class, $rounds * 5);
    Event::assertDispatchedTimes(AnalysisStarted::class, 1);
    Event::assertDispatchedTimes(AnalysisBeat::class, 1);

    $pairing = payloadsOf(PairingRevealed::class)->first();
    expect($pairing)->toHaveKeys(['event', 'server_time', 'state', 'round', 'anonymous', 'ends_at', 'pairs'])
        ->and($pairing['pairs'])->toHaveCount(3)
        ->and($pairing['pairs'][0]['a'])->toHaveKeys(['id', 'username', 'is_bot'])
        ->and($pairing['ends_at'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/');

    $opened = payloadsOf(DecisionOpened::class)->first();
    expect($opened)->toHaveKeys(['round', 'decision', 'opened_at', 'deadline_at', 'choose_ms'])
        ->and($opened['choose_ms'])->toBe(config('game.durations.fast.choose'))
        ->and($opened['state']['status'])->toBe('deciding')
        ->and($opened['state']['phase_ends_at'])->toBe($opened['deadline_at']);

    $revealed = payloadsOf(DecisionRevealed::class)->first();
    expect($revealed)->toHaveKeys(['round', 'decision', 'next_at', 'is_last', 'results', 'aggregate', 'moments', 'leaderboard'])
        ->and($revealed['results'])->toHaveCount(3)
        ->and($revealed['results'][0]['a'])->toHaveKeys(['player_id', 'choice', 'points', 'round_total', 'total', 'timed_out'])
        ->and($revealed['aggregate'])->toHaveKeys(['shares', 'steals', 'mutual_share', 'mutual_steal', 'betrayals', 'share_rate'])
        ->and($revealed['aggregate']['shares'] + $revealed['aggregate']['steals'])->toBe(6)
        ->and($revealed['leaderboard'])->toHaveCount(5)
        ->and($revealed['leaderboard'][0])->toHaveKeys(['rank', 'player', 'total_points', 'round_points', 'movement'])
        ->and($revealed['leaderboard'][0]['rank'])->toBe(1)
        ->and(collect($revealed['moments'])->pluck('type')->unique()->diff(['betrayal', 'mutual_steal', 'mutual_share_streak', 'comeback']))->toBeEmpty()
        ->and(collect($revealed['moments'])->contains(fn ($m) => $m['type'] === 'betrayal' && str_contains($m['text'], 'Player1 stole from')))->toBeTrue();
    expect(payloadsOf(DecisionRevealed::class)->last()['is_last'])->toBeTrue();

    $summary = payloadsOf(RoundSummary::class)->first();
    expect($summary)->toHaveKeys(['round', 'is_last', 'ends_at', 'leaderboard', 'biggest_betrayal', 'most_cooperative_pair', 'aggregate'])
        ->and($summary['leaderboard'])->toHaveCount(5)
        ->and($summary['leaderboard'][0]['movement'])->toBe(0)
        ->and($summary['biggest_betrayal'])->toHaveKeys(['text', 'player_ids', 'points'])
        ->and($summary['biggest_betrayal']['player_ids'][0])->toBe($players[0]->id)
        ->and($summary['aggregate'])->toHaveKeys(['share_rate', 'mutual_share', 'mutual_steal', 'betrayals']);
    expect(payloadsOf(RoundSummary::class)->last()['is_last'])->toBeTrue();

    $you = payloadsOf(YouRevealed::class)->first();
    expect($you)->toHaveKeys(['round', 'decision', 'next_at', 'is_last', 'you', 'partner', 'round_total', 'total_points', 'outcome'])
        ->and($you['you'])->toHaveKeys(['choice', 'points', 'timed_out', 'response_ms'])
        ->and($you['partner'])->toHaveKeys(['choice', 'points', 'timed_out']);

    $yourRound = payloadsOf(YouRoundSummary::class)->first();
    expect($yourRound)->toHaveKeys(['round', 'is_last', 'round_points', 'total_points', 'rank', 'player_count', 'partner', 'shares', 'steals', 'stolen_from'])
        ->and($yourRound['player_count'])->toBe(5)
        ->and($yourRound['shares'] + $yourRound['steals'])->toBe($decisions);

    // Analysis: beat 0 on screen, then Next walks the beats and sends cards, then the end.
    $state = $session->refresh()->toStateArray();
    $beatCount = count($session->analysis_beats);
    expect($state['status'])->toBe('analysis')->and($state['analysis_beat'])->toBe(0)->and($state['analysis_beat_count'])->toBe($beatCount)->and($state['phase_ends_at'])->toBeNull();
    expect(payloadsOf(AnalysisStarted::class)->sole()['beat_count'])->toBe($beatCount);
    $beat = payloadsOf(AnalysisBeat::class)->sole();
    expect($beat)->toMatchArray(['index' => 0, 'count' => $beatCount, 'type' => 'room_share_rate'])
        ->and($beat['payload'])->toHaveKeys(['share_rate', 'total_points', 'max_cooperative_points']);
    expect($session->stats()->count())->toBe(6)->and($session->awards()->where('key', 'champion')->count())->toBe(3); // five humans and The Machine

    // Next walks every beat, sending cards as it goes, and the last Next ends the game.
    for ($i = 1; $i < $beatCount; $i++) {
        asDirector()->postJson('/api/director/sessions/GAME/next')->assertOk()->assertJsonPath('state.analysis_beat', $i);
    }
    expect(payloadsOf(AnalysisBeat::class)->last()['type'])->toBe('podium');
    Event::assertDispatched(YouCard::class, fn ($e) => $e->broadcastWith()['type'] === 'podium' && array_key_exists('awards', $e->broadcastWith()['payload']));
    Event::assertDispatched(YouCard::class, fn ($e) => $e->broadcastWith()['type'] === 'archetype_reveal' && $e->broadcastWith()['payload']['archetype'] !== null);
    expect(payloadsOf(YouCard::class)->where('type', 'podium'))->toHaveCount(5);

    asDirector()->postJson('/api/director/sessions/GAME/next')->assertOk()->assertJsonPath('state.status', 'finished');
    expect(payloadsOf(SessionEnded::class)->sole()['reason'])->toBe('completed');
    asDirector()->postJson('/api/director/sessions/GAME/next')->assertStatus(409)->assertJsonPath('reason', 'not_in_analysis');
});

it('keeps every name off the public channel in an anonymous game', function () {
    [$session, $players] = lobby(4, ['code' => 'ANON', 'mode' => 'anonymous', 'rounds_count' => 2, 'decisions_per_round' => 2]);
    engine()->start($session);
    tickUntil($session, SessionStatus::Analysis);

    expect($session->rounds()->get()->every(fn ($r) => $r->anonymous))->toBeTrue();
    expect(payloadsOf(PairingRevealed::class)->every(fn ($p) => $p['anonymous'] === true && $p['pairs'] === []))->toBeTrue();
    expect(payloadsOf(DecisionRevealed::class)->every(fn ($p) => $p['results'] === [] && $p['moments'] === [] && $p['leaderboard'] === [] && isset($p['aggregate']['share_rate'])))->toBeTrue();
    expect(payloadsOf(RoundSummary::class)->every(fn ($p) => $p['leaderboard'] === [] && $p['biggest_betrayal'] === null && $p['most_cooperative_pair'] === null))->toBeTrue();

    $paired = payloadsOf(YouPaired::class);
    expect($paired->every(fn ($p) => $p['partner']['is_codename'] === true))->toBeTrue();
    $round1 = $paired->where('round', 1)->pluck('partner.display_name');
    expect($round1->unique())->toHaveCount(4, 'codenames are unique within a round');
    expect(payloadsOf(YouRoundSummary::class)->every(fn ($p) => is_int($p['rank']) && $p['partner']['is_codename']))->toBeTrue();

    $podium = collect($session->refresh()->analysis_beats)->firstWhere('type', 'podium');
    expect($podium['screen'])->toHaveKeys(['distribution', 'top_scores'])->and($podium['private'])->toHaveCount(4);
    expect(json_encode(collect($session->analysis_beats)->pluck('screen')))->not->toContain('Player1');
});

it('never advances a phase early', function () {
    [$session] = lobby(2);
    engine()->start($session);
    $endsAt = $session->refresh()->phase_ends_at;

    Carbon::setTestNow($endsAt->copy()->subMillisecond());
    engine()->tick();
    expect($session->refresh()->status)->toBe(SessionStatus::Pairing);

    Carbon::setTestNow($endsAt);
    engine()->tick();
    expect($session->refresh()->status)->toBe(SessionStatus::Deciding);
});

it('leaves a finished or lobby session alone', function () {
    [$session] = lobby(2);
    GameSession::factory()->create(['status' => SessionStatus::Finished, 'phase_ends_at' => now()->subMinute()]);
    advanceClock(60_000);
    expect(engine()->tick())->toBe([]);
    expect($session->refresh()->status)->toBe(SessionStatus::Lobby);
});
