<?php

namespace App\Console;

use App\Shared\Services\ExportService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Clean up expired export files every hour
        $schedule->call(function () {
            app(ExportService::class)->cleanupExpired();
        })->hourly();

        // Collect tenant resource usage metrics hourly
        $schedule->command('tenants:collect-metrics')->hourly();

        // Expire trial tenants daily
        $schedule->command('tenants:expire-trials')->daily();

        // Expire subscriptions daily
        $schedule->command('tenants:expire-subscriptions')->daily();
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
