<?php

namespace App\Actions\Urunan;

use App\Enums\StatusPembayaran;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Pembayaran;

/**
 * Lencana seru untuk anggota grup, dihitung dari riwayat tagihan dan pembayaran.
 */
class RaporGrup
{
    /**
     * @return list<array{kunci: string, emoji: string, judul: string, nama: string, keterangan: string}>
     */
    public static function untuk(Grup $grup): array
    {
        $anggota = $grup->anggota()->get()->keyBy('id');

        if ($anggota->isEmpty() || ! $grup->tagihan()->exists()) {
            return [];
        }

        $lencana = [];
        $saldo = $grup->saldo();

        if (collect($saldo)->every(fn (int $jumlah): bool => $jumlah === 0)) {
            $lencana[] = [
                'kunci' => 'bebas-utang',
                'emoji' => '🏆',
                'judul' => __('Grup Bebas Utang'),
                'nama' => $grup->nama,
                'keterangan' => __('Semua tagihan lunas. Pertemanan aman!'),
            ];
        }

        $penalang = $grup->tagihan()
            ->selectRaw('dibayar_oleh, SUM(total) as jumlah')
            ->groupBy('dibayar_oleh')
            ->orderByDesc('jumlah')
            ->first();

        if ($penalang !== null && $anggota->has($penalang->dibayar_oleh)) {
            $lencana[] = [
                'kunci' => 'dompet-darurat',
                'emoji' => '💸',
                'judul' => __('Dompet Darurat'),
                'nama' => $anggota[$penalang->dibayar_oleh]->nama,
                'keterangan' => __('Paling banyak nalangin, total :jumlah', ['jumlah' => FormatRupiah::rp((int) $penalang->jumlah)]),
            ];
        }

        $gercep = $grup->pembayaran()
            ->where('status', StatusPembayaran::Diterima->value)
            ->whereNotNull('tagihan_id')
            ->with('tagihan:id,created_at')
            ->get()
            ->filter(fn (Pembayaran $pembayaran): bool => $pembayaran->tagihan !== null)
            ->groupBy('dari_anggota_id')
            ->map(fn ($daftar): float => $daftar->avg(fn (Pembayaran $pembayaran): float => max(0, $pembayaran->tagihan->created_at->diffInMinutes($pembayaran->dibayar_pada))))
            ->sort()
            ->keys()
            ->first();

        if ($gercep !== null && $anggota->has($gercep)) {
            $lencana[] = [
                'kunci' => 'gercep',
                'emoji' => '⚡',
                'judul' => __('Si Paling Gercep'),
                'nama' => $anggota[$gercep]->nama,
                'keterangan' => __('Paling cepat bayar setelah tagihan dicatat'),
            ];
        }

        $ditunggu = collect($saldo)->filter(fn (int $jumlah): bool => $jumlah < 0)->sort()->keys()->first();

        if ($ditunggu !== null && $anggota->has($ditunggu)) {
            /** @var AnggotaGrup $orang */
            $orang = $anggota[$ditunggu];

            $lencana[] = [
                'kunci' => 'ditunggu',
                'emoji' => '👀',
                'judul' => __('Paling Ditunggu'),
                'nama' => $orang->nama,
                'keterangan' => __('Masih punya bagian :jumlah', ['jumlah' => FormatRupiah::rp(abs($saldo[$ditunggu]))]),
            ];
        }

        return $lencana;
    }
}
