<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tenant;

interface TenantBuilderInterface
{
    public function withDomain(string $domain): static;

    public function withPlan(?string $planSlug = null): static;

    public function build(): Tenant;
}
