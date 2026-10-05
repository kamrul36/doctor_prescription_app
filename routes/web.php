<?php

use Illuminate\Support\Facades\Route;

// Blade UI (session auth). Feature routes are added per milestone; the
// `auth` group gets the session login in P1.1.
Route::view('/', 'home')->name('home');
