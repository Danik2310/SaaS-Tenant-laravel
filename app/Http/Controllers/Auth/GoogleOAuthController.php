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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;
use Throwable;

/**
 * Google OAuth 2.0 entry point and callback — the tenant login/registration
 * "Continue with Google" flow.
 *
 * Both endpoints live on a **central** host because Google refuses wildcard
 * redirect URIs and plain http outside loopback, so a per-tenant callback
 * (`<slug>.sasapp/auth/google/callback`) cannot be registered. The tenant is
 * therefore carried through the flow in the central session and the result is
 * handed back with a signed, 60-second tenant URL
 * ({@see OAuthHandoffUrl}) — never a JWT in a URL.
 *
 * Nothing about the tenant is trusted from the callback query string: the tenant
 * id, and the fact that this browser started the flow, come from the session
 * that `redirect()` wrote.
 */
class GoogleOAuthController extends Controller
{
    private const SESSION_TENANT = 'oauth.tenant';

    private const SESSION_INTENT = 'oauth.intent';

    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly OAuthAccountLinker $linker,
    ) {}

    /**
     * Start the flow: remember which tenant asked, then hand off to Google.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $tenant = Tenant::query()->find($request->query('tenant'));

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $request->session()->put(self::SESSION_TENANT, $tenant->getTenantKey());
        $request->session()->put(self::SESSION_INTENT, $request->query('intent') === 'register' ? 'register' : 'login');

        return $this->provider()->redirect();
    }

    /**
     * Google's return leg: exchange the code, resolve the tenant user, then hand
     * a signed URL back to the tenant host that mints the JWT cookie.
     */
    public function callback(Request $request): RedirectResponse
    {
        $tenantKey = $request->session()->pull(self::SESSION_TENANT);
        $request->session()->pull(self::SESSION_INTENT);

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
            default => OAuthCallbackError::FAILED,
        };
    }
}
