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
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
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
