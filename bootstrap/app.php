<?php

use App\Http\Middleware\EnsureDirector;
use App\Http\Middleware\EnsurePlayer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Channel authorization lives at POST /api/broadcasting/auth, stateless, using the `game` guard.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'director' => EnsureDirector::class,
            'player' => EnsurePlayer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
