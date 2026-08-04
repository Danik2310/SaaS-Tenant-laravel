<?php

namespace App\Shared\Constants;

/**
 * Central registry for permission and role names across guards.
 *
 * Central (admin guard) and tenant (web guard) catalogs are separate,
 * so a single string may legally exist in both (e.g. 'manage settings').
 */
final class PermissionNames
{
    // ──────────────────────────────────────────────
    //  Tenants (admin)
    // ──────────────────────────────────────────────
    public const VIEW_TENANTS = 'view tenants';

    public const CREATE_TENANTS = 'create tenants';

    public const EDIT_TENANTS = 'edit tenants';

    public const DELETE_TENANTS = 'delete tenants';

    public const RESTORE_TENANTS = 'restore tenants';

    public const IMPERSONATE_TENANTS = 'impersonate tenants';

    // ──────────────────────────────────────────────
    //  Staff (admin)
    // ──────────────────────────────────────────────
    public const VIEW_STAFF = 'view staff';

    public const CREATE_STAFF = 'create staff';

    public const EDIT_STAFF = 'edit staff';

    public const DELETE_STAFF = 'delete staff';

    // ──────────────────────────────────────────────
    //  Roles (admin)
    // ──────────────────────────────────────────────
    public const VIEW_ROLES = 'view roles';

    public const CREATE_ROLES = 'create roles';

    public const EDIT_ROLES = 'edit roles';

    public const DELETE_ROLES = 'delete roles';

    // ──────────────────────────────────────────────
    //  Permissions (admin)
    // ──────────────────────────────────────────────
    public const VIEW_PERMISSIONS = 'view permissions';

    public const CREATE_PERMISSIONS = 'create permissions';

    public const EDIT_PERMISSIONS = 'edit permissions';

    public const DELETE_PERMISSIONS = 'delete permissions';

    // ──────────────────────────────────────────────
    //  Plans (admin)
    // ──────────────────────────────────────────────
    public const VIEW_PLANS = 'view plans';

    public const CREATE_PLANS = 'create plans';

    public const EDIT_PLANS = 'edit plans';

    public const DELETE_PLANS = 'delete plans';

    public const MANAGE_FEATURE_FLAGS = 'manage feature flags';

    // ──────────────────────────────────────────────
    //  Billing (admin)
    // ──────────────────────────────────────────────
    public const VIEW_SUBSCRIPTIONS = 'view subscriptions';

    public const MANAGE_SUBSCRIPTION_PAYMENTS = 'manage subscription payments';

    public const VIEW_PAYMENT_METHODS = 'view payment methods';

    public const CREATE_PAYMENT_METHODS = 'create payment methods';

    public const EDIT_PAYMENT_METHODS = 'edit payment methods';

    public const DELETE_PAYMENT_METHODS = 'delete payment methods';

    // ──────────────────────────────────────────────
    //  System (admin)
    // ──────────────────────────────────────────────
    public const VIEW_SETTINGS = 'view settings';

    public const EDIT_SETTINGS = 'edit settings';

    public const VIEW_ACTIVITY_LOGS = 'view activity logs';

    // ──────────────────────────────────────────────
    //  Profile (admin)
    // ──────────────────────────────────────────────
    public const MANAGE_PROFILE = 'manage profile';

    // ──────────────────────────────────────────────
    //  Deprecated coarse-grained permissions (admin)
    //  Removed from the catalog by the seeder.
    // ──────────────────────────────────────────────
    public const DEPRECATED_MANAGE_TENANTS = 'manage tenants';

    public const DEPRECATED_MANAGE_STAFF = 'manage staff';

    public const DEPRECATED_MANAGE_PLANS = 'manage plans';

    public const DEPRECATED_MANAGE_PAYMENT_METHODS = 'manage payment methods';

    public const DEPRECATED_MANAGE_SUBSCRIPTIONS = 'manage subscriptions';

    public const DEPRECATED_MANAGE_SETTINGS = 'manage settings';

    // ──────────────────────────────────────────────
    //  Tenant domain (web)
    // ──────────────────────────────────────────────
    public const MANAGE_CUSTOMERS = 'manage customers';

    public const MANAGE_PRODUCTS = 'manage products';

    public const MANAGE_CATEGORIES = 'manage categories';

    public const MANAGE_ORDERS = 'manage orders';

    public const MANAGE_INVENTORY = 'manage inventory';

    public const MANAGE_PAYMENTS = 'manage payments';

    public const MANAGE_SETTINGS = 'manage settings';

    public const VIEW_REPORTS = 'view reports';

    // ──────────────────────────────────────────────
    //  Role names
    // ──────────────────────────────────────────────
    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_TENANT_ADMIN = 'tenant-admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_CASHIER = 'cashier';

    /**
     * Build a Spatie permission middleware string, e.g.
     * PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin').
     */
    public static function middleware(array $permissions, string $guard): string
    {
        return 'permission:'.implode('|', $permissions).','.$guard;
    }
}
