<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Shared\Support\JwtCookie;
use App\Shared\Support\OAuthCallbackError;
use App\Shared\Support\OAuthProviderLinks;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            // "Continue with …" links; empty when no provider is configured.
            'providers' => OAuthProviderLinks::for('register'),
            // Signed `?oauth_error=` carried back by the Google callback.
            'oauthError' => OAuthCallbackError::message($request),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {

        $user = User::create($request->validated());

        event(new Registered($user));

        $token = Auth::login($user);

        return redirect(RouteServiceProvider::HOME)->withCookie(JwtCookie::make($token));
    }
}
