<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Guard-based authentication middleware (`auth` / `auth:admin`).
 *
 * Redirect target: JSON requests and `/admin/*` get `null` (→ 401); everyone else
 * is sent to the named `login` route when one exists.
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('admin/*')) {
            return null;
        }

        if (Route::has('login')) {
            return route('login');
        }

        return null;
    }
}
