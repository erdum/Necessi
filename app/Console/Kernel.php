<?php

namespace App\Console;

use App\Jobs\DeleteOldAcceptedBids;
use App\Jobs\RemindOrderMark;
use App\Jobs\RemindOrderPayment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->job(new DeleteOldAcceptedBids)->daily();
        $schedule->job(new RemindOrderMark)->everyThreeHours();
        $schedule->job(new RemindOrderPayment)->everySixHours();
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
