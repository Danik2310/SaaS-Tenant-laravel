<?php

/**
 * Application bootstrap.
 *
 * Creates the Laravel application instance and binds the three kernels that
 * drive it. Everything else — providers, middleware, routes — is wired up later
 * by those kernels.
 *
 * Flow:
 *  1. Instantiate the Application with the project base path.
 *  2. Bind the HTTP kernel (App\Http\Kernel) — serves web/API requests.
 *  3. Bind the Console kernel (App\Console\Kernel) — serves artisan/CLI.
 *  4. Bind the exception handler (App\Shared\Exceptions\Handler).
 *  5. Return the instance to the entry script (public/index.php or artisan),
 *     which then handles the incoming request or command.
 *
 * @see Kernel
 * @see App\Console\Kernel
 * @see Handler
 */

use App\Http\Kernel;
use App\Shared\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
