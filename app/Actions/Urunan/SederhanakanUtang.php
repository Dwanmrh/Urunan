<?php

namespace App\Actions\Urunan;

class SederhanakanUtang
{
    /**
     * Ubah saldo tiap anggota menjadi daftar pembayaran sesedikit mungkin.
     *
     * Saldo positif berarti anggota akan menerima uang, saldo negatif berarti
     * anggota masih harus membayar.
     *
     * @param  array<int, int>  $saldo  Saldo tiap anggota, dengan id anggota sebagai kunci.
     * @return list<array{dari: int, ke: int, jumlah: int}>
     */
    public static function dariSaldo(array $saldo): array
    {
        $pengutang = [];
        $penerima = [];

        foreach ($saldo as $id => $jumlah) {
            if ($jumlah < 0) {
                $pengutang[$id] = -$jumlah;
            } elseif ($jumlah > 0) {
                $penerima[$id] = $jumlah;
            }
        }

        arsort($pengutang);
        arsort($penerima);

        $pembayaran = [];

        while ($pengutang !== [] && $penerima !== []) {
            $dari = array_key_first($pengutang);
            $ke = array_key_first($penerima);
            $jumlah = min($pengutang[$dari], $penerima[$ke]);

            $pembayaran[] = ['dari' => $dari, 'ke' => $ke, 'jumlah' => $jumlah];

            $pengutang[$dari] -= $jumlah;
            $penerima[$ke] -= $jumlah;

            if ($pengutang[$dari] === 0) {
                unset($pengutang[$dari]);
            }

            if ($penerima[$ke] === 0) {
                unset($penerima[$ke]);
            }
        }

        return $pembayaran;
    }
}
