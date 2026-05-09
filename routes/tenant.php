<?php

declare(strict_types=1);

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

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Public routes (no auth required)
    Route::get('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])
        ->name('admin.login');
    Route::post('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);

    // Authenticated tenant routes
    Route::middleware(['auth', 'tenant.state'])->group(function () {
        Route::post('/admin/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])
            ->name('admin.logout');

        // Dashboard
        Route::get('/', [\App\Http\Controllers\Tenant\DashboardController::class, 'index'])
            ->name('tenant.dashboard');

        // Products
        Route::prefix('products')->name('tenant.products.')->middleware(['permission:manage products'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\ProductController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Tenant\ProductController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Tenant\ProductController::class, 'store'])->name('store');
            Route::get('/{product}/edit', [\App\Http\Controllers\Tenant\ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [\App\Http\Controllers\Tenant\ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [\App\Http\Controllers\Tenant\ProductController::class, 'destroy'])->name('destroy');
        });

        // Categories
        Route::prefix('categories')->name('tenant.categories.')->middleware(['permission:manage categories'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\CategoryController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Tenant\CategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [\App\Http\Controllers\Tenant\CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [\App\Http\Controllers\Tenant\CategoryController::class, 'destroy'])->name('destroy');
        });

        // Warehouses
        Route::prefix('warehouses')->name('tenant.warehouses.')->middleware(['permission:manage inventory'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\WarehouseController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Tenant\WarehouseController::class, 'store'])->name('store');
            Route::put('/{warehouse}', [\App\Http\Controllers\Tenant\WarehouseController::class, 'update'])->name('update');
            Route::delete('/{warehouse}', [\App\Http\Controllers\Tenant\WarehouseController::class, 'destroy'])->name('destroy');
        });

        // Inventory Movements
        Route::prefix('inventory')->name('tenant.inventory.')->middleware(['permission:manage inventory'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\InventoryMovementController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Tenant\InventoryMovementController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Tenant\InventoryMovementController::class, 'store'])->name('store');
        });
    });
});
