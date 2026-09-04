<?php

use App\Http\Controllers\Api\SessionStateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API — see CONTRACT.md § Endpoints
|--------------------------------------------------------------------------
|
| Session 0 ships only the read-only state endpoint. Sessions 1 and 4 add the
| player and director endpoints named in the contract, under these prefixes:
|
|   /api/sessions/{code}/...   players   (route middleware: player)
|   /api/director/...          director  (route middleware: director)
|
| Channel authorization is registered separately at POST /api/broadcasting/auth.
|
*/

Route::get('/sessions/{code}', SessionStateController::class)->name('api.session.state');
