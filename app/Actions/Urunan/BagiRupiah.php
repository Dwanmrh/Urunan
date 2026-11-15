<?php

namespace App\Actions\Urunan;

class BagiRupiah
{
    /**
     * Bagi sejumlah rupiah sama rata ke beberapa anggota tanpa selisih pembulatan.
     *
     * Sisa pembagian diberikan satu rupiah per anggota mulai dari urutan pertama,
     * sehingga jumlah seluruh bagian selalu sama persis dengan total.
     *
     * @param  list<int>  $idAnggota
     * @return array<int, int> Bagian tiap anggota, dengan id anggota sebagai kunci.
     */
    public static function rata(int $total, array $idAnggota): array
    {
        $idAnggota = array_values(array_unique($idAnggota));
        $banyak = count($idAnggota);

        if ($banyak === 0) {
            return [];
        }

        $dasar = intdiv($total, $banyak);
        $sisa = $total % $banyak;

        $bagian = [];

        foreach ($idAnggota as $urutan => $id) {
            $bagian[$id] = $dasar + ($urutan < $sisa ? 1 : 0);
        }

        return $bagian;
    }
}
