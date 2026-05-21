<?php

namespace App\Factories;

use App\Contracts\PermissionPrerequisiteStrategyInterface;
use App\Services\Permissions\Strategies\NullPrerequisiteStrategy;
use App\Services\Permissions\Strategies\TenantModulePrerequisiteStrategy;

class PermissionPrerequisiteStrategyFactory
{
    private const STRATEGY_MAP = [
        'impersonate tenants' => TenantModulePrerequisiteStrategy::class,
        'manage subscriptions' => TenantModulePrerequisiteStrategy::class,
        'restore tenants' => TenantModulePrerequisiteStrategy::class,
    ];

    public static function make(string $permissionName): PermissionPrerequisiteStrategyInterface
    {
        $strategyClass = self::STRATEGY_MAP[$permissionName] ?? NullPrerequisiteStrategy::class;

        return app($strategyClass);
    }

    public static function getManagedPermissions(): array
    {
        return array_keys(self::STRATEGY_MAP);
    }
}
