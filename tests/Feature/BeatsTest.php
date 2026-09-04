<?php

use App\Analysis\Analyzer;
use App\Enums\AwardKey;
use App\Models\GameSession;
use App\Simulation\Personality;
use Tests\Support\ScriptedGame;

function smallRoom(bool $anonymous = false, int $seed = 1): GameSession
{
    $session = ScriptedGame::make(rounds: 2, decisions: 5, seed: $seed, anonymous: $anonymous)
        ->add('Priya', Personality::Wall)->add('Sam', Personality::Backstabber)->add('Alex', Personality::Mirror)
        ->add('Jordan', Personality::Saint)->add('Morgan', Personality::Opportunist)->add('Casey', Personality::Wildcard)
        ->play();
    app(Analyzer::class)->analyze($session);

    return $session->fresh();
}

it('builds the normal beat sequence in the contract order', function () {
    $session = smallRoom();
    $beats = collect($session->analysis_beats);
    $types = $beats->pluck('type');
    $awardKeys = $session->awards->filter(fn ($a) => $a->key !== AwardKey::Champion)->count();

    expect($types->take(2)->all())->toBe(['room_share_rate', 'share_rate_by_decision'])
        ->and($types->filter(fn ($t) => $t === 'archetype_reveal'))->toHaveCount(6)
        ->and($types->slice(8, 2)->values()->all())->toBe(['archetype_census', 'stat_leaders'])
        ->and($types->filter(fn ($t) => $t === 'award'))->toHaveCount($awardKeys)
        ->and($types->last())->toBe('podium')
        ->and($beats->count())->toBe(10 + $awardKeys + 1);

    $room = $beats[0]['screen'];
    expect($room)->toHaveKeys(['share_rate', 'total_points', 'max_cooperative_points'])
        ->and($room['max_cooperative_points'])->toBe(6 * 2 * 5 * 3)
        ->and($room['total_points'])->toBe($session->stats->sum('total_points'));
    expect($beats[1]['screen']['series'])->toHaveCount(5)->and($beats[1]['screen']['series'][0])->toHaveKeys(['decision', 'share_rate']);

    // Reveals run from the back of the field to the champion.
    $ranks = $beats->where('type', 'archetype_reveal')->pluck('screen.rank')->values()->all();
    expect($ranks)->toBe(collect($ranks)->sortDesc()->values()->all());
    $reveal = $beats->firstWhere('type', 'archetype_reveal');
    expect($reveal['screen'])->toHaveKeys(['player', 'total_points', 'rank', 'share_rate', 'archetype'])
        ->and($reveal['private'])->toHaveKey($reveal['screen']['player']['id']);

    $census = $beats->firstWhere('type', 'archetype_census')['screen']['counts'];
    expect(collect($census)->sum('count'))->toBe(6)->and($census[0]['archetype'])->toHaveKeys(['key', 'label', 'blurb']);

    $leaders = $beats->firstWhere('type', 'stat_leaders')['screen']['leaders'];
    expect($leaders[0])->toHaveKeys(['stat', 'label', 'player', 'value', 'value_label']);

    $award = $beats->firstWhere('type', 'award');
    expect($award['screen']['award'])->toHaveKeys(['key', 'label', 'description', 'winner', 'value', 'value_label', 'tie_break'])
        ->and($award['private'])->toHaveKey($award['screen']['award']['winner']['id']);

    $podium = $beats->last();
    expect($podium['screen']['places'])->toHaveCount(3)
        ->and($podium['screen']['places'][0])->toMatchArray(['place' => 1])
        ->and($podium['screen']['places'][0]['archetype'])->toHaveKey('label')
        ->and($podium['private'])->toHaveCount(6);
    $champion = $podium['screen']['places'][0]['player']['id'];
    expect($podium['private'][$champion])->toMatchArray(['rank' => 1])
        ->and(collect($podium['private'][$champion]['awards'])->pluck('key'))->toContain('champion');
});

it('keeps names off the screen in the anonymous sequence', function () {
    $session = smallRoom(anonymous: true);
    $beats = collect($session->analysis_beats);
    $types = $beats->pluck('type');

    expect($types->contains('archetype_reveal'))->toBeFalse();
    $cards = $beats->firstWhere('type', 'archetype_cards');
    expect($cards['screen'])->toBe(['count' => 6])->and($cards['private'])->toHaveCount(6);

    foreach ($beats->where('type', 'stat_leaders')->first()['screen']['leaders'] as $leader) {
        expect($leader)->not->toHaveKey('player');
    }
    foreach ($beats->where('type', 'award') as $award) {
        expect($award['screen']['award'])->toHaveKeys(['key', 'label', 'description'])->not->toHaveKey('winner')
            ->and($award['private'])->toHaveCount(1);
    }
    $podium = $beats->last();
    expect($podium['screen'])->toHaveKeys(['distribution', 'top_scores'])->not->toHaveKey('places')
        ->and($podium['screen']['distribution'])->toHaveKeys(['min', 'max', 'median'])
        ->and($podium['screen']['top_scores'])->toHaveCount(3)
        ->and($podium['private'])->toHaveCount(6);
    expect(json_encode($beats->pluck('screen')))->not->toContain('Priya');
});

it('adds a comparison beat when the same phones played an earlier finished session', function () {
    $first = smallRoom();
    $first->update(['status' => 'finished', 'ended_at' => now()]);
    // Same device tokens, second game, anonymous this time.
    $second = ScriptedGame::make(rounds: 2, decisions: 5, seed: 9, anonymous: true)
        ->add('Priya', Personality::Saint)->add('Sam', Personality::Saint)->add('Alex', Personality::Wall)
        ->add('Jordan', Personality::Wall)->add('Morgan', Personality::Mirror)->add('Casey', Personality::Mirror)
        ->play();
    foreach ($second->players as $p) {
        $p->update(['device_token' => $first->players->firstWhere('username', $p->username)->device_token]);
    }
    app(Analyzer::class)->analyze($second);

    $beat = collect($second->fresh()->analysis_beats)->last();
    expect($beat['type'])->toBe('comparison')
        ->and($beat['screen'])->toHaveKeys(['text', 'share_rate', 'forgiveness', 'betrayals'])
        ->and($beat['screen']['share_rate'])->toHaveKeys(['before', 'after'])
        ->and($beat['screen']['text'])->toStartWith('Playing anonymously, this room')
        ->and($beat['private'])->toHaveCount(6);
    $priya = $beat['private'][ScriptedGame::player($second, 'Priya')->id];
    expect($priya['share_rate']['before'])->toEqual(ScriptedGame::statsOf($first, 'Priya')->share_rate)
        ->and($priya['text'])->toStartWith('You ');

    // No earlier game: no comparison.
    expect(collect($first->analysis_beats)->last()['type'])->toBe('podium');
});

it('serves what it stored through the director endpoint', function () {
    $session = smallRoom();
    asDirector()->getJson("/api/director/sessions/{$session->code}/analysis")
        ->assertOk()
        ->assertJsonCount(6, 'stats')
        ->assertJsonPath('stats.0.rank', 1)
        ->assertJsonPath('beats.0.type', 'room_share_rate')
        ->assertJsonStructure(['awards' => [['key', 'label', 'description', 'winner', 'value', 'value_label', 'tie_break']]]);
});
