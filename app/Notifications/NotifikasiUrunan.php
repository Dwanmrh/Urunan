<?php

namespace App\Notifications;

use App\Actions\Telegram\KirimPesanTelegram;
use App\Actions\Urunan\FormatRupiah;
use App\Models\Tagihan;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

abstract class NotifikasiUrunan extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Tagihan $tagihan, public int $jumlah) {}

    /**
     * Get the notification's delivery channels.
     *
     * Notifikasi di aplikasi selalu dikirim. Telegram hanya kalau penerima sudah menghubungkannya.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $saluran = ['database'];

        if (filled($notifiable->telegram_chat_id ?? null) && KirimPesanTelegram::aktif()) {
            $saluran[] = TelegramChannel::class;
        }

        return $saluran;
    }

    /**
     * Notifikasi di aplikasi langsung disimpan, sedangkan Telegram dikirim lewat antrean
     * supaya halaman tidak menunggu respons Telegram.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
        ];
    }

    /**
     * Kalimat judul dan isi notifikasi dalam bahasa Indonesia. Kalimat ini juga
     * menjadi kunci terjemahan di lang/en.json.
     */
    public const JUDUL = '';

    public const PESAN = '';

    /**
     * Get the array representation of the notification.
     *
     * Selain teks jadi, data mentahnya ikut disimpan supaya notifikasi bisa
     * ditampilkan ulang dalam bahasa yang sedang dipilih penerima.
     *
     * @return array{judul: string, pesan: string, url: string, param: array<string, int|string>}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'judul' => $this->judul(),
            'pesan' => $this->pesan(),
            'url' => $this->urlTagihan(absolute: false),
            'param' => [...$this->param(), 'jumlah' => $this->jumlah],
        ];
    }

    /**
     * Susun judul dan isi notifikasi tersimpan dalam bahasa yang sedang aktif.
     *
     * Notifikasi lama yang belum menyimpan data mentah dibaca ulang dari kalimat
     * Indonesianya. Kalau tidak cocok, teks yang tersimpan ditampilkan apa adanya.
     *
     * @return array{judul: string, pesan: string}
     */
    public static function teksUntuk(DatabaseNotification $notifikasi): array
    {
        $data = $notifikasi->data;
        $tersimpan = [
            'judul' => (string) ($data['judul'] ?? __('Notifikasi')),
            'pesan' => (string) ($data['pesan'] ?? ''),
        ];

        $kelas = $notifikasi->type;

        if (! is_string($kelas) || ! is_subclass_of($kelas, self::class) || $kelas::PESAN === '') {
            return $tersimpan;
        }

        $param = $data['param'] ?? self::bacaParam($kelas::PESAN, $tersimpan['pesan']);

        if (! is_array($param)) {
            return $tersimpan;
        }

        if (is_int($param['jumlah'] ?? null)) {
            $param['jumlah'] = FormatRupiah::rp($param['jumlah']);
        }

        return [
            'judul' => __($kelas::JUDUL),
            'pesan' => __($kelas::PESAN, array_map('strval', $param)),
        ];
    }

    /**
     * Ambil nilai :param dari kalimat yang sudah jadi, misalnya
     * ":nama mencatat :tagihan." dan "Deren mencatat Ngegrill." menjadi
     * ['nama' => 'Deren', 'tagihan' => 'Ngegrill'].
     *
     * @return array<string, string>|null
     */
    private static function bacaParam(string $pola, string $kalimat): ?array
    {
        $regex = preg_replace_callback(
            '/:([a-z]+)|([^:]+|:)/',
            fn (array $m): string => ($m[1] ?? '') !== '' ? '(?<'.$m[1].'>.+?)' : preg_quote($m[0], '/'),
            $pola,
        );

        if (! preg_match('/^'.$regex.'$/su', $kalimat, $cocok)) {
            return null;
        }

        return array_filter($cocok, fn ($kunci): bool => is_string($kunci), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Isi pesan Telegram.
     */
    public function toTelegram(object $notifiable): string
    {
        return $this->judul()."\n\n".$this->pesan()."\n\n".__('Buka:').' '.$this->urlTagihan(absolute: true);
    }

    /**
     * Nilai yang dimasukkan ke kalimat notifikasi, selain jumlah.
     *
     * @return array<string, string>
     */
    abstract protected function param(): array;

    /**
     * Judul singkat notifikasi.
     */
    protected function judul(): string
    {
        return __(static::JUDUL);
    }

    /**
     * Isi notifikasi.
     */
    protected function pesan(): string
    {
        return __(static::PESAN, [...$this->param(), 'jumlah' => $this->rupiah()]);
    }

    /**
     * Nominal dengan titik ribuan, misalnya "Rp 50.000".
     */
    protected function rupiah(): string
    {
        return FormatRupiah::rp($this->jumlah);
    }

    /**
     * Alamat halaman tagihan yang dibahas notifikasi ini.
     */
    protected function urlTagihan(bool $absolute): string
    {
        return route('tagihan.show', ['grup' => $this->tagihan->grup_id, 'tagihan' => $this->tagihan->id], absolute: $absolute);
    }
}
