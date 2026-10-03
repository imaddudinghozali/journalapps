<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('trades', 'trades')
    ->middleware(['auth', 'verified'])
    ->name('trades');

Route::view('reports', 'reports')
    ->middleware(['auth', 'verified'])
    ->name('reports');

Route::view('setups', 'setups')
    ->middleware(['auth', 'verified'])
    ->name('setups');

Route::view('instruments', 'instruments')
    ->middleware(['auth', 'verified'])
    ->name('instruments');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
