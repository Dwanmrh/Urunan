<?php

namespace App\Actions\Urunan;

use App\Models\Tagihan;

/**
 * Susun pesan tagihan siap kirim ke grup WhatsApp, dengan pilihan gaya bahasa.
 */
class PesanTagih
{
    public const GAYA = ['sopan', 'santai', 'kocak'];

    /**
     * Label tiap gaya untuk tombol pilihan.
     *
     * @return array<string, string>
     */
    public static function pilihan(): array
    {
        return [
            'sopan' => __('Sopan 🙏'),
            'santai' => __('Santai 😎'),
            'kocak' => __('Kocak 🤪'),
        ];
    }

    /**
     * Pesan lengkap: pembuka, daftar yang belum lunas, rekening tujuan, dan link tagihan.
     */
    public static function buat(Tagihan $tagihan, string $gaya): string
    {
        $gaya = in_array($gaya, self::GAYA, true) ? $gaya : 'sopan';
        $pembayar = $tagihan->pembayar;
        $nama = $pembayar->nama;

        $belumLunas = $tagihan->statusPelunasan()
            ->filter(fn (array $baris): bool => ! $baris['pembayar'] && $baris['sisa'] > 0)
            ->map(fn (array $baris): string => '• '.$baris['anggota']->nama.': '.FormatRupiah::rp($baris['sisa'])
                .($baris['menunggu'] !== null ? ' '.__('(bukti sedang dicek)') : ''))
            ->implode("\n");

        $param = [
            'tagihan' => $tagihan->judul,
            'nama' => $nama,
            'total' => FormatRupiah::rp($tagihan->total),
            'tanggal' => $tagihan->tanggal->format('d/m/Y'),
        ];

        $pembuka = match ($gaya) {
            'santai' => __('Gaes, *:tagihan* (:total) belum beres nih 👀', $param),
            'kocak' => __("📢 PENGUMUMAN PENTING 📢\nDompet :nama lagi kurus gara-gara nalangin *:tagihan* (:total) 🥲", $param),
            default => __("Halo semuanya 🙏\nMau mengingatkan tagihan *:tagihan* (:total) tanggal :tanggal.", $param),
        };

        $penutup = match ($gaya) {
            'santai' => __('Kirim bukti di sini biar langsung kecentang ✅'),
            'kocak' => __('Yuk lunasin sebelum :nama makan nasi pakai garam doang 🙏', $param),
            default => __('Kalau sudah transfer, kirim buktinya di sini ya. Terima kasih! 😊'),
        };

        $bagian = [$pembuka];

        $bagian[] = $belumLunas === ''
            ? __('Semua sudah lunas. Makasih semuanya! 🎉')
            : __('Yang belum lunas:')."\n".$belumLunas;

        $rekening = $pembayar->user?->infoBayar() ?? [];

        if ($rekening !== []) {
            $bagian[] = __('Transfer ke :nama:', ['nama' => $nama])."\n".collect($rekening)
                ->map(fn (array $baris): string => '• '.$baris['jenis'].' '.$baris['nomor'].(filled($baris['atas_nama'] ?? null) ? ' '.__('a.n.').' '.$baris['atas_nama'] : ''))
                ->implode("\n");
        }

        if ($belumLunas !== '') {
            $bagian[] = $penutup."\n".route('tagihan.show', [$tagihan->grup_id, $tagihan->id]);
        }

        return implode("\n\n", $bagian);
    }

    /**
     * Link WhatsApp yang membuka pesan ini, tinggal pilih grup tujuannya.
     */
    public static function linkWhatsapp(string $pesan): string
    {
        return 'https://wa.me/?text='.rawurlencode($pesan);
    }
}
