<?php

namespace App\Console;

use App\Shared\Services\ExportService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Console kernel — serves artisan and the scheduled task runner.
 *
 * Flow:
 *  1. `commands()` registers the `app/Console/Commands` directory and
 *     `routes/console.php` closures on boot.
 *  2. `schedule()` defines the recurring tasks run by
 *     `php artisan schedule:run` (driven by a cron entry in production).
 *
 * @see docs/architecture/08-console-and-scheduled.md
 */
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Registered tasks (all idempotent, safe to re-run):
     *  - ExportService::cleanupExpired() — purge expired export files (hourly).
     *  - `tenants:collect-metrics` — snapshot tenant resource usage (hourly).
     *  - `tenants:expire-trials` — move lapsed trials out of Trial (daily).
     *  - `tenants:expire-subscriptions` — expire lapsed subscriptions (daily).
     *
     * @see ExportService::cleanupExpired()
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
     * Register the console commands for the application.
     *
     * Loads every command class in `app/Console/Commands` and the closure-based
     * commands declared in `routes/console.php`. Module commands are registered
     * separately by their own service providers.
     *
     * @see docs/architecture/02-providers-and-container.md
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
