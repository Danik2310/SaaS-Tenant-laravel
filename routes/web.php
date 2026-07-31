<?php

use App\Billing\Http\Controllers\Admin\PaymentMethodController;
use App\Billing\Http\Controllers\Admin\SubscriptionController;
use App\Billing\Http\Controllers\Admin\SubscriptionPaymentController;
use App\Plans\Http\Controllers\Admin\FeatureFlagController;
use App\Plans\Http\Controllers\Admin\PlanController;
use App\Shared\Http\Controllers\Admin\ActivityLogController;
use App\Shared\Http\Controllers\Admin\AdminProfileController;
use App\Shared\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Shared\Http\Controllers\Admin\DashboardController;
use App\Shared\Http\Controllers\Admin\ExportController;
use App\Shared\Http\Controllers\Admin\ImpersonationController;
use App\Shared\Http\Controllers\Admin\RolePermissionController;
use App\Shared\Http\Controllers\Admin\SettingController;
use App\Shared\Http\Controllers\Admin\StaffController;
use App\Tenants\Http\Controllers\Admin\TenantController;
use App\Tenants\Http\Controllers\Admin\TenantMetricsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (Central Management)
|--------------------------------------------------------------------------
*/

// Central admin login/logout (restricted to central domains)
Route::middleware(['central.domain'])->group(function () {
    Route::get('/central/login', [AdminAuthController::class, 'showLogin'])->name('central.login');
    Route::post('/central/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/central/logout', [AdminAuthController::class, 'logout'])->middleware('auth:admin')->name('central.logout');
});

// Unauthorized page (accessible to authenticated admin users on central domain only)
Route::middleware(['auth:admin', 'central.domain'])->get('/admin/unauthorized', function () {
    return Inertia::render('Unauthorized');
})->name('admin.unauthorized');

// Protected admin routes — only accessible on central domains
Route::middleware(['auth:admin', 'throttle:100,1', 'impersonation.expiry', 'central.domain'])->prefix('admin')->group(function () {
    Route::get('/user', [AdminAuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/api/dashboard-stats', [DashboardController::class, 'stats'])->middleware('permission:manage tenants,admin');

    // Admin profile endpoints (everyone authenticated should be able to manage own profile)
    Route::middleware(['permission:manage profile,admin'])->group(function () {
        Route::get('/api/profile', [AdminProfileController::class, 'show']);
        Route::put('/api/profile', [AdminProfileController::class, 'updateProfile']);
        Route::put('/api/profile/password', [AdminProfileController::class, 'updatePassword']);
        Route::delete('/api/profile', [AdminProfileController::class, 'deleteAccount']);
    });

    // Tenant management API endpoints - requires 'manage tenants' permission
    Route::middleware(['permission:manage tenants,admin'])->group(function () {
        // Specific routes must come BEFORE parameterized routes
        Route::post('/api/tenants/bulk', [TenantController::class, 'bulkOperation']);
        Route::get('/api/tenants', [TenantController::class, 'index']);
        Route::get('/api/tenants/{id}', [TenantController::class, 'show']);
        Route::post('/api/tenants', [TenantController::class, 'store']);
        Route::put('/api/tenants/{id}', [TenantController::class, 'update']);
        Route::delete('/api/tenants/{id}', [TenantController::class, 'destroy']);
        Route::patch('/api/tenants/{id}/restore', [TenantController::class, 'restore']);

        // additional endpoints for tenants
        Route::get('/api/tenants/{id}/database', [TenantController::class, 'database']);
        Route::post('/api/tenants/{id}/migrate', [TenantController::class, 'migrate']);
        Route::put('/api/tenants/{id}/plan', [TenantController::class, 'changePlan']);
    });

    // Lightweight dropdown endpoints — accessible to all authenticated admins
    Route::get('/api/plans-list', [TenantController::class, 'plans']);
    Route::get('/api/tenants-list', [TenantController::class, 'tenants']);

    // Staff management - requires 'manage staff' permission
    Route::middleware(['permission:manage staff,admin'])->group(function () {
        // Specific routes must come BEFORE parameterized routes
        Route::get('/api/staff/get-roles', [StaffController::class, 'getRoles']);
        Route::get('/api/staff/get-permissions', [StaffController::class, 'getPermissions']);

        // Parameterized and other routes
        Route::get('/api/staff', [StaffController::class, 'index']);
        Route::post('/api/staff', [StaffController::class, 'store']);
        Route::get('/api/staff/{id}', [StaffController::class, 'show']);
        Route::put('/api/staff/{id}', [StaffController::class, 'update']);
        Route::delete('/api/staff/{id}', [StaffController::class, 'destroy']);
        Route::patch('/api/staff/{id}/restore', [StaffController::class, 'restore']);
        Route::patch('/api/staff/{id}/toggle-status', [StaffController::class, 'toggleStatus']);
        Route::post('/api/staff/{id}/roles', [StaffController::class, 'assignRoles']);
        Route::post('/api/staff/{id}/permissions', [StaffController::class, 'assignPermissions']);
    });

    // Plans management - requires 'manage plans' permission
    Route::middleware(['permission:manage plans,admin'])->group(function () {
        Route::get('/api/plans', [PlanController::class, 'index']);
        Route::post('/api/plans', [PlanController::class, 'store']);
        Route::get('/api/plans/{id}', [PlanController::class, 'show']);
        Route::put('/api/plans/{id}', [PlanController::class, 'update']);
        Route::delete('/api/plans/{id}', [PlanController::class, 'destroy']);

        Route::get('/api/feature-flags', [FeatureFlagController::class, 'index']);
        Route::post('/api/feature-flags', [FeatureFlagController::class, 'store']);
        Route::get('/api/feature-flags/{id}', [FeatureFlagController::class, 'show']);
        Route::put('/api/feature-flags/{id}', [FeatureFlagController::class, 'update']);
        Route::delete('/api/feature-flags/{id}', [FeatureFlagController::class, 'destroy']);
    });

    // Payment methods management - requires 'manage payment methods' permission
    Route::middleware(['permission:manage payment methods,admin', 'payment.rate.limit'])->group(function () {
        Route::get('/api/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/api/payment-methods', [PaymentMethodController::class, 'store']);
        Route::get('/api/payment-methods/{id}', [PaymentMethodController::class, 'show']);
        Route::put('/api/payment-methods/{id}', [PaymentMethodController::class, 'update']);
        Route::patch('/api/payment-methods/{id}/toggle-active', [PaymentMethodController::class, 'toggleActive']);
        Route::delete('/api/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
    });

    // Impersonation (God Mode) - requires 'impersonate tenants' permission
    Route::middleware(['permission:impersonate tenants,admin', 'impersonation.expiry'])->group(function () {
        Route::post('/api/impersonate', [ImpersonationController::class, 'start']);
        Route::post('/api/impersonate/stop', [ImpersonationController::class, 'stop']);
    });

    // Role & Permission management - requires 'manage staff' permission
    Route::middleware(['permission:manage staff,admin'])->group(function () {
        // Roles
        Route::get('/api/roles', [RolePermissionController::class, 'indexRoles']);
        Route::post('/api/roles', [RolePermissionController::class, 'storeRole']);
        Route::put('/api/roles/{id}', [RolePermissionController::class, 'updateRole']);
        Route::delete('/api/roles/{id}', [RolePermissionController::class, 'destroyRole']);

        // Permissions
        Route::get('/api/permissions', [RolePermissionController::class, 'indexPermissions']);
        Route::post('/api/permissions', [RolePermissionController::class, 'storePermission']);
        Route::put('/api/permissions/{id}', [RolePermissionController::class, 'updatePermission']);
        Route::delete('/api/permissions/{id}', [RolePermissionController::class, 'destroyPermission']);
    });

    // Subscription management - read-only, requires 'manage subscriptions' permission
    Route::middleware(['permission:manage subscriptions,admin'])->group(function () {
        Route::get('/api/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/api/subscriptions/{id}', [SubscriptionController::class, 'show']);
        Route::get('/api/subscriptions/{id}/payments', [SubscriptionPaymentController::class, 'index']);
        Route::post('/api/subscriptions/{id}/payments', [SubscriptionPaymentController::class, 'store']);
        Route::put('/api/subscriptions/{id}/payments/{paymentId}', [SubscriptionPaymentController::class, 'update']);
    });

    // Activity Logs - requires 'view activity logs' permission
    Route::middleware(['permission:view activity logs,admin'])->group(function () {
        Route::get('/api/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/api/activity-logs/log-names', [ActivityLogController::class, 'logNames']);
        Route::get('/api/activity-logs/causers', [ActivityLogController::class, 'causers']);
        Route::get('/api/activity-logs/{id}', [ActivityLogController::class, 'show']);
    });

    // Global settings - requires 'manage settings' permission
    Route::middleware(['permission:manage settings,admin'])->group(function () {
        Route::get('/api/settings', [SettingController::class, 'index']);
        Route::put('/api/settings', [SettingController::class, 'update']);
        Route::get('/api/settings/{key}', [SettingController::class, 'get']);
    });

    // Data export - POST uses ExportRequest for entity-level authorization
    // Download and status require 'manage tenants' to access stored files
    Route::post('/api/export/{entity}', [ExportController::class, 'export']);
    Route::middleware(['permission:manage tenants,admin'])->group(function () {
        Route::get('/api/export/download/{filename}', [ExportController::class, 'download']);
        Route::get('/api/export/status/{jobId}', [ExportController::class, 'status']);
    });

    // Tenant resource usage metrics
    Route::middleware(['permission:manage tenants,admin'])->group(function () {
        Route::get('/api/resource-usage', [TenantMetricsController::class, 'index']);
        Route::get('/api/resource-usage/summary', [TenantMetricsController::class, 'summary']);
        Route::get('/api/resource-usage/{tenantId}/history', [TenantMetricsController::class, 'history']);
        Route::get('/api/resource-usage/{tenantId}', [TenantMetricsController::class, 'show']);
    });
});
