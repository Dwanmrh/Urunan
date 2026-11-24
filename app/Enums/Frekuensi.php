<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum Frekuensi: string
{
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';

    /**
     * Label yang ditampilkan ke pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mingguan => __('Tiap minggu'),
            self::Bulanan => __('Tiap bulan'),
        };
    }

    /**
     * Tanggal tagihan ke-n (dihitung dari 0) sejak tanggal mulai.
     *
     * Selalu dihitung dari tanggal mulai supaya tanggal 31 tidak bergeser
     * menjadi 28 selamanya setelah melewati Februari.
     */
    public function tanggalKe(CarbonInterface $mulai, int $urutan): CarbonInterface
    {
        return match ($this) {
            self::Mingguan => $mulai->copy()->addWeeks($urutan),
            self::Bulanan => $mulai->copy()->addMonthsNoOverflow($urutan),
        };
    }
}
