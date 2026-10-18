<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->guardAgainstCachedConfiguration();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * A cached config (bootstrap/cache/config.php) makes the framework ignore
     * phpunit.xml's env overrides, so the suite silently runs against the
     * development environment and database — wiping it on migrate:fresh.
     * `php artisan optimize`, which the /super-commit flow runs, creates
     * exactly that file. Fail fast with a clear remedy instead of producing
     * hundreds of misleading failures.
     */
    private function guardAgainstCachedConfiguration(): void
    {
        if (! is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            return;
        }

        throw new RuntimeException(
            'Configuration is cached at bootstrap/cache/config.php, so tests would run against the '
            .'wrong environment and database. Run `php artisan optimize:clear` and re-run the suite.'
        );
    }
}
