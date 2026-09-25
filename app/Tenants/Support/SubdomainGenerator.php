<?php

declare(strict_types=1);

namespace App\Tenants\Support;

use App\Models\Domain;
use Illuminate\Support\Str;

class SubdomainGenerator
{
    /**
     * Resolve the full workspace domain for a public registration payload.
     * An explicit, validated subdomain wins; otherwise the company name is
     * slugified and de-duplicated as before.
     */
    public function forRequest(array $data): string
    {
        $suffix = (string) config('tenancy.tenant_domain_suffix', 'sasapp');

        $subdomain = strtolower(trim((string) ($data['subdomain'] ?? '')));
        $subdomain = (string) preg_replace('/\.'.preg_quote($suffix, '/').'$/', '', $subdomain);

        if ($subdomain === '') {
            return $this->generate($data['company_name'] ?? null);
        }

        return $subdomain.'.'.$suffix;
    }

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
