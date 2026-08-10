<?php

declare(strict_types=1);

use App\Billing\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ProfileController;
use App\Products\Http\Controllers\Tenant\CategoryController;
use App\Products\Http\Controllers\Tenant\InventoryMovementController;
use App\Products\Http\Controllers\Tenant\ProductController;
use App\Products\Http\Controllers\Tenant\WarehouseController;
use App\Shared\Constants\PermissionNames;
use App\Shared\Http\Controllers\Admin\AuthController;
use App\Shared\Http\Controllers\Tenant\DashboardController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

$tenancyMiddleware = [InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class];

Route::middleware(array_merge(['web'], $tenancyMiddleware, ['tenant.state']))->group(function () {
    // Public routes (no auth required)

    // Tenant user auth (end-user of the tenant)
    Route::middleware('guest')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])
            ->name('register');

        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:3,1');

        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->name('login');

        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:5,1');

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('password.request');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:3,1')
            ->name('password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:3,1')
            ->name('password.store');
    });

    // Tenant admin login (staff of the tenant)
    Route::get('/admin/login', [AuthController::class, 'showLogin'])
        ->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Authenticated tenant routes
    Route::middleware(['jwt.cookie', 'jwt.refresh:web', 'auth', 'jwt.tenant:web', 'throttle:60,1'])->group(function () {
        // Email verification — auth only, NOT verified (avoids infinite redirect loop)
        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Everything below requires email verification
        Route::middleware(['verified'])->group(function () {
            Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

            Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

            Route::put('password', [PasswordController::class, 'update'])->name('password.update');

            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            // Tenant user profile management
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            // Tenant admin auth
            Route::post('/admin/logout', [AuthController::class, 'logout'])
                ->name('admin.logout');

            // Dashboard
            Route::get('/dashboard', [DashboardController::class, 'index'])
                ->name('tenant.dashboard');

            // Billing / Plan upgrade
            Route::get('/billing/upgrade', [BillingController::class, 'upgrade'])
                ->name('billing.upgrade');

            // Products
            Route::prefix('products')->name('tenant.products.')->middleware([PermissionNames::middleware([PermissionNames::MANAGE_PRODUCTS], 'web')])->group(function () {
                Route::get('/', [ProductController::class, 'index'])->name('index');
                Route::get('/create', [ProductController::class, 'create'])->name('create');
                Route::post('/', [ProductController::class, 'store'])->middleware('storage.limit')->name('store');
                Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
                Route::put('/{product}', [ProductController::class, 'update'])->name('update');
                Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
            });

            // Categories
            Route::prefix('categories')->name('tenant.categories.')->middleware([PermissionNames::middleware([PermissionNames::MANAGE_CATEGORIES], 'web')])->group(function () {
                Route::get('/', [CategoryController::class, 'index'])->name('index');
                Route::post('/', [CategoryController::class, 'store'])->name('store');
                Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
                Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
            });

            // Warehouses
            Route::prefix('warehouses')->name('tenant.warehouses.')->middleware([PermissionNames::middleware([PermissionNames::MANAGE_INVENTORY], 'web')])->group(function () {
                Route::get('/', [WarehouseController::class, 'index'])->name('index');
                Route::post('/', [WarehouseController::class, 'store'])->name('store');
                Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
                Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
            });

            // Inventory Movements — premium feature (not available on free plan)
            Route::prefix('inventory')->name('tenant.inventory.')->middleware([PermissionNames::middleware([PermissionNames::MANAGE_INVENTORY], 'web'), 'feature:advanced', 'storage.limit'])->group(function () {
                Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
                Route::get('/create', [InventoryMovementController::class, 'create'])->name('create');
                Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
            });
        });
    });
});
