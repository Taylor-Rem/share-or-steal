<?php

namespace App\Http\Middleware;

use App\Auth\Identity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `player`: the identity is an active (admitted, not kicked) player of the route's session.
 * `player:any`: any player record of the session, so a late joiner can poll `me` and a
 * kicked phone gets a proper answer rather than a 401.
 */
class EnsurePlayer
{
    public function handle(Request $request, Closure $next, string $scope = 'active'): Response
    {
        $identity = $request->user('game');
        $code = strtoupper((string) $request->route('code'));

        $ok = $identity instanceof Identity && $identity->kind === 'player' && $identity->code === $code
            && ($scope === 'any' || $identity->isPlayerIn($code));

        if (! $ok) {
            return response()->json(['message' => 'Not a player in this session.'], 401);
        }

        return $next($request);
    }
}
