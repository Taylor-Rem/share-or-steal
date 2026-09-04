<?php

/*
|--------------------------------------------------------------------------
| Share or Steal — every tunable in one place
|--------------------------------------------------------------------------
|
| Rules, clocks, payoffs, limits, and the archetype thresholds all live here.
| Sessions 1 (engine), 5 (analysis) and 7 (simulator) read from this file and
| nowhere else. Thresholds should be tuned against dress-rehearsal data.
|
*/

return [

    'name' => 'Share or Steal',

    /*
    | Structure. A session stores its own copy of these at creation time so
    | changing the defaults never alters a game in progress.
    */
    'rounds' => 5,
    'decisions_per_round' => 10,
    'max_players' => 30,
    'min_players' => 2,

    /*
    | Clocks, in milliseconds. `normal` is game day; `fast` is a session-level
    | setting used by the simulator and by anyone who wants to watch a whole
    | game in under two minutes. The engine loop tick is how often game:run
    | wakes up to check every active session's phase_ends_at.
    */
    'durations' => [
        'normal' => [
            'pairing_reveal' => 8_000,
            'choose' => 5_000,
            'reveal' => 5_000,
            'round_summary' => 12_000,
        ],
        'fast' => [
            'pairing_reveal' => 3_000,
            'choose' => 1_000,
            'reveal' => 1_000,
            'round_summary' => 3_000,
        ],
    ],
    'tick_ms' => 250,

    /*
    | Payoffs, keyed by [your choice][their choice] => your points.
    */
    'payoffs' => [
        'share' => ['share' => 3, 'steal' => 0],
        'steal' => ['share' => 5, 'steal' => 1],
    ],

    /*
    | Edge rules.
    */
    'timeout_choice' => 'share',      // no tap before the deadline counts as this, flagged timed_out
    'nudge_after_timeouts' => 3,      // consecutive timeouts before the phone gets "still there?"
    'avoid_repeat_partners_from' => 10, // at or above this many players, pairing never repeats a partner

    /*
    | The house bot that fills the empty seat on odd counts. Tit-for-tat.
    | Excluded from every award and archetype.
    */
    'bot' => [
        'name' => 'The Machine',
        'strategy' => 'tit_for_tat',
    ],

    /*
    | Room codes and anonymous-mode codenames.
    */
    'code_length' => 4,
    'code_alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ', // no I or O
    'codenames' => [
        'adjectives' => [
            'Blue', 'Amber', 'Silver', 'Crimson', 'Jade', 'Violet', 'Golden', 'Ivory',
            'Scarlet', 'Cobalt', 'Copper', 'Emerald', 'Onyx', 'Coral', 'Slate', 'Sable',
        ],
        'animals' => [
            'Heron', 'Fox', 'Otter', 'Falcon', 'Badger', 'Lynx', 'Raven', 'Hare',
            'Moth', 'Wolf', 'Crane', 'Newt', 'Owl', 'Stoat', 'Pike', 'Wren',
        ],
    ],

    /*
    | Director panel. A single shared password, sent as the X-Director-Key
    | header on every director request and on channel authorization.
    */
    'director_password' => env('DIRECTOR_PASSWORD', 'change-me'),

    /*
    | Analysis definitions used by Session 5. Rates are fractions 0–1.
    */
    'analysis' => [
        'forgiveness_window' => 3,     // decisions after being stolen from in which returning to share counts as forgiveness
        'endgame_from_decision' => 9,  // decisions >= this are "endgame" for the endgame-shift stat
        'cooperative_points_per_decision' => 3,
    ],

    /*
    | Archetype ladder thresholds. Evaluated top to bottom in this order by
    | Session 5; the first rule that matches wins. Everyone else is a Pragmatist.
    */
    'thresholds' => [
        'saint' => ['share_rate_min' => 0.90],
        'wall' => ['share_rate_max' => 0.15],
        'backstabber' => ['endgame_shift_max' => -0.40, 'early_share_rate_min' => 0.60],
        'grudge' => ['post_steal_share_rate_max' => 0.10],
        'mirror' => ['match_rate_min' => 0.75],
        'diplomat' => ['forgiveness_min' => 0.60, 'share_rate_min' => 0.60],
        'opportunist' => ['exploitation_share_of_steals_min' => 0.50, 'share_rate_min' => 0.30, 'share_rate_max' => 0.70],
        'wildcard' => ['predictability_bottom_fraction' => 0.15],
    ],

    /*
    | Award rules that are not simply "highest value wins".
    */
    'awards' => [
        'most_forgiving_min_times_stolen_from' => 3,
        'kindest_excludes_all_timeouts' => true, // a player whose every share was a timeout is not Kindest
        'podium_places' => 3,
    ],
];
