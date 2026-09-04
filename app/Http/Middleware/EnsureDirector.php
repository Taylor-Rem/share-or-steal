<?php

namespace App\Http\Middleware;

use App\Auth\Identity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirector
{
    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user('game');

        if (! $identity instanceof Identity || ! $identity->isDirector()) {
            return response()->json(['message' => 'Director key required.'], 401);
        }

        return $next($request);
    }
}
