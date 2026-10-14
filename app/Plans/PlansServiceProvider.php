<?php

declare(strict_types=1);

namespace App\Plans;

use App\Plans\Services\PublicPlanCatalog;
use App\Shared\Contracts\PublicPlanCatalogInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Plans module provider.
 *
 * Binds the public (marketing-facing) plan catalog —
 * {@see PublicPlanCatalogInterface} → {@see PublicPlanCatalog}. The rest of the
 * module is resolved by the container directly.
 */
class PlansServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublicPlanCatalogInterface::class, PublicPlanCatalog::class);
    }

    public function boot(): void
    {
        //
    }
}
