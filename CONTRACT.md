# Share or Steal — the contract

This is the source of truth that every build session implements against. If you are
building the engine, a phone, the big screen, the director panel, the analysis or the
simulator, you build to *this document*, not to another session's code. If you need
something the contract doesn't define, add it here first, in the same style, and note
it under "Changes" at the bottom.

Everything the plan (`docs/PLAN.md`) decided is assumed. This document only pins down
the interfaces: the state machine, the events and their payloads, the endpoints, the
clock, and the shapes of the analysis.

Conventions used throughout:

- All timestamps are ISO-8601 in UTC with milliseconds: `2026-09-04T17:02:11.250Z`.
  Produce them with `GameSession::iso()`. Never send any other timestamp format.
- Rates are fractions `0..1` with four decimals, or `null` when the denominator is zero.
- Room codes are four upper-case letters from `ABCDEFGHJKLMNPQRSTUVWXYZ` (no I or O).
  Servers accept codes case-insensitively and always emit them upper-case.
- `id` fields are integers. Players are referred to by `id`, never by username.
- Every JSON object below is exact: keys are present even when `null`, and there are no
  extra keys unless a later change adds them here.

---

## 1. Vocabulary

| Term | Meaning |
|---|---|
| Session | One game, identified by its code. Has a mode (normal/anonymous), settings, and a status. |
| Player | A phone in a session. Identified across sessions by its `device_token`. The Machine is a player with `is_bot: true`. |
| Round | One partner for `decisions_per_round` decisions. Carries its own `anonymous` flag. |
| Pairing | Two players in a round, seats `a` and `b`. |
| Decision | One Share/Steal choice by both seats of a pairing, indexed `1..decisions_per_round`. |
| Phase | A timed status (`pairing`, `deciding`, `revealing`, `round_summary`) whose end is `phase_ends_at`. |
| Beat | One screen of the analysis, advanced by the director's Next. |
| Director | Whoever runs the room, authenticated by the shared director password. |
| Screen | The projector page. Authenticated by knowing the code. |

---

## 2. Identity and authorization

There are no user accounts and no cookie sessions. Every API call and every channel
authorization carries identity in request headers. The server resolves them with the
`game` auth guard (`app/Auth/IdentityResolver.php`) in this order of precedence:

| Header | Resolves to | Notes |
|---|---|---|
| `X-Director-Key: <password>` | `director` | Must equal `DIRECTOR_PASSWORD` (`config('game.director_password')`). |
| `X-Device-Token: <token>` | `player` | The phone's token. Optionally `X-Session-Code` picks which session's player record when the token has played several games; the route's `{code}` is used when present. |
| `X-Screen-Code: <code>` | `screen` | Any client that knows the code. Used by the projector, and by phones before they have joined. |

A client sends every header it has. A phone sends `X-Device-Token` and `X-Screen-Code`;
the screen sends `X-Screen-Code`; the director sends `X-Director-Key`.

Authorization failures return `401` with `{"message": "..."}` from endpoints, and `403`
from channel authorization.

Rate limit: 300 requests per minute per device token (or director key, or IP when
neither is present). A room shares one public IP, so never limit per IP alone.

---

## 3. Web routes

| Route | App | Notes |
|---|---|---|
| `/` | phone | Enter code and username. Remembers the last code and username in localStorage. |
| `/play/{code}` | phone | The phone for one session. |
| `/screen/{code}` | screen | Projector. No controls. |
| `/director` | director | Password, create session, session history. |
| `/director/{code}` | director | Control panel for one session. |
| `/director/{code}/analysis` | director | The computed stats, awards and beats of one session, as tables. |

All five return the same Blade shell (`resources/views/app.blade.php`) with the right
Vite entry; Vue Router owns everything after that. The three apps share
`resources/js/shared/`, in particular the Pinia store `useGameStore`.

localStorage keys, all prefixed `sos.`: `device_token`, `last_code`, `username`,
`director_key`, `sound` (`"on"`/`"off"`, phones default off).

---

## 4. Session state machine

### 4.1 Statuses

`sessions.status` is one of:

```
lobby → pairing → deciding → revealing → round_summary → (pairing | analysis) → finished
```

| Status | Meaning | How long | Ends by |
|---|---|---|---|
| `lobby` | Players joining. | Until the director starts. | Director `start`. |
| `pairing` | Pairs for the round are announced. | `durations.pairing_reveal` | Server clock. |
| `deciding` | One decision is open; phones can choose. | `durations.choose` | Server clock (`deadline_at`). |
| `revealing` | The decision is scored and shown; countdown to the next. | `durations.reveal` | Server clock. |
| `round_summary` | Round scoreboard. | `durations.round_summary` | Server clock. |
| `analysis` | Director-paced beats. `analysis_beat` is the index of the beat on screen. | Until the director passes the last beat. | Director `next`. |
| `finished` | Over. Data kept. | — | — |

Durations come from `config/game.php` and depend on the session's `fast_mode`
(`normal`: 8 s / 5 s / 5 s / 12 s; `fast`: 3 s / 1 s / 1 s / 3 s).

### 4.2 Transitions

| From | To | Trigger | Side effects |
|---|---|---|---|
| `lobby` | `pairing` | Director `start` (needs ≥ `min_players` non-kicked players) | Lobby locks. If the count is odd, The Machine is added. Round 1 is created with its pairings and codenames. `current_round = 1`, `current_decision = null`. Broadcast `game.started`, then `pairing.revealed` and `you.paired`. |
| `pairing` | `deciding` | Clock | Decision 1 opens: create the `decisions` rows for every pairing with `opened_at = now`, `deadline_at = now + choose`. `current_decision = 1`. Broadcast `decision.opened`. |
| `deciding` | `revealing` | Clock (`deadline_at` reached) | Every seat without a choice gets the timeout choice (`share`) with `timed_out = true`. Score from the payoff matrix; update pairing round totals and player totals. The Machine's next move is computed here. Broadcast `decision.revealed` and `you.revealed`; `you.nudged` to anyone at ≥ 3 consecutive timeouts. |
| `revealing` | `deciding` | Clock, when `current_decision < decisions_per_round` | Next decision opens exactly as above. |
| `revealing` | `round_summary` | Clock, when the last decision was revealed | Round ends. Leaderboard computed. Broadcast `round.summary` and `you.round_summary`. |
| `round_summary` | `pairing` | Clock, when `current_round < rounds_count` | Next round created with fresh pairings (no repeat partners at ≥ `avoid_repeat_partners_from` players; The Machine rotates to a player who hasn't had it). |
| `round_summary` | `analysis` | Clock, after the last round | Stats, archetypes, awards, and the beat sequence are computed in one pass and stored. `analysis_beat = 0`. Broadcast `analysis.started` then the first `analysis.beat` (and its `you.card`s). |
| `analysis` | `analysis` | Director `next` while `analysis_beat < count - 1` | `analysis_beat += 1`. Broadcast `analysis.beat` and any `you.card`s for that beat. |
| `analysis` | `finished` | Director `next` on the last beat, or `end` | `ended_at = now`. Broadcast `session.ended`. |
| any | `finished` | Director `end` | As above, with `reason: "ended_by_director"`. |

Rules the engine must hold to:

- **The server clock is the only thing that advances a timed status.** Clients never
  ask for the next phase. `game:run` wakes every `tick_ms` (250 ms), and for every session
  whose status is timed and whose `phase_ends_at <= now`, applies the transition. A
  transition that is late by a tick is fine; a transition that is early is a bug.
- **`phase_ends_at` is always set while in a timed status**, and equals `deadline_at`
  of the open decision while `deciding`.
- **Transitions are atomic.** Wrap each in a transaction and `lockForUpdate()` the
  session row so a director command and a tick can't both advance it.
- **Every broadcast carries the new state** (see § 5), so a client that misses an
  event learns the truth from the next one.

### 4.3 Pause

`paused` is an overlay, not a status. Director `pause` while in a timed status sets
`paused_at = now`, `paused_from_status = status`, and `paused_remaining_ms =
phase_ends_at - now`, and broadcasts `session.paused`. While paused the clock ignores
the session and choices are rejected with `paused`. Director `resume` sets
`phase_ends_at = now + paused_remaining_ms` (and `deadline_at` on the open decision to
the same value), clears the three pause fields, and broadcasts `session.resumed`. A
pause during `lobby` or `analysis` is accepted and is a no-op beyond the broadcast; the
screen may show a "paused" overlay.

### 4.4 Late joiners and leaving

- After `start`, `POST join` still creates the player but with `is_admitted: false`
  and returns `state.status` so the phone can show "game in progress". Director `admit`
  sets `is_admitted: true`; the player is included from the next `pairing`. Admitting
  fails with `409` if it would exceed `max_players`.
- Director `kick` sets `kicked_at`. A kicked player's remaining decisions in the current
  round are timeouts; from the next round they are excluded from pairing. Their channel
  authorization fails from then on.
- A player who closes the tab is not removed. Reconnection is § 12.

---

## 5. Envelope and the `state` object

Every broadcast payload has the same envelope, then event-specific fields:

```json
{
  "event": "decision.opened",
  "server_time": "2026-09-04T17:02:06.250Z",
  "state": { ...State },
  "...": "event fields"
}
```

`State` is the session in one object. It rides on every event and is what
`GET /api/sessions/{code}` returns. Produced by `GameSession::toStateArray()`.

```ts
type State = {
  code: string;                // "DEMO"
  mode: "normal" | "anonymous";
  status: "lobby" | "pairing" | "deciding" | "revealing" | "round_summary" | "analysis" | "finished";
  fast_mode: boolean;
  paused: boolean;
  rounds_count: number;        // 5
  decisions_per_round: number; // 10
  round: number | null;        // current round number, null in lobby
  decision: number | null;     // current decision index, null outside deciding/revealing
  analysis_beat: number | null;       // 0-based index of the beat on screen
  analysis_beat_count: number | null; // total beats, once computed
  phase_ends_at: string | null;       // ISO; when the current timed phase ends
  player_count: number;        // non-bot, non-kicked players
}
```

Clients treat `state` as authoritative: on every event, replace the local copy.

---

## 6. Clock

Phones have wrong clocks. The server is the metronome, so:

1. Every event and every state response includes `server_time`.
2. On the first `server_time` a client sees, it records
   `offset = Date.parse(server_time) - Date.now()`. Later samples nudge the offset by 20%
   (see `resources/js/shared/clock.js`); never replace it wholesale.
3. Countdowns run toward `deadline_at` / `phase_ends_at` in server time:
   `remaining = Date.parse(deadline_at) - (Date.now() + offset)`.
4. A client never decides a phase is over. It renders zero and waits for the event.

The server rejects a choice whose arrival time is after `deadline_at`. Latency is the
player's problem, which is why the choose window is generous relative to a tap.

---

## 7. Channels

All channels are private and authorized at `POST /api/broadcasting/auth` (stateless;
identity from § 2 headers). Echo subscribes with `.private('session.DEMO')`, which on
the wire is `private-session.DEMO`.

| Channel | Who may subscribe | Carries |
|---|---|---|
| `session.{code}` | Players of the session (admitted, not kicked), the screen, the director | Everything public: state changes, reveals, leaderboards, public analysis beats. Phones and the screen both listen here. |
| `screen.{code}` | Same as `session.{code}` | Screen-only signals (`screen.reload`, and `game.ping`). Exists so the director can address the projector without touching phones. |
| `director.{code}` | Director only | Player list changes and warnings the room shouldn't see. |
| `player.{id}` | That player's device only | Everything addressed to one person: their pairing, their reveal, their cards. In anonymous mode this is the only place individual results ever go. |

Which client subscribes to what:

| Client | Channels |
|---|---|
| Phone | `session.{code}`, `player.{id}` (after join) |
| Screen | `session.{code}`, `screen.{code}` |
| Director | `session.{code}`, `screen.{code}`, `director.{code}` |

Event names are dotted lower-case (`decision.opened`); Echo clients listen with a
leading dot: `.listen('.decision.opened', …)`, or `.listenToAll(…)`. All events
broadcast immediately (`ShouldBroadcastNow`), never via a queue.

---

## 8. Shared shapes

```ts
// `avatar` is the look picked on the join screen (an emoji and a colour key from
// config('game.avatars')), The Machine's fixed one for the bot, or null.
type Avatar = { emoji: string; color: string };
type PublicPlayer = { id: number; username: string; is_bot: boolean; avatar: Avatar | null };

// What a phone sees its partner as. In an anonymous round `display_name` is the codename
// and `is_codename` is true; `id` is still present so the phone can key state, but never shown.
// A codename hides the avatar too.
type Partner = { id: number; display_name: string; is_bot: boolean; is_codename: boolean; avatar: Avatar | null };

type Choice = "share" | "steal";

type SeatResult = {
  player_id: number;
  choice: Choice;
  points: number;        // this decision
  round_total: number;   // this round so far
  total: number;         // whole game so far
  timed_out: boolean;
};

type PairingResult = { pairing_id: number; a: SeatResult; b: SeatResult };

type Aggregate = {
  shares: number; steals: number;             // choices this decision, across the room
  mutual_share: number; mutual_steal: number; betrayals: number; // pairings by outcome
  share_rate: number;                          // shares / (shares + steals)
};

type LeaderboardEntry = {
  rank: number; player: PublicPlayer;
  total_points: number; round_points: number;
  movement: number;      // rank change since the previous scoreboard, + is up; 0 in round 1
};

// A notable moment for the screen's feed. Text is pre-written by the server.
type Moment = {
  type: "betrayal" | "mutual_steal" | "mutual_share_streak" | "comeback";
  text: string;          // "Sam stole from Priya"
  player_ids: number[];
};

type ArchetypeInfo = { key: string; label: string; blurb: string };   // see App\Enums\Archetype
type AwardInfo = { key: string; label: string; description: string }; // see App\Enums\AwardKey
```

---

## 9. Events

### 9.1 On `session.{code}` (public)

**`player.joined`** — a player joined the lobby (or was admitted).
```json
{ "player": { "id": 12, "username": "Jordan", "is_bot": false }, "player_count": 7 }
```
In anonymous mode `player` is `null` (screen shows a pop and the count only).

**`player.updated`** — a player changed their look from the waiting room.
```json
{ "player": { "id": 12, "username": "Jordan", "is_bot": false, "avatar": { "emoji": "🦊", "color": "amber" } } }
```
Not sent in anonymous mode (nothing on the screen shows it).

**`player.left`** — kicked (or, later, left).
```json
{ "player_id": 12, "reason": "kicked", "player_count": 6 }
```

**`game.started`** — the lobby locked.
```json
{ "rounds_count": 5, "decisions_per_round": 10, "player_count": 24, "has_bot": false }
```

**`pairing.revealed`** — the round's pairs. `ends_at` is when `deciding` begins.
```json
{
  "round": 1, "anonymous": false, "ends_at": "2026-09-04T17:02:08.000Z",
  "pairs": [ { "a": { "id": 1, "username": "Jordan", "is_bot": false }, "b": { "id": 2, "username": "Priya", "is_bot": false } } ]
}
```
In an anonymous round `pairs` is `[]`.

**`decision.opened`** — phones may choose until `deadline_at`.
```json
{ "round": 1, "decision": 1, "opened_at": "2026-09-04T17:02:08.000Z", "deadline_at": "2026-09-04T17:02:13.000Z", "choose_ms": 5000 }
```

**`decision.revealed`** — scored. `next_at` is when the next decision opens, or when the
round summary starts after the last decision.
```json
{
  "round": 1, "decision": 1, "next_at": "2026-09-04T17:02:18.000Z", "is_last": false,
  "results": [ { "pairing_id": 5, "a": { "player_id": 1, "choice": "share", "points": 0, "round_total": 0, "total": 0, "timed_out": false },
                                     "b": { "player_id": 2, "choice": "steal", "points": 5, "round_total": 5, "total": 5, "timed_out": false } } ],
  "aggregate": { "shares": 14, "steals": 10, "mutual_share": 5, "mutual_steal": 3, "betrayals": 4, "share_rate": 0.5833 },
  "moments": [ { "type": "betrayal", "text": "Priya stole from Jordan", "player_ids": [2, 1] } ],
  "leaderboard": [ { "rank": 1, "player": { "id": 2, "username": "Priya", "is_bot": false }, "total_points": 5, "round_points": 5, "movement": 0 } ]
}
```
In an anonymous round `results` is `[]`, `moments` is `[]`, `leaderboard` is `[]`;
`aggregate` is always present. `leaderboard` here is the top `game.leaderboard_size`
(10) entries and `moments` at most `game.moments.max_per_decision` (8), rarest kinds
first, so the payload stays under Reverb's 10 KB message limit with 30 players;
`round.summary` carries the whole board.

**`round.summary`** — the round scoreboard. `ends_at` is when the next pairing (or
analysis) begins.
```json
{
  "round": 1, "is_last": false, "ends_at": "2026-09-04T17:04:10.000Z",
  "leaderboard": [ LeaderboardEntry ],
  "biggest_betrayal": { "text": "Priya took 15 points off Jordan", "player_ids": [2, 1], "points": 15 },
  "most_cooperative_pair": { "text": "Alex and Casey shared 9 of 10", "player_ids": [4, 6], "mutual_shares": 9 },
  "aggregate": { "share_rate": 0.61, "mutual_share": 41, "mutual_steal": 12, "betrayals": 17 }
}
```
Anonymous: `leaderboard` is `[]`, `biggest_betrayal` and `most_cooperative_pair` are `null`.

**`analysis.started`**
```json
{ "beat_count": 24 }
```

**`analysis.beat`** — one beat. The payload is the beat's `screen` half (§ 11).
```json
{ "index": 0, "count": 24, "type": "room_share_rate", "payload": { "share_rate": 0.58, "total_points": 3120, "max_cooperative_points": 3600 } }
```

**`session.paused`** / **`session.resumed`**
```json
{ "paused_from": "deciding", "remaining_ms": 2140 }
{ "status": "deciding", "phase_ends_at": "2026-09-04T17:02:15.140Z" }
```

**`session.ended`**
```json
{ "reason": "completed" }
```
`reason` is `"completed"` or `"ended_by_director"`.

**`game.ping`** — test only, from `php artisan game:ping`.
```json
{ "message": "ping" }
```

### 9.2 On `player.{id}` (private to one phone)

**`you.paired`**
```json
{ "round": 1, "anonymous": false, "decisions_per_round": 10, "seat": "a",
  "partner": { "id": 2, "display_name": "Priya", "is_bot": false, "is_codename": false } }
```

**`you.revealed`**
```json
{
  "round": 1, "decision": 1, "next_at": "2026-09-04T17:02:18.000Z", "is_last": false,
  "you":     { "choice": "share", "points": 0, "timed_out": false, "response_ms": 1240 },
  "partner": { "choice": "steal", "points": 5, "timed_out": false },
  "round_total": { "you": 0, "partner": 5 },
  "total_points": 0,
  "outcome": "betrayed"
}
```
`outcome` is `"mutual_share" | "mutual_steal" | "betrayed" | "betrayer"` from your seat's view.

**`you.nudged`** — three or more timeouts in a row.
```json
{ "consecutive_timeouts": 3 }
```

**`you.round_summary`**
```json
{ "round": 1, "is_last": false, "round_points": 22, "total_points": 22, "rank": 7, "player_count": 24,
  "partner": { "id": 2, "display_name": "Priya", "is_bot": false, "is_codename": false },
  "shares": 6, "steals": 4, "stolen_from": 3 }
```
Anonymous: `rank` is still sent (it's private), `partner` is the codename.

**`you.card`** — the private half of an analysis beat (§ 11). Sent with the
`analysis.beat` of the same index, only to players who have a card for that beat.
```json
{ "index": 3, "type": "archetype_reveal", "payload": { ...PlayerStats } }
```

**`you.admitted`** `{}` · **`you.kicked`** `{ "reason": "kicked" }`

**`game.ping`** — as above.

### 9.3 On `screen.{code}`

**`screen.reload`** `{}` — director asks the projector to reload the page.
**`game.ping`** — as above.

### 9.4 On `director.{code}`

**`director.player_updated`** — connection/timeout status for the player list.
```json
{ "player": { "id": 12, "username": "Jordan", "is_bot": false }, "is_admitted": true, "kicked": false,
  "last_seen_at": "2026-09-04T17:02:11.250Z", "consecutive_timeouts": 2, "total_points": 41 }
```
**`director.warning`** `{ "message": "3 players have not answered for 30 s" }`
**`game.ping`** — as above.

---

## 10. Endpoints

All under `/api`, JSON in and out. Validation errors are Laravel's standard `422`
`{ message, errors }`. Unknown code is `404`. Wrong or missing identity is `401`. A game
rule saying no is `409` `{ message, reason }`, where `reason` is the token named next to
the endpoint below (`not_enough_players`, `session_full`, …); `POST choice` has its own
`409` shape.

### 10.1 Public (no identity required)

**`GET /api/sessions/{code}`** — the first thing every client loads.
```json
{ "server_time": "…", "state": State, "players": [ PublicPlayer ], "beat": { "index": 3, "count": 24, "type": "…", "payload": { … } } }
```
`players` is non-kicked players in join order; `[]` in anonymous mode. `beat` is the
current `analysis.beat` payload (the beat's `screen` half) during the analysis and after
the end, `null` otherwise, so a screen that reloads mid-analysis can draw what is up.

### 10.2 Player (`X-Device-Token`)

**`POST /api/sessions/{code}/join`** — no identity required; this is what creates it.
```json
// request
{ "username": "Jordan", "device_token": "5c0a…", "avatar": { "emoji": "🦊", "color": "amber" } }
// 201 response
{ "server_time": "…", "state": State, "player": PublicPlayer, "is_admitted": true }
```
Rules: `username` 1–24 characters after trimming, unique within the session
case-insensitively (`422` `username taken`); `avatar` optional, each half from the
configured lists (`422` otherwise); a second join with the same `device_token`
returns `200` and the existing player (this is also how "remembers you" works) and
updates the avatar if one is sent; after
`start` the player is created with `is_admitted: false` and the response says so;
`409` `session_full` at `max_players`; `409` `session_finished` after the end.
Broadcasts `player.joined`.

**`GET /api/sessions/{code}/me`** — the reconnect snapshot; everything the phone needs to
render the current moment without waiting for the next event.
```json
{
  "server_time": "…", "state": State, "player": PublicPlayer, "is_admitted": true, "kicked": false,
  "total_points": 41,
  "round": { "number": 3, "anonymous": false, "partner": Partner, "seat": "a", "round_total": { "you": 12, "partner": 9 } } ,
  "decision": { "index": 7, "opened_at": "…", "deadline_at": "…", "your_choice": "share", "chosen": true },
  "last_reveal": { ...the last you.revealed payload for this round, or null },
  "card": { ...the latest you.card payload, or null }
}
```
`round` and `decision` are `null` when not applicable.

**`POST /api/sessions/{code}/avatar`** — pick or change your look; any player of the session, admitted or not.
```json
// request
{ "emoji": "🦊", "color": "amber" }
// 200
{ "server_time": "…", "player": PublicPlayer }
```
Both halves must come from `config('game.avatars')` (`422` otherwise). Broadcasts
`player.updated` (normal mode) and `director.player_updated`.

**`POST /api/sessions/{code}/choice`**
```json
// request
{ "choice": "share", "round": 3, "decision": 7 }
// 200
{ "accepted": true, "choice": "share", "response_ms": 1240, "server_time": "…" }
// 409
{ "accepted": false, "reason": "deadline_passed", "server_time": "…" }
```
Rejection reasons, checked in this order:
`not_admitted`, `paused`, `not_deciding` (status isn't `deciding`),
`wrong_decision` (the `round`/`decision` in the body don't match the open one),
`not_in_pairing`, `deadline_passed` (arrival time > `deadline_at`),
`already_chosen` (first choice wins; a second submission is ignored, never overwritten).
`response_ms` is arrival time minus `opened_at`, measured on the server.

### 10.3 Director (`X-Director-Key`; route middleware `director`)

**`POST /api/director/login`** `{ "password": "…" }` → `200 {}` or `401`. Lets the panel
validate the key before storing it.

**`GET /api/director/sessions`** → `{ "sessions": [ SessionSummary ] }`, newest first.
```ts
type SessionSummary = State & { created_at: string; started_at: string | null; ended_at: string | null };
```

**`POST /api/director/sessions`**
```json
// request (all optional; defaults from config/game.php)
{ "mode": "normal", "rounds_count": 5, "decisions_per_round": 10, "fast_mode": false, "max_players": 30 }
// 201
{ "state": State, "urls": { "join": "https://…/", "play": "https://…/play/DEMO", "screen": "https://…/screen/DEMO", "director": "https://…/director/DEMO" } }
```

**`GET /api/director/sessions/{code}`**
```json
{ "server_time": "…", "state": State, "players": [ DirectorPlayer ], "rounds": [ { "number": 1, "anonymous": false, "started_at": "…", "ended_at": "…" } ] }
```
```ts
type DirectorPlayer = PublicPlayer & { is_admitted: boolean; kicked: boolean; last_seen_at: string | null; consecutive_timeouts: number; total_points: number };
```

**`POST /api/director/sessions/{code}/start`** → `200 { state }` · `409 not_enough_players` · `409 already_started`
**`POST /api/director/sessions/{code}/pause`** → `200 { state }`
**`POST /api/director/sessions/{code}/resume`** → `200 { state }` · `409 not_paused`
**`POST /api/director/sessions/{code}/next`** → `200 { state }` · `409 not_in_analysis`
**`POST /api/director/sessions/{code}/end`** → `200 { state }`
**`POST /api/director/sessions/{code}/players/{id}/admit`** → `200 { player: DirectorPlayer }` · `409 session_full`
**`POST /api/director/sessions/{code}/players/{id}/kick`** → `200 { player: DirectorPlayer }`
**`POST /api/director/sessions/{code}/screen/reload`** → `200 {}` (broadcasts `screen.reload`)
**`GET /api/director/sessions/{code}/analysis`** → the full computed analysis for the history view:
```json
{ "state": State, "stats": [ PlayerStats ], "awards": [ Award ], "beats": [ Beat ] }
```

Every director mutation returns the new `state` *and* broadcasts the corresponding
event, so the panel can update from either.

### 10.4 Channel authorization

**`POST /api/broadcasting/auth`** — Laravel's standard body (`channel_name`,
`socket_id`) with § 2 headers. Echo does this for you; set `authEndpoint:
'/api/broadcasting/auth'` and `auth.headers` (see `resources/js/shared/echo.js`).

---

## 11. Analysis payloads

Computed once, in one pass over `decisions`, when the last round ends. Stored in
`player_stats`, `awards`, and `sessions.analysis_beats`. Session 5 owns the numbers;
these are the shapes.

### 11.1 PlayerStats

```ts
type PlayerStats = {
  player: PublicPlayer;
  total_points: number;
  rank: number;                 // 1 = most points; ties share the higher rank
  decisions_count: number;      // decisions played (bot-partnered ones included)
  timeouts: number;
  share_rate: number | null;    // shares / decisions, timeouts excluded
  opening_move: number | null;  // share rate on decision 1 of each round
  retaliation: number | null;   // steals immediately after being stolen from / times stolen from (excluding last decision of a round)
  forgiveness: number | null;   // returned to share within `forgiveness_window` after a steal / times stolen from
  betrayals: number;            // stole right after a mutual share
  exploitation: number;         // stole against a partner who shared last time
  exploitation_rate: number | null; // exploitation / steals
  endgame_shift: number | null; // share rate in decisions >= endgame_from_decision minus share rate before
  predictability: number | null;// fraction of moves correctly guessed by the best rule of (my last, their last) -> my next
  partner_yield: number | null; // average points per decision earned by partners while playing you
  sucker_count: number;         // shared and got stolen from
  times_stolen_from: number;
  avg_response_ms: number | null; // timeouts excluded
  archetype: ArchetypeInfo | null;  // null for The Machine
};
```

### 11.2 Award

```ts
type Award = AwardInfo & {
  winner: PublicPlayer;
  value: number | null;      // the winning stat
  value_label: string;       // "92%", "3.4 pts", "812 ms"
  tie_break: null | "points" | "coin_flip";
  place?: number;            // champion only: 1, 2, 3
};
```
Award keys, in reveal order: `kindest`, `most_forgiving`, `most_ruthless`,
`best_partner`, `most_betrayed`, `cold_blooded`, `endgame_assassin`, `unreadable`,
`fastest_thumb`, then `champion` on the podium. Rules per `docs/PLAN.md`; `most_forgiving`
needs `times_stolen_from >= 3`; `kindest` excludes players whose every share was a
timeout; The Machine is never eligible. Ties break on total points, then a coin flip
that the screen shows as a coin flip.

### 11.3 Beats

The sequence is an array stored on the session. Each beat has a `screen` half (what
goes on `session.{code}` as `analysis.beat`) and an optional `private` map of
`player_id → payload` (what goes on each `player.{id}` as `you.card`).

```ts
type Beat = { type: BeatType; screen: object; private?: Record<number, object> };
```

| `type` | `screen` payload | `private` |
|---|---|---|
| `room_share_rate` | `{ share_rate, total_points, max_cooperative_points }` | — |
| `share_rate_by_decision` | `{ series: [{ decision, share_rate }] }` (one per decision index, averaged over rounds) | — |
| `archetype_reveal` | `PlayerStats` (one beat per player, in ascending rank so the champion's card comes last) | that player: `PlayerStats` |
| `archetype_cards` | `{ count }` (anonymous mode only, replaces all `archetype_reveal` beats) | every player: `PlayerStats` |
| `archetype_census` | `{ counts: [{ archetype: ArchetypeInfo, count }] }` | — |
| `stat_leaders` | `{ leaders: [{ stat, label, player, value, value_label }] }` (normal) · `{ leaders: [{ stat, label, value, value_label }] }` (anonymous, no player) | — |
| `award` | `{ award: Award }` (normal) · `{ award: AwardInfo }` (anonymous) | winner: `{ award: Award }` |
| `podium` | `{ places: [{ place, player, total_points, archetype }] }` (normal) · `{ distribution: { min, max, median }, top_scores: [n, n, n] }` (anonymous) | every player: `{ rank, total_points, archetype: ArchetypeInfo, awards: AwardInfo[] }` |
| `comparison` | `{ text, share_rate: { before, after }, forgiveness: { before, after }, betrayals: { before, after } }` (only in a session whose players also played an earlier session) | each linked player: the same shape for them |

Default order: `room_share_rate`, `share_rate_by_decision`, all `archetype_reveal`
(or one `archetype_cards`), `archetype_census`, `stat_leaders`, one `award` per award
in reveal order, `podium`, then `comparison` if applicable.

---

## 12. Reconnection

A phone that reloads, or comes back from the background, does this and nothing more:

1. Read `device_token` and `last_code` from localStorage.
2. `GET /api/sessions/{code}/me`. Render from that snapshot immediately.
3. Connect Echo, subscribe `session.{code}` and `player.{id}`.
4. Apply events as they arrive; `state` on each event overrides the snapshot.

Missed decisions are already timeouts on the server; nothing needs to be caught up.
The director panel and the screen do the same with `GET /api/sessions/{code}` (and the
director's `GET /api/director/sessions/{code}`).

Echo's Pusher client reconnects on its own; the store exposes `connection` so the UI can
show a "reconnecting" state.

---

## 13. Anonymous mode, in one place

Chosen at creation (`mode: "anonymous"`); every round is created with `anonymous: true`.
The data model allows per-round flags, but nothing in the first build sets them
individually.

| Surface | Normal | Anonymous |
|---|---|---|
| Lobby | Names pop onto the screen | Count only; `player.joined.player` is `null`; `GET state.players` is `[]` |
| `pairing.revealed` | pairs listed | `pairs: []` |
| `you.paired` partner | username | codename, `is_codename: true` |
| `decision.revealed` | results, moments, leaderboard | `aggregate` only |
| `round.summary` | leaderboard, betrayal, cooperative pair | `aggregate` only |
| `you.round_summary` | rank included | rank included (private) |
| Analysis | reveals and awards on screen and phone | census and aggregates on screen; cards, awards and rank to phones only |

Codenames are `Adjective Animal` from `config('game.codenames')`, unique within a round.
A player sees the *same* codename for their partner across all ten decisions and a
different one every round. Codenames are stored on the pairing (`codename_a` is what B
sees A as).

---

## 14. Configuration

Everything tunable is in `config/game.php`: structure, durations for both clock
profiles, tick, payoffs, timeout choice, nudge threshold, repeat-partner threshold,
bot, code alphabet, codenames, director password, analysis windows, archetype
thresholds, award rules. A session copies `rounds_count`, `decisions_per_round`,
`max_players` and `fast_mode` at creation; the rest is read live.

---

## Decisions made in Session 0

Places where the plan was silent or ambiguous, and the call that was made:

1. **`sessions` is called `game_sessions`** (model `GameSession`). Laravel's own
   session driver owns a table named `sessions`; on Laravel Cloud the default
   `SESSION_DRIVER` is `database`, and a collision there would be a bad day.
2. **Identity is header-based, with no accounts and no cookies.** Three headers
   (`X-Director-Key`, `X-Device-Token`, `X-Screen-Code`) resolved by one custom `game`
   guard. Channel auth is stateless at `/api/broadcasting/auth`. This keeps the phones
   free of CSRF and session cookies and makes the simulator trivial to write.
3. **The big screen authenticates by knowing the code.** The plan wants the projector to
   have no controls, so there is no login. All four channels are still private; the
   `screen` identity just has a low bar. Nothing on the session or screen channels is
   ever secret from the room.
4. **The director is one shared password** in `DIRECTOR_PASSWORD`, sent as a header.
   No user table, no roles.
5. **`session.{code}` carries all public payloads for both phones and the screen**,
   including leaderboards and moments. `screen.{code}` exists (the plan asks for it) but
   carries only screen control signals, so there is one source of truth for what the
   room sees rather than two channels drifting apart.
6. **Choices go over HTTP, not WebSocket.** Reverb is broadcast-only. The deadline check,
   first-choice-wins and response timing all happen in one request handler.
7. **Broadcasts are `ShouldBroadcastNow`**, never queued. A queued reveal is a late reveal.
8. **Rate limiting is per device token, not per IP.** Thirty phones behind one office NAT.
9. **Pause is an overlay** (`paused_at` + `paused_from_status` + `paused_remaining_ms`),
   not a status, so resuming lands in exactly the phase with exactly the time remaining.
10. **The analysis beat sequence is stored as JSON on the session**
    (`analysis_beats`), each beat with a `screen` half and a `private` map, so "Next" is
    a pointer increment and the anonymous variants are just different halves.
11. **Timed-out choices carry `response_ms: null`**, so `avg_response_ms` (Fastest Thumb)
    ignores them without a separate flag.
12. **Every event carries the full `state`.** It costs a few hundred bytes and means a
    client that missed an event self-heals on the next one.
13. **Tests run on SQLite in memory**; local dev and production use Postgres. Migrations
    use only portable column types so both work; CI installs `pdo_pgsql` for anyone who
    wants to flip `phpunit.xml` to Postgres later.
14. **`game:run` ships as a stub loop** so the Laravel Cloud background-process
    definition is valid on the first deploy. Session 1 fills in `tick()`.
15. **Anonymous lobby shows a count, not names**, because the plan says the screen
    shows no names at all in that mode and the lobby is on the screen.

## Changes

Append a dated line here whenever the contract changes, with the session that made it.

- 2026-09-04 · Session 0 · Initial contract.
- 2026-09-04 · Session 1 · `GET me` gains `kicked: boolean` so a reloaded phone can tell it
  was removed (its channels 403 either way). `409` error shape pinned as `{ message, reason }`.
  `me` and `choice` accept any player record of the session (route middleware `player:any`),
  so a late joiner can poll and a kicked phone gets `not_admitted` rather than a `401`.
  Moment rules and thresholds: `betrayal` and `mutual_steal` every time they happen;
  `mutual_share_streak` when a pair's streak reaches `game.moments.share_streak_at` (3) and
  again on a perfect round; `comeback` when a seat trailing its partner by at least
  `game.moments.comeback_deficit` (5) draws level or ahead. `you.nudged` is sent on every
  reveal while a player's `consecutive_timeouts >= nudge_after_timeouts`; `director.warning`
  fires alongside it. `aggregate` counts The Machine's seat like any other. A join with a
  known device token returns `200` without re-broadcasting `player.joined`; a late joiner's
  `player.joined` is broadcast at `admit`, not at join. Timestamps are stored with
  microseconds and a UTC offset (`App\Models\Concerns\HasPreciseTimestamps`).
  `decision.revealed` carries a top-10 `leaderboard` and at most 8 `moments`: Reverb (and
  Pusher) refuse messages over 10 KB, and the full 30-player board plus 15 results was
  crossing it. `max_players` at creation is capped at 40 for the same reason.
