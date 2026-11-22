<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ConnectOAuthAccountRequest;
use App\Http\Requests\Tenant\UnlinkOAuthAccountRequest;
use App\Shared\Constants\OAuthProvider;
use App\Shared\Constants\PermissionNames;
use App\Shared\Support\OAuthConnectResult;
use App\Shared\Support\OAuthProviderLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Entry points for the tenant account settings hub and its personal-account pages.
 *
 * Workspace, domain and database settings will be added to the same hub in
 * later phases; for now these pages cover only the tenant user's own account.
 */
class TenantSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Tenant/Settings/Index', [
            'canManageBilling' => $request->user()->can(PermissionNames::MANAGE_PAYMENTS),
        ]);
    }

    public function security(Request $request): Response
    {
        return Inertia::render('Tenant/Settings/Security', [
            'connectedAccounts' => $request->user()->connectedAccounts(),
            'hasPassword' => $request->user()->password !== null,
            'canConnect' => OAuthProviderLinks::googleIsConfigured(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function destroy(UnlinkOAuthAccountRequest $request): RedirectResponse
    {
        $provider = (string) $request->validated('provider');

        $account = $request->user()
            ->oauthAccounts()
            ->linked()
            ->where('provider', $provider)
            ->firstOrFail();

        $account->update(['unlinked_at' => now()]);

        return back()->with('status', OAuthProvider::label($provider).' account disconnected. You can sign in with your password.');
    }

    public function connect(ConnectOAuthAccountRequest $request): SymfonyResponse
    {
        $provider = (string) $request->validated('provider');
        $url = OAuthProviderLinks::connectUrlFor($request->user(), $provider);

        if ($url === null) {
            return back()->with('error', OAuthProvider::label($provider).' sign-in is not configured.');
        }

        return Inertia::location($url);
    }

    public function connectResult(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('tenant.settings.security');
        }

        $userId = $request->query('user');

        if (! is_numeric($userId) || (int) $userId !== $request->user()->getKey()) {
            abort(403);
        }

        $message = OAuthConnectResult::message($request);

        if ($message === null) {
            return redirect()->route('tenant.settings.security');
        }

        if ($request->query('code') === OAuthConnectResult::CONNECTED) {
            return redirect()->route('tenant.settings.security')->with('status', $message);
        }

        return redirect()->route('tenant.settings.security')->with('error', $message);
    }
}
