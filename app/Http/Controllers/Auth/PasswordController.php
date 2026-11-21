<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdatePasswordRequest;
use App\Shared\Support\JwtCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        try {
            $token = JWTAuth::parseToken();
            JWTAuth::invalidate($token);
        } catch (\Exception $e) {
            // Token may already be invalid — continue with session cleanup.
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withCookie(JwtCookie::forget());
    }
}
