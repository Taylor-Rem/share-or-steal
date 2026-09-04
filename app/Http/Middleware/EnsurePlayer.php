<?php

namespace App\Http\Middleware;

use App\Auth\Identity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user('game');
        $code = (string) $request->route('code');

        if (! $identity instanceof Identity || ! $identity->isPlayerIn($code)) {
            return response()->json(['message' => 'Not a player in this session.'], 401);
        }

        return $next($request);
    }
}
