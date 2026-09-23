<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Domain;
use Illuminate\Support\Str;

class SubdomainGenerator
{
    public function generate(?string $companyName): string
    {
        $slug = trim(Str::slug((string) $companyName), '-');

        if ($slug === '') {
            $slug = 'workspace-'.substr(md5(strtolower(trim((string) $companyName))), 0, 6);
        }

        $slug = mb_substr($slug, 0, 60);
        $suffix = (string) config('tenancy.tenant_domain_suffix', 'sasapp');

        $candidate = $slug;
        $i = 2;

        while (Domain::where('domain', $candidate.'.'.$suffix)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate.'.'.$suffix;
    }
}
