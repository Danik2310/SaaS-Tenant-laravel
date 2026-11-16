# 04 — Authentication

> **Scope**: how a user or admin is authenticated — the two JWT guards, the
> JWT-in-a-cookie transport, login rate limiting, token refresh, and the idle +
> absolute session clocks.
>
> **Out of scope**: authorization/permissions (`05`), god-mode impersonation
> (`06`), and the middleware ordering (`01`).

---

## 1. Two guards, one driver

Both guards use the **`jwt` driver** (`config/auth.php:41`):

| Guard | Provider | Secret | Used by |
|-------|----------|--------|---------|
| `web` | `App\Models\User` | `jwt.guard_secrets.web` → `JWT_SECRET` | tenant app |
| `admin` | `App\Models\AdminUser` | `jwt.guard_secrets.admin` → `JWT_ADMIN_SECRET` (falls back to `JWT_SECRET`) | central admin |

The **per-guard secret** is the isolation boundary: a token minted for a tenant
user is signed with a different key than an admin token, so it cannot be validated
by the other guard even if it is somehow presented
(`config/jwt.php:38`).

The `jwt` driver is registered by
[`JwtServiceProvider::boot()`](../../app/Shared/Providers/JwtServiceProvider.php) `:33`,
which resolves `jwt.guard_secrets.{guard}` and builds a `Manager` + `JWT` +
`JWTGuard` per guard.

---

## 2. The JWT-cookie transport

The token never lives in JavaScript-reachable storage: it is issued in an
**HttpOnly cookie** by [`JwtCookie`](../../app/Shared/Support/JwtCookie.php) and
promoted back to a bearer header on the next request.

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser
    participant R as Route stack
    participant JC as AuthenticateJwtFromCookie
    participant G as jwt guard
    participant C as Controller

    Note over B,G: login response already set the HttpOnly "token" cookie
    B->>R: GET /dashboard (cookie: token=…)
    R->>JC: no Authorization header?
    JC->>R: copy cookie → Authorization: Bearer …
    R->>G: auth / auth:admin
    G->>G: verify signature with the guard's secret
    G-->>C: authenticated user
```

- [`JwtCookie::make()`](../../app/Shared/Support/JwtCookie.php) `:21` — HttpOnly,
  `secure` from `session.secure`, `SameSite=Lax`, TTL `jwt.refresh_ttl` capped at
  `session.absolute_lifetime`.
- [`JwtCookie::forget()`](../../app/Shared/Support/JwtCookie.php) `:53` — clears it.
- [`AuthenticateJwtFromCookie`](../../app/Shared/Middleware/AuthenticateJwtFromCookie.php)
  (alias `jwt.cookie`) copies the cookie into `Authorization: Bearer …` so the
  rest of the stack only deals with bearer tokens.
- [`RefreshJwtToken`](../../app/Shared/Middleware/RefreshJwtToken.php) (alias
  `jwt.refresh`) re-issues the cookie when the token is expired or past half-TTL.

---

## 3. Login flow

### 3.1 Tenant login

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser
    participant C as AuthenticatedSessionController
    participant LR as LoginRequest
    participant G as web (jwt) guard

    B->>C: POST /login
    C->>LR: authenticate()
    LR->>LR: ensureIsNotRateLimited()  (5/min per email+IP)
    LR->>G: attempt(email, password, is_active=true)
    alt valid
        G-->>LR: token
        LR-->>C: token
        C->>C: session()->regenerate()
        C-->>B: redirect(HOME) + JWT cookie
    else invalid
        LR-->>B: 422 "auth.failed"
    end
```

- [`AuthenticatedSessionController::store()`](../../app/Http/Controllers/Auth/AuthenticatedSessionController.php) `:54`.
- [`LoginRequest::authenticate()`](../../app/Http/Requests/Auth/LoginRequest.php) `:51`
  — throttles to **5 attempts / minute** keyed on `email|ip`,
  [`ensureIsNotRateLimited()`](../../app/Http/Requests/Auth/LoginRequest.php) `:75`.

#### The central `/login` is a workspace finder

The tenant route above can only answer on a tenant host — tenancy cannot
identify a central domain. The marketing site therefore registers a
domain-constrained twin of the same URI per central domain (`routes/web.php`),
which renders `Auth/FindWorkspace`. `route('login')` emits the bare path
`/login`, so the same expression resolves to the real tenant login on a tenant
host and to the finder on a central host — which is what makes the marketing
Nav and FinalCta links work on both.

The finder posts to `POST /find-workspace`, handled by
[`WorkspaceLookupController::__invoke()`](../../app/Tenants/Http/Controllers/Public/WorkspaceLookupController.php) `:44`.
The address is reduced to ordered candidates by
[`WorkspaceAddress::candidates()`](../../app/Tenants/Support/WorkspaceAddress.php) `:25`:
scheme, credentials, path, query, port and trailing dot are stripped, the value
is lowercased, a bare label gains `tenancy.tenant_domain_suffix`, and a leading
`www.` is retried without it. The first candidate that exists in `domains` wins
and the browser is sent to **that** host's login via
[`TenantVerificationUrl::loginFor()`](../../app/Tenants/Support/TenantVerificationUrl.php) `:98`,
so a visitor who typed a custom domain stays on it.

Invariants:

- The redirect target is always a `domains` row, never the raw input — no open
  redirect. Central domains are rejected before the lookup.
- Unknown input returns a **422** on `workspace` (inline in the finder), with
  one message for "unknown" and "not an address" so the endpoint does not
  answer whether a workspace exists more precisely than DNS already does.
- The route is throttled to **10 lookups/minute** and only runs on submit; a
  lookup on every keystroke would be a free enumeration API.
- Soft-deleted tenants deliberately resolve: their own host renders the
  deleted-account page from `tenant.state`.
- The endpoint is central-only (`central.domain`), so the URI stays free on
  tenant hosts.

### 3.2 Admin login

Same shape via
[`AdminAuthController::login()`](../../app/Shared/Http/Controllers/Admin/Auth/AdminAuthController.php) `:57`
and
[`AdminLoginRequest::authenticate()`](../../app/Http/Requests/Admin/AdminLoginRequest.php) `:36`,
but on the `admin` guard. One difference: a blocked account
(`is_active = false`) gets a distinct message rather than the generic
`auth.failed`, so support can tell the two apart — while an unknown email still
returns the generic message.

`AdminAuthController::user()` `:98` returns the admin plus the **flattened**
permission set (role permissions ∪ direct permissions), which the SPA uses to
render the menu. The login page itself is the one Blade view, `admin.login`
(`showLogin()` `:33`).

### 3.3 Tenant owner email confirmation (signup)

Login does **not** require a verified email: an unverified owner must be able to
sign in to reach the resend button. Instead, the `verified` middleware holds them
at `/verify-email` for everything below the verification routes.

The signed link itself is **self-contained**:
[`VerifyEmailController`](../../app/Http/Controllers/Auth/VerifyEmailController.php)
sits outside the `auth` group, looks the owner up by the `id` in the URL and
validates the signature in the controller. Mail clients open the link in whatever
browser is handy, so requiring a session pushed the owner through the login form
before the address was confirmed. The route still runs inside the tenant group,
so the lookup hits the tenant database; a signed link whose `hash` does not match
`sha1(email)` is refused.

The controller redirects to the signed central waiting screen `GET
/register/pending`, which renders `Auth/TenantRegisterSuccess` in its pending
state. That screen names the address, keeps the workspace locked, and has no link
into the workspace. Two things flip it to the green check:

1. **Reverb** — when the owner clicks the link, the `Verified` listener
   [`BroadcastTenantEmailVerified`](../../app/Tenants/Listeners/BroadcastTenantEmailVerified.php)
   projects `owner_email_verified_at` onto the central `tenants` row and
   broadcasts `TenantEmailVerified` on the public HMAC channel
   `tenant-verification.<sha256>`
   ([`TenantVerificationChannel`](../../app/Tenants/Support/TenantVerificationChannel.php)).
   The open tab is already subscribed through Echo, so it flips immediately.
2. **Poll fallback** — the screen still polls its own `verified` prop every five
   seconds, so it also flips when Reverb is not running.

`tenants.owner_email_verified_at` is a **projection**: the tenant-side
`users.email_verified_at` row remains authoritative, and only the owner's address
is projected — a teammate confirming their own address must not unlock the
owner's workspace. Real-time requires `BROADCAST_DRIVER=reverb` and a running
`php artisan reverb:start`; `php artisan reverb:install` generates the
`REVERB_*`/`VITE_REVERB_*` credentials. Without either, the poll keeps the flow
working.

The verified state shows the confirmation and a full-width **Go to Login**
button pointing at the tenant `/login`; it no longer auto-redirects, so the
owner chooses when to leave the screen (visiting the link again re-renders the
same confirmed state).
`Auth/EmailVerificationExpired` returns a 403 when the link is past its 60-minute
window, was tampered with, or points at a deleted account, and routes the owner
to sign in and resend. The in-tenant gate page `Auth/VerifyEmail` shows the
address the link went to, states that the workspace stays locked until it is
confirmed, and has no link into the workspace; resend and logout remain.

The pending screen and its resend action are **signed central URLs**
(`register.pending`, `register.pending.resend`, valid 24 hours): the owner has no
session yet, so the signature is the credential. Signup and paid checkout
redirect to the pending URL and start a server-side 60-second cooldown keyed by
tenant and normalised email. The screen's **Resend email** button posts the
signed resend URL and is disabled while that cooldown runs, so a reload, a second
tab, or a hand-crafted POST cannot send a stream of mails. The resend reuses
[`TenantVerificationMailer::send()`](../../app/Tenants/Support/TenantVerificationMailer.php),
so the initial mail and the resend share one template and one tenant-rooted
signed link.

Locally, the mail reaches Laragon's Mailpit when `.env` sets
`MAIL_MAILER=smtp` and `MAIL_HOST=127.0.0.1` (SMTP on port 1025, UI on 8025).
The signed links are rooted at `<slug>.sasapp`, so the Laragon Apache vhost must
also alias `*.sasapp`; otherwise the button falls through to the default docroot.

The central `/register` flow provisions the tenant and creates the owner through
[`CreateTenantAdminUser::execute()`](../../app/Tenants/Jobs/CreateTenantAdminUser.php) `:46`,
which fires `Registered` from inside `tenancy()->initialize()`. For an
email/password signup the owner is kept **unverified** (`email_verified_at =
null`), so the first dashboard request redirects to `verification.notice` until
the signed link is clicked. A Google signup arrives with the address already
confirmed — see §3.4.

The link must be rooted at the tenant domain: the provisioning job runs on a queue
worker with no tenant request, where `URL` would otherwise sign the central
`APP_URL`. [`TenantVerificationUrl::for()`](../../app/Tenants/Support/TenantVerificationUrl.php) `:34`
forces the URL root and scheme to the tenant's primary domain and restores both in
a `finally` (URL state is process-global and leaks into later jobs on a
long-lived worker). [`SendTenantVerificationEmail::handle()`](../../app/Tenants/Listeners/SendTenantVerificationEmail.php) `:23`
replaces the framework listener, so the in-tenant self-service signup shares the
same branded mail; send failures are logged and never fail provisioning.

### 3.4 Google OAuth (tenant login, joining, and new-workspace signup)

Google runs end to end on a **central** host, because Google only accepts an
exact redirect URI: a per-tenant callback (`<slug>.sasapp/...`) cannot be
registered (wildcards are rejected, and plain `http` is refused outside
loopback). The same two endpoints serve three entry kinds:

| Entry | Entry link | Session marker | May create a tenant user? |
|---|---|---|---|
| Tenant login | tenant `/login` → `?tenant=…&intent=login` | `oauth.tenant`, `oauth.intent=login` | **Never** — an unknown Google email is refused |
| Tenant join | tenant `/register` → `?tenant=…&intent=register` | `oauth.tenant`, `oauth.intent=register` | Yes, within the plan's user quota |
| New workspace | central `/register` → `?flow=signup` | `oauth.flow=signup` | No tenant is provisioned during OAuth at all |

The tenant id — and the fact that *this browser* started the flow — are carried
in the central session, never in the callback query string.
`GOOGLE_REDIRECT_URI` must be on the same host as `APP_URL`: the session cookie
is host-scoped, so an entry link minted for `127.0.0.1` that returns to
`localhost` would lose the tenant and end in the "session expired" path.

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser
    participant T as Tenant host (slug.sasapp)
    participant C as Central host
    participant G as Google

    B->>T: GET /login (or /register)
    T-->>B: providers[] → central /auth/google/redirect?tenant=…&intent=…
    B->>C: GET /auth/google/redirect  (session: oauth.tenant, oauth.intent)
    C-->>B: 302 accounts.google.com (prompt=select_account)
    B->>G: consent
    G-->>B: 302 /auth/google/callback?code=…
    B->>C: GET /auth/google/callback
    C->>C: exchange code, resolve/link the user in the tenant DB
    C-->>B: 302 <slug>.sasapp/auth/google/complete?signature=… (60 s)
    B->>T: GET /auth/google/complete  (signed)
    T-->>B: 302 /dashboard + JWT cookie
```

- Entry point and callback:
  [`GoogleOAuthController::redirect()`](../../app/Http/Controllers/Auth/GoogleOAuthController.php) `:67`
  and [`GoogleOAuthController::callback()`](../../app/Http/Controllers/Auth/GoogleOAuthController.php) `:96`.
  Registered once as `oauth.google.redirect` / `oauth.google.callback` behind
  `central.domain` — `routes/web.php` :148. Deliberately **no `guest`**: these
  are central routes with no tenant session to protect, and `guest` would bounce
  a visitor who is already signed in instead of letting them switch accounts.
- Tenant end:
  [`GoogleOAuthCompleteController::__invoke()`](../../app/Http/Controllers/Auth/GoogleOAuthCompleteController.php) `:31`,
  route `auth.google.complete` with `signed` and `throttle:10,1`, outside both
  `auth` and `guest` — `routes/tenant.php` :112.
- Account resolution:
  [`OAuthAccountLinker::loginOrRegister()`](../../app/Shared/Support/OAuthAccountLinker.php) `:41`.
- Handoff URL:
  [`OAuthHandoffUrl::for()`](../../app/Tenants/Support/OAuthHandoffUrl.php) `:34`
  (`EXPIRY_SECONDS = 60`).
- The tenant login footer links to the **central** `/register` through
  [`CentralSignupUrl::for()`](../../app/Shared/Support/CentralSignupUrl.php) `:20`,
  because a plain `route('register.tenant')` rendered on a tenant host resolves
  to *that tenant's* `/register` (join the existing workspace), not the central
  page that creates a new one.

**Linking rules** (one `oauth_accounts` row per provider identity):

1. A known `provider` + `provider_user_id` signs in directly. Email is never
   consulted on this path, so an address change on either side cannot hijack the
   link.
2. An unknown identity may link to an existing local account **only when Google
   reports `email_verified`**. Linking an unverified provider email would let
   anyone who can create an unverified Google account for `victim@example.com`
   take over the victim's local account.
3. No local account — behaviour depends on intent:
   - **login** refuses with `no_account`. The login button must not silently add
     a member to a workspace the visitor was never invited to; joining goes
     through that workspace's own `/register`.
   - **register** creates the user with `password = NULL`,
     `email_verified_at = now()` and `is_active = true`, subject to the plan's
     `max_users` quota (same `PlanLimitExceededException` as
     `StoreUserRequest`). `Registered` is **not** dispatched: its only listener
     sends the verification mail that Google has already made redundant.
   - the **central signup** flow never reaches the linker; it parks the identity
     and lets the normal `POST /register` provision a brand-new workspace.

Refusal order is `inactive` → `unverified` → `no_account`: an unverified
identity that also has no account still reports `unverified`, because the
untrusted address is the reason it cannot be used.

Soft-deleted accounts are never resurrected — the Eloquent global scope keeps the
email lookup inside `deleted_at IS NULL`, matching the partial unique index.
Inactive accounts are refused on both paths, and every refusal returns to the
tenant host with a **signed** `?oauth_error=<code>` because a cross-host session
flash is unreadable on the other host
([`OAuthCallbackError`](../../app/Shared/Support/OAuthCallbackError.php) owns the
codes and the copy: `cancelled`, `failed`, `expired`, `inactive`, `unverified`,
`user_limit`, `no_account`, `account_exists`).

**Google-first workspace signup.** The central `/register` button points at the
same redirect with `flow=signup`; `redirect()` forgets the tenant keys and stores
`oauth.flow=signup`. After consent,
[`GoogleOAuthController::signupCallback()`](../../app/Http/Controllers/Auth/GoogleOAuthController.php) `:175`
stays on the central host and:

1. refuses a cancelled or failed consent (`oauth_error=cancelled|failed`);
2. requires `email_verified` (`oauth_error=unverified`), since the form will
   force this address on submit;
3. refuses an address that already owns a workspace
   (`oauth_error=account_exists`), so a returning owner is sent to log in rather
   than into a second, empty workspace;
4. parks the verified name and lowercased address in the central session through
   [`PendingOAuthSignup::remember()`](../../app/Tenants/Support/PendingOAuthSignup.php) `:31`
   (30-minute TTL) and redirects to `/register?oauth=1`.

The callback never provisions a tenant and never charges a card: the normal
`POST /register` remains the single provisioning choke point (including the paid
checkout), so an abandoned consent cannot create or bill a workspace.

The plan step then shows the identity and asks only for what Google does not
supply — plan, company name, workspace name, workspace address, terms. On
submit, `StoreTenantRegistrationRequest::prepareForValidation()` forces the
session address over the posted payload, the password rules become `nullable`
and `TenantRegistrationController::store()` persists `password = null`. The
queued [`CreateTenantAdminUser::execute()`](../../app/Tenants/Jobs/CreateTenantAdminUser.php) `:46`
therefore creates a **passwordless, pre-verified** owner: `users.email_verified_at`
and the central `tenants.owner_email_verified_at` projection are written, and
`Registered` is skipped because Google already confirmed the address. The
verification screen is bypassed for this owner.

A paid selection carries the Google profile name in the pending signup
(`admin_name`) together with the `NULL` password. The name must travel with the
pending payload because the 30-minute session identity can expire while the
guest is at the hosted checkout; `RegistrationCheckoutController` passes that
name and hash straight to the job.

**Use email instead** posts `register.oauth.forget`, which drops the parked
identity so the same form can be completed with an email and password. There is
no `sessionStorage` draft to restore: Google is clicked before anything has been
typed.

Because both ends of the signup flow are on the central host, its errors are
plain same-host `?oauth_error=` values resolved with
[`OAuthCallbackError::fromCode()`](../../app/Shared/Support/OAuthCallbackError.php) `:69`
— no signature is needed, and the fixed map means an unknown code renders
nothing. The tenant flow keeps its **signed** `?oauth_error=` handoff, since a
flash cannot cross hosts.

`users.password` is nullable for these accounts
(`2026_11_14_000002_make_users_password_nullable`). A NULL hash is not a bypass:
`BcryptHasher::check()` returns false for it, and the user can adopt a password
later through the profile / password-reset flows. The JWT is minted only on the
tenant host, so it never appears in a URL, and no provider access or refresh
tokens are stored.

Socialite does not implement the OAuth `state` parameter. The central session
binding (the tenant must be in *this* browser's session) plus
`prompt=select_account` — which forces the account chooser instead of a silent
SSO round trip — are what stand in for it; a login-CSRF would otherwise need the
victim to consent to the attacker's Google account unprompted.

---

## 4. Token refresh & expiry

```mermaid
flowchart TD
    A[request] --> B{Authorization header?}
    B -- no --> Z[continue as guest]
    B -- yes --> S{session anchors fresh?}
    S -- "no: idle > 30 min or age > 12 h" --> X[blacklist JWT + clear cookie/session → sign in]
    S -- yes --> C{token expired?}
    C -- yes --> R[refresh + re-issue cookie]
    C -- no --> D{past half TTL?}
    D -- yes --> R
    D -- no --> Z
    R --> Z
```

[`EnsureSessionNotExpired`](../../app/Shared/Middleware/EnsureSessionNotExpired.php)
(alias `session.expiry`) enforces the two server-side clocks **before**
[`RefreshJwtToken`](../../app/Shared/Middleware/RefreshJwtToken.php) runs, so an
idle or absolute expiry can never be rescued by a token refresh:

| Clock | Config | Anchor | Behaviour |
|-------|--------|--------|-----------|
| Idle | `session.idle_lifetime` (`SESSION_IDLE_LIFETIME`, 30 min) | `last_activity`, rewritten on every authenticated request | signed out after N minutes without a request |
| Absolute | `session.absolute_lifetime` (`SESSION_ABSOLUTE_LIFETIME`, 720 min) | `auth_started_at`, written once at login | signed out N minutes after login, even with constant activity |

Both anchors live in the Laravel session, not in the JWT
([`AuthSession`](../../app/Shared/Support/AuthSession.php)), so they survive
token refreshes but end with the session. A request that carries a token but no
anchors is treated as **expired**: an expired session cookie must not be
re-established by a still-refreshable JWT. The cost is one forced sign-in for
sessions that predate the anchors, which is intended.

Expiry blacklists the JWT, invalidates the session (regenerating the CSRF
token), expires the JWT cookie, and returns a 401 (JSON) or a guard-aware
redirect — `central.login` for `admin`, `login` otherwise. Impersonation is
skipped; god mode is bounded by `impersonation.ttl` instead. The transport
cookie's max-age is capped at `min(jwt.refresh_ttl, session.absolute_lifetime)`
in [`JwtCookie`](../../app/Shared/Support/JwtCookie.php).

`RefreshJwtToken` still refreshes a token that expired inside
`jwt.refresh_ttl`, but only for a session that passes both clocks. The tenant
claim `ten` is in `persistent_claims` (`config/jwt.php:193`), so it survives a
refresh and
[`ValidateTenantToken`](../../app/Shared/Middleware/ValidateTenantToken.php)
(`jwt.tenant`) can still reject a token that does not belong to the current
tenant.

---

## 5. Middleware

| Alias | Class | Role |
|-------|-------|------|
| `jwt.cookie` | `AuthenticateJwtFromCookie` | cookie → bearer header |
| `jwt.refresh[:guard]` | `RefreshJwtToken` | sliding-window renewal |
| `auth` / `auth:admin` | `App\Http\Middleware\Authenticate` | guard check (401 for JSON/`/admin/*`) |
| `session.expiry[:guard]` | `EnsureSessionNotExpired` | idle + absolute clocks, blacklist + guard-aware logout |
| `guest` | `RedirectIfAuthenticated` | keep authenticated users off guest pages |

---

## 6. Key decisions & invariants

- **Per-guard secrets are the isolation boundary** — never share a secret across
  guards in production.
- **Cookie is transport, bearer is the interface.** No middleware needs to know
  about cookies; `jwt.cookie` normalises.
- **Refresh keeps `ten`,** so tenant scoping survives renewal.
- **Rate limit by `email|ip`,** not by IP alone, so one attacker cannot lock out
  every account from a shared NAT.
- **Idle and absolute clocks are separate from TTL,** so a long-lived refresh
  window still cannot extend a session indefinitely.

---

## 7. Cross-references

- [`01-request-lifecycle.md`](01-request-lifecycle.md) — where auth middleware runs.
- [`05-permissions-and-plan-gating.md`](05-permissions-and-plan-gating.md) — what the authenticated user may do.
- [`06-impersonation-god-mode.md`](06-impersonation-god-mode.md) — how an admin becomes a tenant user.
- [`03-multi-tenancy.md`](03-multi-tenancy.md) — the `ten` claim and tenant scoping.
- Source: [`config/auth.php`](../../config/auth.php), [`config/jwt.php`](../../config/jwt.php), [`JwtServiceProvider`](../../app/Shared/Providers/JwtServiceProvider.php), [`JwtCookie`](../../app/Shared/Support/JwtCookie.php).
