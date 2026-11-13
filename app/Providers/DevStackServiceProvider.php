<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\DevStack;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Brings up the local dev stack when `php artisan serve` runs.
 *
 * Listening to CommandStarting (instead of overriding Laravel's ServeCommand)
 * keeps the framework command untouched across upgrades. Only the `serve`
 * command is matched, so starting Reverb from the listener cannot recurse.
 */
final class DevStackServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (strtolower(trim((string) $event->command)) !== 'serve') {
                return;
            }

            $stack = $this->app->make(DevStack::class);

            if ($stack->shouldBoot()) {
                $stack->boot();
            }
        });
    }
}
