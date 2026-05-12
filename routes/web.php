<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use Illuminate\Foundation\Application;
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
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Admin Routes (Central Management)
|--------------------------------------------------------------------------
*/

// Central admin login/logout (public)
Route::get('/central/login', [AdminAuthController::class, 'showLogin'])->name('central.login');
Route::post('/central/login', [AdminAuthController::class, 'login']);
Route::post('/central/logout', [AdminAuthController::class, 'logout'])->middleware('auth:admin')->name('central.logout');
Route::get('/admin/user', [AdminAuthController::class, 'user']);

// Unauthorized page (accessible to authenticated admin users)
Route::middleware(['auth:admin'])->get('/admin/unauthorized', function () {
    return Inertia::render('Unauthorized');
})->name('admin.unauthorized');

// Protected admin routes
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/api/dashboard-stats', [AdminDashboardController::class, 'dashboardStats'])->middleware('permission:manage tenants');

    // Admin profile endpoints (everyone authenticated should be able to manage own profile)
    Route::middleware(['permission:manage profile'])->group(function () {
        Route::get('/api/profile', [AdminProfileController::class, 'show']);
        Route::put('/api/profile', [AdminProfileController::class, 'updateProfile']);
        Route::put('/api/profile/password', [AdminProfileController::class, 'updatePassword']);
        Route::delete('/api/profile', [AdminProfileController::class, 'deleteAccount']);
    });

    // Tenant management API endpoints - requires 'manage tenants' permission
    Route::middleware(['permission:manage tenants'])->group(function () {
        // Specific routes must come BEFORE parameterized routes
        Route::post('/api/tenants/bulk', [AdminDashboardController::class, 'bulkTenantOperation']);
        Route::get('/api/tenants', [AdminDashboardController::class, 'tenants']);
        Route::get('/api/tenants/{id}', [AdminDashboardController::class, 'showTenant']);
        Route::post('/api/tenants', [AdminDashboardController::class, 'createTenant']);
        Route::put('/api/tenants/{id}', [AdminDashboardController::class, 'updateTenant']);
        Route::delete('/api/tenants/{id}', [AdminDashboardController::class, 'deleteTenant']);
        Route::patch('/api/tenants/{id}/restore', [AdminDashboardController::class, 'restoreTenant']);

        // additional endpoints for tenants
        Route::get('/api/tenants/{id}/database', [AdminDashboardController::class, 'tenantDatabase']);
        Route::post('/api/tenants/{id}/migrate', [AdminDashboardController::class, 'migrateTenant']);
        Route::put('/api/tenants/{id}/plan', [AdminDashboardController::class, 'changeTenantPlan']);
    });

    // Staff management - requires 'manage staff' permission
    Route::middleware(['permission:manage staff'])->group(function () {
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
    Route::middleware(['permission:manage plans'])->group(function () {
        Route::get('/api/plans', [PlanController::class, 'index']);
        Route::post('/api/plans', [PlanController::class, 'store']);
        Route::get('/api/plans/{id}', [PlanController::class, 'show']);
        Route::put('/api/plans/{id}', [PlanController::class, 'update']);
        Route::delete('/api/plans/{id}', [PlanController::class, 'destroy']);
    });

    // Payment methods management - requires 'manage payment methods' permission
    Route::middleware(['permission:manage payment methods'])->group(function () {
        Route::get('/api/payment-methods', [PaymentMethodController::class, 'index']);
        Route::post('/api/payment-methods', [PaymentMethodController::class, 'store']);
        Route::get('/api/payment-methods/{id}', [PaymentMethodController::class, 'show']);
        Route::put('/api/payment-methods/{id}', [PaymentMethodController::class, 'update']);
        Route::patch('/api/payment-methods/{id}/toggle-active', [PaymentMethodController::class, 'toggleActive']);
        Route::delete('/api/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
    });

    // Impersonation (God Mode) - requires 'impersonate tenants' permission
    Route::middleware(['permission:impersonate tenants'])->group(function () {
        Route::post('/api/impersonate', [AdminDashboardController::class, 'impersonateTenant']);
        Route::post('/api/impersonate/stop', [AdminDashboardController::class, 'stopImpersonation']);
    });

    // Role & Permission management - requires 'manage staff' permission
    Route::middleware(['permission:manage staff'])->group(function () {
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

    // Subscription management - requires 'manage subscriptions' permission
    Route::middleware(['permission:manage subscriptions'])->group(function () {
        Route::get('/api/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/api/subscriptions/{id}', [SubscriptionController::class, 'show']);
        Route::post('/api/subscriptions', [SubscriptionController::class, 'store']);
        Route::put('/api/subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::delete('/api/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
    });

    // Activity Logs - requires 'view activity logs' permission
    Route::middleware(['permission:view activity logs'])->group(function () {
        Route::get('/api/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/api/activity-logs/log-names', [ActivityLogController::class, 'logNames']);
        Route::get('/api/activity-logs/causers', [ActivityLogController::class, 'causers']);
        Route::get('/api/activity-logs/{id}', [ActivityLogController::class, 'show']);
    });

    // Global settings - requires 'manage settings' permission
    Route::middleware(['permission:manage settings'])->group(function () {
        Route::get('/api/settings', [SettingController::class, 'index']);
        Route::put('/api/settings', [SettingController::class, 'update']);
        Route::get('/api/settings/{key}', [SettingController::class, 'get']);
    });

    // Data export - requires 'manage tenants' permission (reuses existing permission)
    Route::middleware(['permission:manage tenants'])->group(function () {
        Route::post('/api/export/{entity}', [\App\Http\Controllers\Admin\ExportController::class, 'export']);
        Route::get('/api/export/download/{filename}', [\App\Http\Controllers\Admin\ExportController::class, 'download']);
        Route::get('/api/export/status/{jobId}', [\App\Http\Controllers\Admin\ExportController::class, 'status']);
    });

    // Tenant resource usage metrics
    Route::middleware(['permission:manage tenants'])->group(function () {
        Route::get('/api/resource-usage', [\App\Http\Controllers\Admin\TenantMetricsController::class, 'index']);
        Route::get('/api/resource-usage/{tenantId}', [\App\Http\Controllers\Admin\TenantMetricsController::class, 'show']);
    });
});
