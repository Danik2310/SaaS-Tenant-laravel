<?php

namespace App\Shared\Contracts;

interface PermissionPrerequisiteStrategyInterface
{
    public function getPrerequisites(): array;

    public function validate(array $assignedPermissionNames): array;

    public function explanation(): string;
}
