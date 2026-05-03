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
    // public-facing home for tenant
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    // admin login/logout for this tenant
    Route::get('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])
        ->name('admin.login');
    Route::post('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'login']);
    Route::post('/admin/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('admin.logout');
});
