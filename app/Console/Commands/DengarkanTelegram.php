<?php

namespace App\Console\Commands;

use App\Actions\Telegram\KirimPesanTelegram;
use App\Actions\Telegram\TanganiUpdateTelegram;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class DengarkanTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:dengarkan
        {--sekali : Ambil pesan satu kali lalu berhenti}
        {--periksa : Tampilkan info bot dan webhook tanpa mengambil pesan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terima pesan bot Telegram lewat long polling, untuk pengembangan di lokal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! KirimPesanTelegram::aktif()) {
            $this->error('Isi TELEGRAM_BOT_TOKEN dan TELEGRAM_BOT_USERNAME di .env dulu.');

            return self::FAILURE;
        }

        if ($this->option('periksa')) {
            return $this->periksa();
        }

        $sekali = (bool) $this->option('sekali');
        $offset = 0;

        $this->info('Mendengarkan pesan bot Telegram. Tekan Ctrl+C untuk berhenti.');

        do {
            try {
                $respons = KirimPesanTelegram::http()->timeout(40)->get(KirimPesanTelegram::url('getUpdates'), [
                    'offset' => $offset,
                    'timeout' => $sekali ? 0 : 30,
                ]);
            } catch (ConnectionException $e) {
                $this->warn('Tidak bisa menghubungi Telegram: '.KirimPesanTelegram::samarkan($e->getMessage()));
                if (! $sekali) {
                    sleep(5);
                }

                continue;
            }

            if ($respons->failed()) {
                $tunggu = max(5, (int) $respons->json('parameters.retry_after', 5));

                $this->warn(match ($respons->status()) {
                    401, 404 => 'Token bot ditolak Telegram. Cek TELEGRAM_BOT_TOKEN di .env.',
                    409 => 'Ada proses lain yang sedang mendengarkan bot yang sama. Tutup terminal lain yang menjalankan composer run dev atau telegram:dengarkan.',
                    429 => "Terlalu sering meminta ke Telegram. Menunggu {$tunggu} detik.",
                    default => 'Telegram menolak permintaan (status '.$respons->status().').',
                }.' Pesan Telegram: '.$respons->json('description', '-'));

                if (! $sekali) {
                    sleep($tunggu);
                }

                continue;
            }

            foreach ($respons->json('result', []) as $update) {
                $offset = $update['update_id'] + 1;

                TanganiUpdateTelegram::tangani($update);
            }
        } while (! $sekali);

        return self::SUCCESS;
    }

    /**
     * Tampilkan jawaban Telegram untuk getMe dan getWebhookInfo, untuk mencari penyebab masalah.
     */
    private function periksa(): int
    {
        foreach (['getMe', 'getWebhookInfo'] as $method) {
            try {
                $respons = KirimPesanTelegram::http()->timeout(15)->get(KirimPesanTelegram::url($method));
            } catch (ConnectionException $e) {
                $this->error("{$method}: tidak bisa menghubungi Telegram: ".KirimPesanTelegram::samarkan($e->getMessage()));

                continue;
            }

            $this->line("== {$method} (status {$respons->status()})");
            $this->line(json_encode($respons->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
