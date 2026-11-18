<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Setting;
use App\Shared\Contracts\TenantCurrencyResolverInterface;
use Throwable;

class TenantCurrencyResolver implements TenantCurrencyResolverInterface
{
    public function currentCode(): ?string
    {
        if (tenant('id') === null) {
            return null;
        }

        try {
            $value = Setting::query()->where('key', 'currency')->value('value');
        } catch (Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? strtoupper($value) : null;
    }
}
