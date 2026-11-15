<?php

use App\Actions\Urunan\SederhanakanUtang;

test('utang dua arah cukup dibayar selisihnya', function () {
    expect(SederhanakanUtang::dariSaldo([1 => -35000, 2 => 35000]))
        ->toBe([['dari' => 1, 'ke' => 2, 'jumlah' => 35000]]);
});

test('beberapa pengutang membayar ke satu penerima', function () {
    $pembayaran = SederhanakanUtang::dariSaldo([1 => 100000, 2 => -50000, 3 => -50000]);

    expect($pembayaran)->toHaveCount(2);
    expect(array_sum(array_column($pembayaran, 'jumlah')))->toBe(100000);
    expect(array_unique(array_column($pembayaran, 'ke')))->toBe([1]);
});

test('saldo yang sudah nol tidak menghasilkan pembayaran', function () {
    expect(SederhanakanUtang::dariSaldo([1 => 0, 2 => 0]))->toBe([]);
});
