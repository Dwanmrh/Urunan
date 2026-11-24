<?php

namespace App\Console\Commands;

use App\Models\TagihanRutin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('urunan:tagihan-rutin')]
#[Description('Catat tagihan rutin yang sudah jatuh tempo')]
class JalankanTagihanRutin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $total = 0;

        TagihanRutin::query()
            ->where('aktif', true)
            ->whereDate('jadwal_berikutnya', '<=', now()->toDateString())
            ->with('grup')
            ->eachById(function (TagihanRutin $rutin) use (&$total): void {
                $total += $rutin->jalankan();
            });

        $this->info("{$total} tagihan rutin dicatat.");

        return self::SUCCESS;
    }
}
