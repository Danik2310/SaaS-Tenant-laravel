<?php

namespace App\Rules;

use App\Models\Permission;
use App\Services\PermissionPrerequisiteValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PermissionPrerequisitesRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $permissionNames = Permission::whereIn('id', $value)
            ->pluck('name')
            ->all();

        $validator = app(PermissionPrerequisiteValidator::class);
        $errors = $validator->validateAll($permissionNames);

        foreach ($errors as $perm => $error) {
            $fail("The permission '{$perm}' requires: "
                . implode(', ', $error['missing'])
                . '. ' . $error['explanation']);
        }
    }
}
