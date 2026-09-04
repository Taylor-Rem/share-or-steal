<?php

namespace App\Providers;

use App\Auth\IdentityResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The `game` guard: identity from headers, no sessions, no cookies. See app/Auth.
        Auth::viaRequest('game', fn (Request $request) => app(IdentityResolver::class)->resolve($request));

        // A room of 30 phones usually shares one public IP, so limit per device, not per IP.
        RateLimiter::for('api', function (Request $request) {
            $key = $request->header('X-Device-Token') ?: $request->header('X-Director-Key') ?: $request->ip();

            return Limit::perMinute(300)->by($key);
        });
    }
}
