<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('grup', 'pages::grup.index')->name('grup.index');
    Route::livewire('grup/{grup}', 'pages::grup.show')->name('grup.show');
});

require __DIR__.'/settings.php';
