@props([
    'nama',
    'langkah' => [],
])

{{--
    Panduan langkah demi langkah untuk satu halaman. Dijalankan otomatis sekali
    saat halaman pertama kali dibuka, dan bisa diulang dari tombol Panduan.

    Tiap langkah: ['elemen' => '[data-tur="..."]' atau daftar selector, 'judul' => '...', 'isi' => '...', 'sisi' => 'bottom'].
    Langkah tanpa elemen tampil di tengah layar. Elemen yang tidak terlihat dilewati.
--}}
<script type="application/json" data-tur-halaman="{{ $nama }}">
    {!! json_encode([
        'langkah' => $langkah,
        'teks' => [
            'lanjut' => __('Lanjut'),
            'kembali' => __('Kembali'),
            'selesai' => __('Selesai'),
            'progres' => __(':sekarang dari :total'),
            'tutup' => __('Tutup panduan'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
</script>
