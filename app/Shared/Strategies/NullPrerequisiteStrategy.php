<?php

namespace App\Shared\Strategies;

use App\Shared\Contracts\PermissionPrerequisiteStrategyInterface;

/** No-op strategy for permissions that have no prerequisites. */
class NullPrerequisiteStrategy implements PermissionPrerequisiteStrategyInterface
{
    public function getPrerequisites(): array
    {
        return [];
    }

    public function validate(array $assignedPermissionNames): array
    {
        return [];
    }

    public function explanation(): string
    {
        return 'No prerequisites required.';
    }
}
