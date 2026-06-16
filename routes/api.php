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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'data' => $user instanceof AdminUser
            ? new AdminUserResource($user)
            : ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
    ]);
});
