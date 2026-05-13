<?php

namespace App\Services\Permissions\Strategies;

use App\Contracts\PermissionPrerequisiteStrategyInterface;

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
