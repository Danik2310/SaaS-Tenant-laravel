<?php

declare(strict_types=1);

namespace App\Tenants\Generators;

use App\Models\Tenant;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

class SequentialIdGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        $prefix = 'TEN-';

        $max = Tenant::withTrashed()
            ->where('id', 'like', $prefix.'%')
            ->pluck('id')
            ->map(fn (string $id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
