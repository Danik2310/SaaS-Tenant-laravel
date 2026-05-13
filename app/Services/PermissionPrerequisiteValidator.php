<?php

namespace App\Services;

use App\Factories\PermissionPrerequisiteStrategyFactory;
use Illuminate\Validation\ValidationException;

class PermissionPrerequisiteValidator
{
    public function validateAll(array $permissionNames): array
    {
        $errors = [];

        foreach (PermissionPrerequisiteStrategyFactory::getManagedPermissions() as $perm) {
            if (! in_array($perm, $permissionNames, true)) {
                continue;
            }

            $strategy = PermissionPrerequisiteStrategyFactory::make($perm);
            $missing = $strategy->validate($permissionNames);

            if (! empty($missing)) {
                $errors[$perm] = [
                    'missing' => $missing,
                    'explanation' => $strategy->explanation(),
                ];
            }
        }

        return $errors;
    }

    public function validateAllOrFail(array $permissionNames): void
    {
        $errors = $this->validateAll($permissionNames);

        if (! empty($errors)) {
            $messages = collect($errors)->map(
                fn (array $error, string $perm) => "The permission '{$perm}' requires: "
                    . implode(', ', $error['missing'])
                    . '. ' . $error['explanation']
            )->values()->all();

            throw ValidationException::withMessages(['permissions' => $messages]);
        }
    }
}
