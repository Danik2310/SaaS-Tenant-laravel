<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\InventoryMovementController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\WarehouseController;
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
    'tenant.state',
])->group(function () {
    // Public routes (no auth required)
    Route::get('/admin/login', [AuthController::class, 'showLogin'])
        ->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login']);

    // Authenticated tenant routes
    Route::middleware(['auth'])->group(function () {
        Route::post('/admin/logout', [AuthController::class, 'logout'])
            ->name('admin.logout');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('tenant.dashboard');

        // Products
        Route::prefix('products')->name('tenant.products.')->middleware(['permission:manage products'])->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        });

        // Categories
        Route::prefix('categories')->name('tenant.categories.')->middleware(['permission:manage categories'])->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });

        // Warehouses
        Route::prefix('warehouses')->name('tenant.warehouses.')->middleware(['permission:manage inventory'])->group(function () {
            Route::get('/', [WarehouseController::class, 'index'])->name('index');
            Route::post('/', [WarehouseController::class, 'store'])->name('store');
            Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
            Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
        });

        // Inventory Movements
        Route::prefix('inventory')->name('tenant.inventory.')->middleware(['permission:manage inventory'])->group(function () {
            Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
            Route::get('/create', [InventoryMovementController::class, 'create'])->name('create');
            Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
        });
    });
});
