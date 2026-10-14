<?php

namespace App\Shared\Factories;

use App\Shared\Constants\PermissionNames;
use App\Shared\Contracts\PermissionPrerequisiteStrategyInterface;
use App\Shared\Strategies\NullPrerequisiteStrategy;
use App\Shared\Strategies\TenantModulePrerequisiteStrategy;

/**
 * Maps managed permission names to a prerequisite strategy.
 *
 * Permissions absent from the map resolve to {@see NullPrerequisiteStrategy}
 * (no prerequisites). Only the mapped ("managed") names are ever checked by
 * {@see PermissionPrerequisiteValidator}.
 */
class PermissionPrerequisiteStrategyFactory
{
    private const STRATEGY_MAP = [
        PermissionNames::IMPERSONATE_TENANTS => TenantModulePrerequisiteStrategy::class,
        PermissionNames::MANAGE_SUBSCRIPTION_PAYMENTS => TenantModulePrerequisiteStrategy::class,
        PermissionNames::RESTORE_TENANTS => TenantModulePrerequisiteStrategy::class,
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
