<?php

use App\Http\Resources\AdminUserResource;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Loaded by RouteServiceProvider under the `api` group and the `api/v1`
| prefix. This is a thin surface: the SPA talks to Inertia routes, so the only
| endpoint here is the Sanctum-authenticated identity check.
|
| Flow:
|  1. `GET /api/v1/user` — requires a Sanctum token and is rate-limited per
|     tenant+user. Returns the authenticated admin, or 401 if the resolved
|     principal is not an AdminUser.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->get('/user', function (Request $request) {
    $user = $request->user();

    if (! $user instanceof AdminUser) {
        abort(401, 'Unauthorized');
    }

    return response()->json([
        'user' => new AdminUserResource($user),
    ]);
});
