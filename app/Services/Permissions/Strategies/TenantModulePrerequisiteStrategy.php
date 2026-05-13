<?php

namespace App\Services\Permissions\Strategies;

use App\Contracts\PermissionPrerequisiteStrategyInterface;

class TenantModulePrerequisiteStrategy implements PermissionPrerequisiteStrategyInterface
{
    private const PREREQUISITES = ['manage tenants'];

    public function getPrerequisites(): array
    {
        return self::PREREQUISITES;
    }

    public function validate(array $assignedPermissionNames): array
    {
        return array_diff(self::PREREQUISITES, $assignedPermissionNames);
    }

    public function explanation(): string
    {
        return 'Requires the ability to list and view tenants for tenant selection and context.';
    }
}
