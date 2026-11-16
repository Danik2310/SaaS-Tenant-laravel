<?php

declare(strict_types=1);

namespace App\Tenants\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\FindWorkspaceRequest;
use App\Tenants\Contracts\TenantRepositoryInterface;
use App\Tenants\Support\TenantVerificationUrl;
use App\Tenants\Support\WorkspaceAddress;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Front door for returning users on the central marketing site.
 *
 * `GET /login` on a central domain is this screen; the tenant route of the same
 * URI keeps serving the real login on tenant hosts (see routes/web.php, and
 * docs/architecture/04-authentication.md §3.1). `POST /find-workspace` resolves
 * a typed address against the `domains` table and hands the browser to that
 * host's login.
 *
 * The redirect target is always the candidate that matched a `domains` row,
 * never the raw input, so this endpoint cannot be used as an open redirect.
 * The lookup runs only on submit (no per-keystroke endpoint) and is throttled,
 * because "does this workspace exist" is otherwise a free enumeration API.
 */
final class WorkspaceLookupController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/FindWorkspace', [
            'tenant_domain_suffix' => WorkspaceAddress::suffix(),
        ]);
    }

    public function __invoke(FindWorkspaceRequest $request): SymfonyResponse
    {
        $candidates = WorkspaceAddress::candidates((string) ($request->validated()['workspace'] ?? ''));

        foreach ($candidates as $candidate) {
            if (WorkspaceAddress::isCentral($candidate)) {
                throw $this->notFound();
            }
        }

        foreach ($candidates as $candidate) {
            $tenant = $this->tenants->findByDomain($candidate);

            if ($tenant === null) {
                continue;
            }

            // Use the address the visitor typed, not the tenant's primary
            // domain: a custom-domain user should stay on the host they know.
            $url = TenantVerificationUrl::loginFor($tenant, $candidate);

            if ($url !== null) {
                // Inertia visits get a 409 + X-Inertia-Location, plain form
                // posts get a 302 — either way the browser navigates to the
                // tenant host and signs in there (the JWT cookie is host-local).
                return Inertia::location($url);
            }
        }

        throw $this->notFound();
    }

    private function notFound(): ValidationException
    {
        // One message for "unknown" and "not an address": telling the two apart
        // would only help someone probing which workspaces exist. A 422 keeps
        // the error inline in the finder instead of reloading the page.
        return ValidationException::withMessages([
            'workspace' => 'We could not find a workspace at that address. Check the spelling, or create a new workspace.',
        ]);
    }
}
