<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PRD.md §8: satu baris cron cPanel (`* * * * * php artisan schedule:run`)
// memicu ini tiap menit; command sendiri yang menyaring user berdasarkan
// reminder_time masing-masing (lihat ReminderCheckDailyCommand).
Schedule::command('reminder:check-daily')->everyMinute();
