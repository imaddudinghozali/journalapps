<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

// Terbuka untuk tamu: orang berhak tahu apa yang disimpan SEBELUM mendaftar.
Route::view('privasi', 'privasi')->name('privasi');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('trades', 'trades')
    ->middleware(['auth', 'verified'])
    ->name('trades');

Route::view('reports', 'reports')
    ->middleware(['auth', 'verified'])
    ->name('reports');

// Route model binding terscope: trade milik pengguna lain berakhir 404
// sebelum komponennya sempat dimuat.
Volt::route('trades/{trade}/edit', 'trades.edit')
    ->middleware(['auth', 'verified'])
    ->name('trades.edit');

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
