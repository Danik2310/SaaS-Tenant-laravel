<?php

use App\Billing\Http\Controllers\Admin\PaymentMethodController;
use App\Billing\Http\Controllers\Admin\SubscriptionController;
use App\Billing\Http\Controllers\Admin\SubscriptionPaymentController;
use App\Plans\Http\Controllers\Admin\FeatureFlagController;
use App\Plans\Http\Controllers\Admin\PlanController;
use App\Shared\Constants\PermissionNames;
use App\Shared\Http\Controllers\Admin\ActivityLogController;
use App\Shared\Http\Controllers\Admin\AdminProfileController;
use App\Shared\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Shared\Http\Controllers\Admin\DashboardController;
use App\Shared\Http\Controllers\Admin\ExchangeRateController;
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
    return Inertia::render('Unauthorized', [
        'message' => request()->query('message'),
    ]);
})->name('admin.unauthorized');

// Protected admin routes — only accessible on central domains
Route::middleware(['auth:admin', 'throttle:100,1', 'impersonation.expiry', 'central.domain'])->prefix('admin')->group(function () {
    Route::get('/user', [AdminAuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Dashboard statistics - requires read-level tenant access
    Route::get('/api/dashboard-stats', [DashboardController::class, 'stats'])->middleware(PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin'));

    // Admin profile endpoints (everyone authenticated should be able to manage own profile)
    Route::middleware([PermissionNames::middleware([PermissionNames::MANAGE_PROFILE], 'admin')])->group(function () {
        Route::get('/api/profile', [AdminProfileController::class, 'show']);
        Route::put('/api/profile', [AdminProfileController::class, 'updateProfile']);
        Route::put('/api/profile/password', [AdminProfileController::class, 'updatePassword']);
        Route::delete('/api/profile', [AdminProfileController::class, 'deleteAccount']);
    });

    // Tenant management - granular per-action permissions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin')])->group(function () {
        Route::get('/api/tenants', [TenantController::class, 'index']);
        Route::get('/api/tenants/{id}', [TenantController::class, 'show']);
        Route::get('/api/tenants/{id}/database', [TenantController::class, 'database']);
    });
    Route::post('/api/tenants/bulk', [TenantController::class, 'bulkOperation'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_TENANTS, PermissionNames::DELETE_TENANTS, PermissionNames::RESTORE_TENANTS], 'admin'));
    Route::post('/api/tenants', [TenantController::class, 'store'])->middleware(PermissionNames::middleware([PermissionNames::CREATE_TENANTS], 'admin'));
    Route::put('/api/tenants/{id}', [TenantController::class, 'update'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_TENANTS], 'admin'));
    Route::delete('/api/tenants/{id}', [TenantController::class, 'destroy'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_TENANTS], 'admin'));
    Route::patch('/api/tenants/{id}/restore', [TenantController::class, 'restore'])->middleware(PermissionNames::middleware([PermissionNames::RESTORE_TENANTS], 'admin'));
    Route::post('/api/tenants/{id}/migrate', [TenantController::class, 'migrate'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_TENANTS], 'admin'));
    Route::put('/api/tenants/{id}/plan', [TenantController::class, 'changePlan'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_TENANTS], 'admin'));

    // Lightweight dropdown endpoints - reference data used across permission boundaries
    Route::get('/api/tenants-list', [TenantController::class, 'tenants'])->middleware(PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin'));
    Route::get('/api/plans-list', [TenantController::class, 'plans'])->middleware(PermissionNames::middleware([PermissionNames::VIEW_PLANS, PermissionNames::VIEW_SUBSCRIPTIONS, PermissionNames::CREATE_TENANTS, PermissionNames::EDIT_TENANTS], 'admin'));

    // Staff management - granular per-action permissions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_STAFF], 'admin')])->group(function () {
        // Specific routes must come BEFORE parameterized routes
        Route::get('/api/staff/get-roles', [StaffController::class, 'getRoles']);
        Route::get('/api/staff/get-permissions', [StaffController::class, 'getPermissions']);
        Route::get('/api/staff', [StaffController::class, 'index']);
        Route::get('/api/staff/{id}', [StaffController::class, 'show']);
    });
    Route::post('/api/staff', [StaffController::class, 'store'])->middleware(PermissionNames::middleware([PermissionNames::CREATE_STAFF], 'admin'));
    Route::middleware([PermissionNames::middleware([PermissionNames::EDIT_STAFF], 'admin')])->group(function () {
        Route::put('/api/staff/{id}', [StaffController::class, 'update']);
        Route::patch('/api/staff/{id}/restore', [StaffController::class, 'restore']);
        Route::patch('/api/staff/{id}/toggle-status', [StaffController::class, 'toggleStatus']);
        Route::post('/api/staff/{id}/roles', [StaffController::class, 'assignRoles']);
        Route::post('/api/staff/{id}/permissions', [StaffController::class, 'assignPermissions']);
    });
    Route::delete('/api/staff/{id}', [StaffController::class, 'destroy'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_STAFF], 'admin'));

    // Plans management - granular per-action permissions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_PLANS], 'admin')])->group(function () {
        Route::get('/api/plans', [PlanController::class, 'index']);
        Route::get('/api/plans/{id}', [PlanController::class, 'show']);
    });
    Route::post('/api/plans', [PlanController::class, 'store'])->middleware(PermissionNames::middleware([PermissionNames::CREATE_PLANS], 'admin'));
    Route::put('/api/plans/{id}', [PlanController::class, 'update'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_PLANS], 'admin'));
    Route::delete('/api/plans/{id}', [PlanController::class, 'destroy'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_PLANS], 'admin'));

    // Feature flags - managed as a plan sub-feature
    Route::middleware([PermissionNames::middleware([PermissionNames::MANAGE_FEATURE_FLAGS], 'admin')])->group(function () {
        Route::get('/api/feature-flags', [FeatureFlagController::class, 'index']);
        Route::post('/api/feature-flags', [FeatureFlagController::class, 'store']);
        Route::get('/api/feature-flags/{id}', [FeatureFlagController::class, 'show']);
        Route::put('/api/feature-flags/{id}', [FeatureFlagController::class, 'update']);
        Route::delete('/api/feature-flags/{id}', [FeatureFlagController::class, 'destroy']);
    });

    // Payment methods management - granular per-action permissions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_PAYMENT_METHODS], 'admin'), 'payment.rate.limit'])->group(function () {
        Route::get('/api/payment-methods', [PaymentMethodController::class, 'index']);
        Route::get('/api/payment-methods/{id}', [PaymentMethodController::class, 'show']);
    });
    Route::middleware([PermissionNames::middleware([PermissionNames::CREATE_PAYMENT_METHODS], 'admin'), 'payment.rate.limit'])->group(function () {
        Route::post('/api/payment-methods', [PaymentMethodController::class, 'store']);
    });
    Route::middleware([PermissionNames::middleware([PermissionNames::EDIT_PAYMENT_METHODS], 'admin'), 'payment.rate.limit'])->group(function () {
        Route::put('/api/payment-methods/{id}', [PaymentMethodController::class, 'update']);
        Route::patch('/api/payment-methods/{id}/toggle-active', [PaymentMethodController::class, 'toggleActive']);
    });
    Route::delete('/api/payment-methods/{id}', [PaymentMethodController::class, 'destroy'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_PAYMENT_METHODS], 'admin'), 'payment.rate.limit');

    // Impersonation (God Mode) - requires 'impersonate tenants' permission
    Route::middleware([PermissionNames::middleware([PermissionNames::IMPERSONATE_TENANTS], 'admin'), 'impersonation.expiry'])->group(function () {
        Route::post('/api/impersonate', [ImpersonationController::class, 'start']);
        Route::post('/api/impersonate/stop', [ImpersonationController::class, 'stop']);
    });

    // Role & Permission management - granular per-action permissions
    Route::get('/api/roles', [RolePermissionController::class, 'indexRoles'])->middleware(PermissionNames::middleware([PermissionNames::VIEW_ROLES], 'admin'));
    Route::post('/api/roles', [RolePermissionController::class, 'storeRole'])->middleware(PermissionNames::middleware([PermissionNames::CREATE_ROLES], 'admin'));
    Route::put('/api/roles/{id}', [RolePermissionController::class, 'updateRole'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_ROLES], 'admin'));
    Route::delete('/api/roles/{id}', [RolePermissionController::class, 'destroyRole'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_ROLES], 'admin'));

    Route::get('/api/permissions', [RolePermissionController::class, 'indexPermissions'])->middleware(PermissionNames::middleware([PermissionNames::VIEW_PERMISSIONS], 'admin'));
    Route::post('/api/permissions', [RolePermissionController::class, 'storePermission'])->middleware(PermissionNames::middleware([PermissionNames::CREATE_PERMISSIONS], 'admin'));
    Route::put('/api/permissions/{id}', [RolePermissionController::class, 'updatePermission'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_PERMISSIONS], 'admin'));
    Route::delete('/api/permissions/{id}', [RolePermissionController::class, 'destroyPermission'])->middleware(PermissionNames::middleware([PermissionNames::DELETE_PERMISSIONS], 'admin'));

    // Subscription management - read + payment actions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_SUBSCRIPTIONS], 'admin')])->group(function () {
        Route::get('/api/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/api/subscriptions/{id}', [SubscriptionController::class, 'show']);
        Route::get('/api/subscriptions/{id}/payments', [SubscriptionPaymentController::class, 'index']);
    });
    Route::middleware([PermissionNames::middleware([PermissionNames::MANAGE_SUBSCRIPTION_PAYMENTS], 'admin')])->group(function () {
        Route::post('/api/subscriptions/{id}/payments', [SubscriptionPaymentController::class, 'store']);
        Route::put('/api/subscriptions/{id}/payments/{paymentId}', [SubscriptionPaymentController::class, 'update']);
    });

    // Activity Logs - requires 'view activity logs' permission
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_ACTIVITY_LOGS], 'admin')])->group(function () {
        Route::get('/api/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/api/activity-logs/log-names', [ActivityLogController::class, 'logNames']);
        Route::get('/api/activity-logs/causers', [ActivityLogController::class, 'causers']);
        Route::get('/api/activity-logs/{id}', [ActivityLogController::class, 'show']);
    });

    // Global settings - granular read/write permissions
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_SETTINGS], 'admin')])->group(function () {
        Route::get('/api/settings', [SettingController::class, 'index']);
        Route::get('/api/settings/{key}', [SettingController::class, 'get']);
    });
    Route::put('/api/settings', [SettingController::class, 'update'])->middleware(PermissionNames::middleware([PermissionNames::EDIT_SETTINGS], 'admin'));

    // Reference data - any role that can view prices needs exchange rates
    Route::get('/api/exchange-rates', [ExchangeRateController::class, 'index'])
        ->middleware(PermissionNames::middleware([PermissionNames::VIEW_PLANS, PermissionNames::VIEW_SUBSCRIPTIONS, PermissionNames::CREATE_TENANTS, PermissionNames::EDIT_TENANTS], 'admin'));

    // Data export - POST uses ExportRequest for entity-level authorization.
    // Route-level permission middleware acts as a safety net covering all exportable entities.
    Route::post('/api/export/{entity}', [ExportController::class, 'export'])
        ->middleware(PermissionNames::middleware([PermissionNames::VIEW_TENANTS, PermissionNames::VIEW_SUBSCRIPTIONS, PermissionNames::VIEW_STAFF, PermissionNames::VIEW_PLANS, PermissionNames::VIEW_ACTIVITY_LOGS], 'admin'));
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin')])->group(function () {
        Route::get('/api/export/download/{filename}', [ExportController::class, 'download']);
        Route::get('/api/export/status/{jobId}', [ExportController::class, 'status']);
    });

    // Tenant resource usage metrics - read-level tenant access
    Route::middleware([PermissionNames::middleware([PermissionNames::VIEW_TENANTS], 'admin')])->group(function () {
        Route::get('/api/resource-usage', [TenantMetricsController::class, 'index']);
        Route::get('/api/resource-usage/summary', [TenantMetricsController::class, 'summary']);
        Route::get('/api/resource-usage/{tenantId}/history', [TenantMetricsController::class, 'history']);
        Route::get('/api/resource-usage/{tenantId}', [TenantMetricsController::class, 'show']);
    });
});
