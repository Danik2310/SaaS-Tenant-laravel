<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Billing\Factories\ResourceEnforcementFactory;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Shared\Constants\OAuthProvider;
use App\Shared\Exceptions\OAuthAccountRejectedException;
use App\Shared\Exceptions\PlanLimitExceededException;
use App\Shared\Support\OAuthAccountLinker;
use App\Shared\Support\OAuthCallbackError;
use App\Tenants\Support\OAuthHandoffUrl;
use App\Tenants\Support\PendingOAuthSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;
use Throwable;

/**
 * Google OAuth 2.0 entry point and callback — two flows share the endpoints:
 *
 *  - **Tenant sign-in / join** (`?tenant=…&intent=login|register`): the tenant
 *    login/register "Continue with Google" buttons.
 *  - **Workspace signup** (`?flow=signup`): Google runs *before* the central
 *    `/register` form, so the workspace step only asks for what Google does not
 *    supply.
 *
 * All endpoints live on a **central** host because Google refuses wildcard
 * redirect URIs and plain http outside loopback, so a per-tenant callback
 * (`<slug>.sasapp/auth/google/callback`) cannot be registered. For the tenant
 * flow, the tenant is carried through the flow in the central session and the
 * result is handed back with a signed, 60-second tenant URL
 * ({@see OAuthHandoffUrl}) — never a JWT in a URL. The signup flow stays on the
 * central host end to end; the verified identity is parked in the session
 * ({@see PendingOAuthSignup}) and the normal `POST /register` still provisions.
 *
 * Nothing about the tenant is trusted from the callback query string: the tenant
 * id, and the fact that this browser started the flow, come from the session
 * that `redirect()` wrote.
 */
class GoogleOAuthController extends Controller
{
    private const SESSION_TENANT = 'oauth.tenant';

    private const SESSION_INTENT = 'oauth.intent';

    private const SESSION_FLOW = 'oauth.flow';

    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly OAuthAccountLinker $linker,
    ) {}

    /**
     * Start the flow.
     *
     * `flow=signup` starts a new-workspace signup from the central `/register`
     * page; anything else carries a tenant from its login/register screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->query('flow') === 'signup') {
            $request->session()->forget([self::SESSION_TENANT, self::SESSION_INTENT]);
            $request->session()->put(self::SESSION_FLOW, 'signup');

            return $this->provider()->redirect();
        }

        $tenant = Tenant::query()->find($request->query('tenant'));

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $request->session()->forget(self::SESSION_FLOW);
        $request->session()->put(self::SESSION_TENANT, $tenant->getTenantKey());
        $request->session()->put(self::SESSION_INTENT, $request->query('intent') === 'register' ? 'register' : 'login');

        return $this->provider()->redirect();
    }

    /**
     * Google's return leg.
     *
     * Signup flows stay central and re-enter the signup form with the verified
     * identity. Tenant flows resolve the tenant user and hand a signed URL back
     * to the tenant host that mints the JWT cookie.
     */
    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull(self::SESSION_FLOW);

        if ($flow === 'signup') {
            return $this->signupCallback($request);
        }

        $tenantKey = $request->session()->pull(self::SESSION_TENANT);
        $intent = $request->session()->pull(self::SESSION_INTENT);

        $tenant = $tenantKey !== null ? Tenant::query()->find($tenantKey) : null;

        if (! $tenant instanceof Tenant) {
            // Nothing identifies the workspace any more (expired/foreign
            // session), so there is no tenant login page to return to.
            Log::warning('Google OAuth callback arrived without a tenant in session.');

            return redirect('/')->with('error', 'Your Google sign-in session expired. Please start again from your workspace login page.');
        }

        // Consent refused, or Google returned an error instead of a code.
        if ($request->query('error') !== null) {
            return $this->backToTenant($tenant, OAuthCallbackError::CANCELLED);
        }

        try {
            $identity = $this->provider()->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth token exchange failed.', [
                'tenant' => $tenant->getTenantKey(),
                'exception' => $e::class,
            ]);

            return $this->backToTenant($tenant, OAuthCallbackError::FAILED);
        }

        try {
            tenancy()->initialize($tenant);

            $user = $this->linker->loginOrRegister(
                $identity,
                ResourceEnforcementFactory::make($tenant),
                OAuthProvider::GOOGLE,
                // Only the register screen may create a member; the login
                // button refuses an unknown identity with `no_account`.
                $intent === 'register',
            );

            $handoff = OAuthHandoffUrl::for($user, $tenant);
        } catch (OAuthAccountRejectedException $e) {
            return $this->backToTenant($tenant, $this->codeFor($e->reason));
        } catch (PlanLimitExceededException) {
            return $this->backToTenant($tenant, OAuthCallbackError::USER_LIMIT);
        } catch (Throwable $e) {
            Log::error('Google OAuth account provisioning failed.', [
                'tenant' => $tenant->getTenantKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->backToTenant($tenant, OAuthCallbackError::FAILED);
        } finally {
            tenancy()->end();
        }

        if ($handoff === null) {
            return $this->backToTenant($tenant, OAuthCallbackError::FAILED);
        }

        return redirect()->away($handoff);
    }

    /**
     * The signup leg: stay on the central host, verify the identity, park it in
     * the session and return to the plan step. No tenant is provisioned here —
     * the normal `POST /register` is the single provisioning choke point, so a
     * cancelled or retried consent cannot create (or pay for) a workspace.
     */
    private function signupCallback(Request $request): RedirectResponse
    {
        if ($request->query('error') !== null) {
            return $this->backToSignup(OAuthCallbackError::CANCELLED);
        }

        try {
            $identity = $this->provider()->user();
        } catch (Throwable $e) {
            Log::warning('Google signup token exchange failed.', [
                'exception' => $e::class,
            ]);

            return $this->backToSignup(OAuthCallbackError::FAILED);
        }

        $email = Str::lower(trim((string) $identity->getEmail()));

        if ($email === '') {
            return $this->backToSignup(OAuthCallbackError::FAILED);
        }

        // A signup address must be one Google vouches for. The form forces this
        // address on submit, so an unverified one could never be confirmed.
        if (! (bool) ($identity->getRaw()['email_verified'] ?? false)) {
            return $this->backToSignup(OAuthCallbackError::UNVERIFIED);
        }

        // The submit validation would reject this too, but the workspace form
        // asks for a subdomain first; failing here keeps the visitor from
        // filling in a whole workspace that can never be created.
        if (Tenant::query()->where('email', $email)->exists()) {
            PendingOAuthSignup::forget();

            return $this->backToSignup(OAuthCallbackError::ACCOUNT_EXISTS);
        }

        $name = trim((string) ($identity->getName() ?? $identity->getNickname() ?? ''));

        PendingOAuthSignup::remember($name !== '' ? $name : Str::before($email, '@'), $email);

        // Same-host, relative redirect: the signup flow never leaves the
        // central host, and `route('register.tenant')` is bound to whichever
        // central domain claimed the name, not necessarily this request's.
        return redirect('/register?oauth=1');
    }

    private function backToSignup(string $code): RedirectResponse
    {
        return redirect('/register?'.http_build_query(['oauth_error' => $code]));
    }

    /**
     * The Socialite Google driver with the registered redirect URI forced on.
     *
     * `prompt=select_account` keeps the consent screen from silently reusing
     * whichever Google account the browser happens to hold.
     */
    private function provider(): GoogleProvider
    {
        $provider = $this->socialite->driver(OAuthProvider::GOOGLE);

        if (! $provider instanceof GoogleProvider) {
            throw new RuntimeException('Socialite did not return the Google provider.');
        }

        return $provider
            ->redirectUrl($this->redirectUri())
            ->with(['prompt' => 'select_account']);
    }

    /** Must match the Authorized redirect URI in Google Cloud Console. */
    private function redirectUri(): string
    {
        $configured = (string) config('services.google.redirect');

        return $configured !== '' ? $configured : route('oauth.google.callback');
    }

    private function backToTenant(Tenant $tenant, string $code): RedirectResponse
    {
        $url = OAuthHandoffUrl::errorFor($tenant, $code);

        if ($url === null) {
            return redirect('/')->with('error', OAuthCallbackError::FAILED);
        }

        return redirect()->away($url);
    }

    private function codeFor(string $reason): string
    {
        return match ($reason) {
            OAuthAccountRejectedException::INACTIVE => OAuthCallbackError::INACTIVE,
            OAuthAccountRejectedException::UNVERIFIED_EMAIL => OAuthCallbackError::UNVERIFIED,
            OAuthAccountRejectedException::NO_ACCOUNT => OAuthCallbackError::NO_ACCOUNT,
            default => OAuthCallbackError::FAILED,
        };
    }
}
