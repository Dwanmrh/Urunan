<?php

namespace App\Console\Commands;

use App\Models\Tagihan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('urunan:ingatkan')]
#[Description('Ingatkan anggota yang belum melunasi tagihan')]
class IngatkanPembayaran extends Command
{
    /**
     * Execute the console command.
     *
     * Tagihan diingatkan mulai 3 hari setelah tanggalnya, paling sering seminggu sekali,
     * dan berhenti setelah 60 hari supaya tidak mengganggu.
     */
    public function handle(): int
    {
        $total = 0;

        Tagihan::query()
            ->whereDate('tanggal', '<=', now()->subDays(Tagihan::HARI_SEBELUM_DIINGATKAN)->toDateString())
            ->whereDate('tanggal', '>=', now()->subDays(Tagihan::BATAS_HARI_PENGINGAT)->toDateString())
            ->where(fn ($kueri) => $kueri->whereNull('diingatkan_pada')->orWhere('diingatkan_pada', '<=', now()->subDays(6)))
            ->eachById(function (Tagihan $tagihan) use (&$total): void {
                $total += $tagihan->ingatkanYangBelumBayar();
            });

        $this->info("{$total} pengingat dikirim.");

        return self::SUCCESS;
    }
}
