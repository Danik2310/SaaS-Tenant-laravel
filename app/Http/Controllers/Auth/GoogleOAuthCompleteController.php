<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Shared\Support\JwtCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant end of the Google sign-in handoff.
 *
 * The central callback ({@see GoogleOAuthController::callback()}) has already
 * resolved — or created — the tenant user, but a JWT can only be minted inside
 * tenant context, so it redirects here with a signed, 60-second URL. The
 * signature is the credential: the route is deliberately outside `auth` (there
 * is no session yet) and outside `guest` (re-running it for an already signed-in
 * browser must not 302 in a circle).
 *
 * The controller re-checks the account instead of trusting the URL: the link
 * carries a user id, not a permission.
 */
class GoogleOAuthCompleteController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->find($request->query('user'));

        if (! $user instanceof User || ! $user->is_active || ! $user->hasVerifiedEmail()) {
            return redirect()->route('login')->withErrors([
                'email' => 'We could not complete Google sign-in. Please try again.',
            ]);
        }

        $request->session()->regenerate();

        $token = Auth::guard('web')->login($user);

        return redirect(RouteServiceProvider::HOME)->withCookie(JwtCookie::make($token));
    }
}
