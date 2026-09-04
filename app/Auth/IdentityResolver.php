<?php

namespace App\Auth;

use App\Models\GameSession;
use App\Models\Player;
use Illuminate\Http\Request;

/**
 * The `game` auth guard. Header order matters: a director key wins over a
 * device token, which wins over a screen code, so one client can carry all three.
 */
class IdentityResolver
{
    public function resolve(Request $request): ?Identity
    {
        $key = $request->header('X-Director-Key');
        if (is_string($key) && $key !== '' && hash_equals((string) config('game.director_password'), $key)) {
            return Identity::director();
        }

        $token = $request->header('X-Device-Token');
        if (is_string($token) && $token !== '') {
            // A device token may exist in several sessions (one per game it played). Prefer the
            // session named in the route or the X-Session-Code header, else the most recent.
            $code = strtoupper((string) ($request->route('code') ?? $request->route('session')?->code ?? $request->header('X-Session-Code', '')));
            $query = Player::query()->where('device_token', $token)->with('session');
            $player = ($code !== ''
                ? $query->whereHas('session', fn ($q) => $q->where('code', $code))->first()
                : null) ?? Player::query()->where('device_token', $token)->with('session')->latest('id')->first();

            if ($player) {
                return Identity::player($player);
            }
        }

        $screen = $request->header('X-Screen-Code');
        if (is_string($screen) && $screen !== '' && GameSession::where('code', strtoupper($screen))->exists()) {
            return Identity::screen($screen);
        }

        return null;
    }
}
