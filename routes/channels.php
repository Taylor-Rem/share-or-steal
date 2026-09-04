<?php

use App\Auth\Identity;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels — see CONTRACT.md § Channels
|--------------------------------------------------------------------------
|
| All four channels are private. Identity comes from headers via the `game`
| guard (app/Auth). Each callback answers "may this identity subscribe?".
|
*/

$guards = ['guards' => ['game']];

// Everyone in the room: players of the session, the big screen, the director.
Broadcast::channel('session.{code}', function (Identity $identity, string $code) {
    return $identity->isDirector() || $identity->isScreenFor($code) || $identity->isPlayerIn($code);
}, $guards);

// Screen-only signals. Same audience as the session channel.
Broadcast::channel('screen.{code}', function (Identity $identity, string $code) {
    return $identity->isDirector() || $identity->isScreenFor($code) || $identity->isPlayerIn($code);
}, $guards);

// Director only.
Broadcast::channel('director.{code}', function (Identity $identity, string $code) {
    return $identity->isDirector();
}, $guards);

// One player's private channel: only that player's device.
Broadcast::channel('player.{id}', function (Identity $identity, string $id) {
    return $identity->kind === 'player' && $identity->player?->id === (int) $id;
}, $guards);
