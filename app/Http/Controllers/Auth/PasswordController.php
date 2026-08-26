<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Shared\Support\JwtCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

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
