<?php

use App\Http\Resources\AdminUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Loaded by RouteServiceProvider under the `api` group and the `api/v1`
| prefix. This is a thin surface: the SPA talks to Inertia routes, so the only
| endpoint here is the admin identity check.
|
| Flow:
|  1. `GET /api/v1/user` — requires the `admin` guard and is rate-limited per
|     admin. The token is read from an `Authorization: Bearer` header or from
|     the HttpOnly `token` cookie (the api group has no cookie middleware, so
|     `jwt.cookie` promotes the cookie first). Non-admin principals — tenant
|     JWTs and Sanctum personal access tokens — are rejected by the guard
|     before the route closure runs.
|
*/

Route::middleware(['jwt.cookie', 'auth:admin', 'throttle:api'])->get('/user', function (Request $request) {
    return response()->json([
        'user' => new AdminUserResource($request->user('admin')),
    ]);
});
