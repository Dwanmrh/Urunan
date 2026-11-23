<?php

use App\Http\Controllers\BuktiPembayaranController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('grup', 'pages::grup.index')->name('grup.index');
    Route::livewire('grup/{grup}', 'pages::grup.show')->name('grup.show');
    Route::livewire('gabung/{kode}', 'pages::grup.gabung')->name('grup.gabung');
    Route::livewire('notifikasi', 'pages::notifikasi.index')->name('notifikasi.index');

    Route::livewire('grup/{grup}/tagihan/buat', 'pages::tagihan.form')->name('tagihan.create');
    Route::livewire('grup/{grup}/tagihan/{tagihan}', 'pages::tagihan.show')->name('tagihan.show');
    Route::livewire('grup/{grup}/tagihan/{tagihan}/edit', 'pages::tagihan.form')->name('tagihan.edit');

    Route::get('grup/{grup}/pembayaran/{pembayaran}/bukti', BuktiPembayaranController::class)->name('pembayaran.bukti');
});

require __DIR__.'/settings.php';
