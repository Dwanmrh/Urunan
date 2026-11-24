<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BuktiPembayaranController;
use App\Http\Controllers\GantiBahasaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('bahasa/{bahasa}', GantiBahasaController::class)->name('bahasa.ganti');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('grup', 'pages::grup.index')->name('grup.index');
    Route::livewire('grup/{grup}', 'pages::grup.show')->name('grup.show');
    Route::livewire('gabung/{kode}', 'pages::grup.gabung')->middleware('throttle:30,1')->name('grup.gabung');
    Route::livewire('notifikasi', 'pages::notifikasi.index')->name('notifikasi.index');
    Route::livewire('statistik', 'pages::statistik.index')->middleware('can:lihat-statistik')->name('statistik.index');

    Route::livewire('grup/{grup}/tagihan/buat', 'pages::tagihan.form')->name('tagihan.create');
    Route::livewire('grup/{grup}/tagihan/{tagihan}', 'pages::tagihan.show')->name('tagihan.show');
    Route::livewire('grup/{grup}/tagihan/{tagihan}/edit', 'pages::tagihan.form')->name('tagihan.edit');

    Route::get('grup/{grup}/pembayaran/{pembayaran}/bukti', BuktiPembayaranController::class)->name('pembayaran.bukti');
});

require __DIR__.'/settings.php';
