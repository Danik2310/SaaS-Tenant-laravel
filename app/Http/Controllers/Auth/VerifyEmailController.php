<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Tenants\Support\TenantVerificationUrl;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirms a tenant user's email address from the signed link in the mail.
 *
 * The link is the credential, so the route is deliberately outside the `auth`
 * group: mail clients open it in whatever browser is at hand, and an owner who
 * is not signed in must still be able to confirm. Requiring a session sent them
 * through the login form before the address was confirmed, which read as a
 * failure to the person clicking "Verify my email".
 *
 * The signature is validated here instead of through the `signed` middleware so
 * an expired or tampered link lands on a branded page with a way forward rather
 * than the framework's bare 403.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->invalidLink($request);
        }

        $user = User::find($id);

        // The hash binds the link to the address it was minted for, so a valid
        // signature over a different id/hash pair still cannot confirm someone.
        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->invalidLink($request);
        }

        $alreadyVerified = $user->hasVerifiedEmail();

        if (! $alreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return redirect()->route('login');
        }

        $pendingUrl = TenantVerificationUrl::pendingFor($tenant, $user->getEmailForVerification());

        if ($pendingUrl === null) {
            return redirect()->route('login');
        }

        // The signed waiting screen re-reads `email_verified_at`, so it shows the
        // green check and hands the owner to the tenant login page.
        return redirect()->to($pendingUrl);
    }

    private function invalidLink(Request $request): Response
    {
        return Inertia::render('Auth/EmailVerificationExpired', [
            'loginUrl' => route('login'),
        ])->toResponse($request)->setStatusCode(403);
    }
}
