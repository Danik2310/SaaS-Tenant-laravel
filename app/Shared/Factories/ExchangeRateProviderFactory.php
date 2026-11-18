<?php

declare(strict_types=1);

namespace App\Shared\Factories;

use App\Shared\Adapters\OpenErApiExchangeRateService;
use Swap\Builder;
use Swap\Swap;

/**
 * Builds Swap with the project's open.er-api.com adapter as the first provider.
 *
 * Configured third-party Swap services remain available as fallbacks, but the
 * adapter owns the USD-based snapshot used by the application.
 */
class ExchangeRateProviderFactory
{
    public function make(): Swap
    {
        $builder = new Builder((array) config('swap.options', []));
        $builder->addExchangeRateService(new OpenErApiExchangeRateService);

        return $builder->build();
    }
}
