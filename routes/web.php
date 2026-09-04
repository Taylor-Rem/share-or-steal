<?php

use Illuminate\Support\Facades\Route;

/*
| Three SPAs, one Blade shell. Vue Router owns everything below each prefix.
| See CONTRACT.md § Routes.
*/

Route::view('/', 'app', ['app' => 'phone'])->name('phone.home');
Route::view('/play/{code}', 'app', ['app' => 'phone'])->name('phone.play');

Route::view('/screen/{code}', 'app', ['app' => 'screen'])->name('screen');

Route::view('/director', 'app', ['app' => 'director'])->name('director.home');
Route::view('/director/{code}', 'app', ['app' => 'director'])->name('director.session');
Route::view('/director/{code}/analysis', 'app', ['app' => 'director'])->name('director.analysis');
