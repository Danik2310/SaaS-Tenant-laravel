<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entry points for the tenant account settings hub and its personal-account pages.
 *
 * Workspace, domain and database settings will be added to the same hub in
 * later phases; for now these pages cover only the tenant user's own account.
 */
class TenantSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Settings/Index');
    }

    public function security(): Response
    {
        return Inertia::render('Tenant/Settings/Security');
    }
}
