<?php

use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\ChoiceController;
use App\Http\Controllers\Api\Director\LoginController;
use App\Http\Controllers\Api\Director\PlayerCommandController;
use App\Http\Controllers\Api\Director\SessionCommandController;
use App\Http\Controllers\Api\Director\SessionsController;
use App\Http\Controllers\Api\JoinController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SessionStateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API — see CONTRACT.md § 10
|--------------------------------------------------------------------------
|
|   /api/sessions/{code}          public snapshot
|   /api/sessions/{code}/...      players   (route middleware: player / player:any)
|   /api/director/...             director  (route middleware: director)
|
| Channel authorization is registered separately at POST /api/broadcasting/auth.
|
*/

Route::get('/sessions/{code}', SessionStateController::class)->name('api.session.state');
Route::post('/sessions/{code}/join', JoinController::class)->name('api.session.join');

Route::middleware('player:any')->group(function () {
    Route::get('/sessions/{code}/me', MeController::class)->name('api.session.me');
    Route::post('/sessions/{code}/choice', ChoiceController::class)->name('api.session.choice');
    Route::post('/sessions/{code}/avatar', AvatarController::class)->name('api.session.avatar');
});

Route::post('/director/login', LoginController::class)->name('api.director.login');

Route::middleware('director')->prefix('director')->name('api.director.')->group(function () {
    Route::get('/sessions', [SessionsController::class, 'index'])->name('sessions.index');
    Route::post('/sessions', [SessionsController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{code}', [SessionsController::class, 'show'])->name('sessions.show');
    Route::get('/sessions/{code}/analysis', [SessionsController::class, 'analysis'])->name('sessions.analysis');

    Route::post('/sessions/{code}/start', [SessionCommandController::class, 'start'])->name('sessions.start');
    Route::post('/sessions/{code}/pause', [SessionCommandController::class, 'pause'])->name('sessions.pause');
    Route::post('/sessions/{code}/resume', [SessionCommandController::class, 'resume'])->name('sessions.resume');
    Route::post('/sessions/{code}/next', [SessionCommandController::class, 'next'])->name('sessions.next');
    Route::post('/sessions/{code}/end', [SessionCommandController::class, 'end'])->name('sessions.end');
    Route::post('/sessions/{code}/screen/reload', [SessionCommandController::class, 'screenReload'])->name('sessions.screen.reload');

    Route::post('/sessions/{code}/players/{player}/admit', [PlayerCommandController::class, 'admit'])->name('players.admit');
    Route::post('/sessions/{code}/players/{player}/kick', [PlayerCommandController::class, 'kick'])->name('players.kick');
});
