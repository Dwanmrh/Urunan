<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('urunan:tagihan-rutin')->dailyAt('07:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('urunan:ingatkan')->dailyAt('19:00')->timezone('Asia/Jakarta')->withoutOverlapping();
