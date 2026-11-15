<?php

use App\Actions\Urunan\BagiRupiah;

test('total yang habis dibagi menghasilkan bagian yang sama', function () {
    expect(BagiRupiah::rata(200000, [1, 2, 3, 4]))
        ->toBe([1 => 50000, 2 => 50000, 3 => 50000, 4 => 50000]);
});

test('sisa pembagian diberikan ke anggota urutan pertama sehingga jumlahnya tetap sama dengan total', function () {
    $bagian = BagiRupiah::rata(100000, [7, 8, 9]);

    expect($bagian)->toBe([7 => 33334, 8 => 33333, 9 => 33333]);
    expect(array_sum($bagian))->toBe(100000);
});

test('tanpa anggota tidak ada bagian', function () {
    expect(BagiRupiah::rata(100000, []))->toBe([]);
});
