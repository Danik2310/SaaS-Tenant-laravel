<?php

declare(strict_types=1);

namespace App\Products;

use Illuminate\Support\ServiceProvider;

/**
 * Products module provider.
 *
 * Intentionally empty — the module has no container bindings, listeners, or
 * commands of its own. Present so every module is self-registering and has a
 * place to grow.
 */
class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
