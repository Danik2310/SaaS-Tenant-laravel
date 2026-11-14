<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Shared\Support\JwtCookie;
use App\Shared\Support\OAuthCallbackError;
use App\Shared\Support\OAuthProviderLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant login / logout.
 *
 * Login is session-scoped but the credential is a **JWT cookie**:
 *  - `create()` renders the Inertia `Auth/Login` page.
 *  - `store()` authenticates via {@see LoginRequest} (rate-limited), regenerates
 *    the session and attaches the JWT cookie.
 *  - `destroy()` logs out the `web` guard, invalidates the session and clears the
 *    JWT cookie.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            // "Continue with …" links; empty when no provider is configured.
            'providers' => OAuthProviderLinks::for('login'),
            // Signed `?oauth_error=` carried back by the Google callback.
            'oauthError' => OAuthCallbackError::message($request),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $token = $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME)->withCookie(JwtCookie::make($token));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->withCookie(JwtCookie::forget());
    }
}
