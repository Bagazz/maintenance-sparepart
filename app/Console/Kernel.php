<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Backup harian. Di Windows jadwal utamanya Task Scheduler (php artisan backup:jadwalkan);
        // baris ini dipakai bila server menjalankan "php artisan schedule:run" tiap menit (cron).
        $schedule->command('backup:database')->dailyAt(config('backup.jam'))->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
