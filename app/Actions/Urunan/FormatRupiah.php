<?php

namespace App\Actions\Urunan;

class FormatRupiah
{
    /**
     * Ambil angka bulatnya saja dari teks rupiah, misalnya "Rp 200.000" menjadi "200000".
     *
     * Bagian setelah koma dianggap sen dan dibuang.
     */
    public static function bersihkan(string $teks): string
    {
        return preg_replace('/\D/', '', explode(',', $teks)[0]) ?? '';
    }

    /**
     * Ubah teks rupiah menjadi angka bulat.
     */
    public static function keAngka(string $teks): int
    {
        return (int) self::bersihkan($teks);
    }

    /**
     * Ubah angka menjadi teks dengan titik pemisah ribuan, misalnya 200000 menjadi "200.000".
     */
    public static function keTeks(int $angka): string
    {
        return number_format($angka, 0, ',', '.');
    }

    /**
     * Ubah angka menjadi teks rupiah lengkap, misalnya 50000 menjadi "Rp 50.000".
     *
     * Tanda minus dibuang karena arah utang sudah dijelaskan di kalimatnya.
     */
    public static function rp(int $angka): string
    {
        return 'Rp '.self::keTeks(abs($angka));
    }
}
