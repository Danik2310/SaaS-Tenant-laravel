# Billing settings and Stripe card management

## Scope

Covers the tenant-facing Billing settings page, the Stripe Customer Portal
handoff, the central webhook that sends card-change confirmation emails and
refreshes the admin registry's masked card projection, and the Cashier-backed
sync of Stripe subscription state into the local plan projection. It does
**not** cover plan checkout, subscription invoices, or the admin gateway/API-key
configuration screen.

## Flow

```mermaid
sequenceDiagram
    actor User as Tenant user (manage payments)
    participant UI as Tenant/Settings/Billing
    participant Controller as BillingSettingsController
    participant Gateway as PaymentGatewayInterface (Stripe)
    participant Stripe as Stripe Customer Portal
    participant Webhook as StripeWebhookController
    participant Cashier as Cashier webhook adapter
    participant Projector as LocalSubscriptionProjector
    participant Cards as TenantPaymentCardProjector
    participant Registry as Central tenants row
    participant Admin as Admin tenant registry
    participant Mail as PaymentMethodChanged

    User->>UI: Open Settings -> Billing
    UI->>Controller: GET /settings/billing
    Controller->>Gateway: listPaymentMethods(stripe_customer_id)
    Gateway-->>Controller: Masked card metadata only
    Controller-->>UI: Billing profile + masked cards

    User->>UI: Click "Manage cards in Stripe"
    UI->>Controller: POST /settings/billing/portal
    Controller->>Gateway: createCustomer (first use) + createCustomerPortalSession
    Gateway-->>Controller: Hosted portal URL
    Controller-->>UI: Inertia location redirect to Stripe

    Stripe->>Webhook: POST /api/v1/webhooks/stripe (signed)
    Webhook->>Gateway: verifyWebhookEvent(payload, signature)
    Webhook->>Webhook: Record event id (idempotency)
    Webhook->>Cards: payment_method.* / customer.updated
    Cards->>Gateway: listPaymentMethods(customer id)
    Gateway-->>Cards: Masked card metadata only
    Cards->>Registry: Upsert pm_* projection
    Webhook->>Mail: Notify billing email + owner email (card events)
    Webhook->>Cashier: customer.subscription.* payload
    Cashier->>Cashier: Cashier-owned subscription tables
    Webhook->>Projector: Same event after Cashier sync
    Projector->>Projector: Upsert local plan projection

    Admin->>Registry: GET /admin/api/tenants (no Stripe call per row)
    Admin->>Cards: POST refresh (on demand)
    Admin->>Gateway: POST billing-portal (hosted handoff)
```

## Step-by-step

1. The Billing card appears in the settings hub only for users holding the
   `manage payments` permission; the same permission guards every billing
   route. No new permission was introduced.
2. Billing details are saved on the central `tenants` row. The personal profile
   form still writes to the tenant database user row, so the owner's login
   email is never changed by a billing edit.
3. When a billing email change is saved for a tenant that already has a Stripe
   customer, the adapter updates the Stripe customer email. A failed Stripe
   update returns an error and does not persist the local change.
4. On the first portal visit, the app creates a Stripe customer with tenant
   metadata and stores only its id in `tenants.stripe_customer_id`. No card
   number, expiry, or CVC is ever collected or stored by the app.
5. The portal session URL is short-lived and Stripe-hosted. The return URL is
   generated per request, so it carries the current tenant host.
6. Stripe posts signed `payment_method.attached`, `payment_method.detached`,
   and `customer.updated` events to the central webhook. `customer.updated`
   only syncs and notifies when the default payment method actually changes.
7. The webhook verifies the signature, records the Stripe event id in
   `stripe_webhook_events`, and resolves the tenant by
   `stripe_customer_id`. Retried deliveries are acknowledged without a second
   projection or notification.
8. `TenantPaymentCardProjector` writes `pm_type`, `pm_last_four`,
   `pm_exp_month`, `pm_exp_year`, `pm_card_count`, and `pm_synced_at` on the
   central tenant row. `payment_method.attached`/`detached` apply the signed
   event's masked payload without a gateway call; `customer.updated` recomputes
   the full card list from Stripe. Both paths are idempotent.
9. One on-demand `PaymentMethodChanged` notification is routed to both the
   billing email and the owner email. It reuses the freshly projected masked
   card metadata, so no second gateway call is made.
10. Subscription events are passed to Cashier's own webhook handler first, then
    to `LocalSubscriptionProjector`. If either step throws, the ledger row is
    deleted and the webhook returns 500 so Stripe can retry.
11. Card projection and notification failures follow the same retry contract:
    the ledger row is deleted and the endpoint returns 500, so Stripe redelivers
    instead of leaving a stale registry summary.

## Cashier Phase 1 — foundation (expand-only)

Laravel Cashier is installed but the request path above is unchanged in this
phase. The goal is compatibility: app code still reads and writes
`tenants.stripe_customer_id`, while Cashier-specific models and columns are
introduced without dropping or renaming anything yet.

- `config/cashier.php` maps Cashier's `key`/`secret` to the app's existing
  `STRIPE_PUBLISHABLE_KEY` / `STRIPE_SECRET_KEY` env names, with Cashier's
  upstream defaults as fallbacks. The webhook secret and endpoint remain the
  app's own.
- `BillingServiceProvider` registers `BillingCustomer` as the Cashier billable
  model, custom central `CashierSubscription` / `CashierSubscriptionItem`
  models, and `Cashier::ignoreRoutes()` so Cashier does not add a competing
  `/stripe/webhook` route.
- `BillingCustomer` maps the central `tenants` row. A separate billable view is
  required because `Tenant::subscriptions()` is the local plan/entitlement
  projection and Cashier's `Billable` trait defines a method with the same name.
- Phase 1 adds `tenants.stripe_id`, `pm_type`, `pm_last_four`, and
  `registration_trial_ends_at`; `stripe_id` is a case-sensitive backfill of
  `stripe_customer_id`. Model hooks on both `Tenant` and `BillingCustomer`
  mirror Stripe-id writes in either direction. The legacy column is still
  authoritative until the contract migration in a later phase.
- Stripe subscription state lives in `cashier_subscriptions` and
  `cashier_subscription_items` on the central connection. They are separate
  from the existing `subscriptions` table, which remains the local projection.
- Cashier registration is additive: no new permission, no raw card data, and no
  user-visible flow changes. `CashierIntegrationTest` plus the existing billing,
  webhook, model-connection, and up/down migration tests guard this phase.

## Cashier Phase 2 — subscription webhook sync

The central webhook now handles the full Stripe event for
`customer.subscription.created`, `customer.subscription.updated`, and
`customer.subscription.deleted`:

- `CashierSubscriptionWebhookAdapter` feeds the verified payload into Cashier's
  own public webhook handler, so Cashier remains the owner of
  `cashier_subscriptions` and `cashier_subscription_items`. No package code is
  forked and no second webhook route is registered.
- `plans.stripe_price_id` maps a Stripe recurring price to the local plan.
  `subscriptions.stripe_subscription_id` keys the local projection row, so
  retries upsert one row instead of inserting duplicates.
- `LocalSubscriptionProjector` maps Stripe statuses to local ones:
  `active` → `active`, `trialing` → `trial`, `past_due`/`incomplete`/`paused` →
  `pending`, `canceled`/`unpaid` → `cancelled`, and `incomplete_expired` →
  `expired`. Active or trialing events update `tenants.plan_id`, flush the
  tenant cache through `TenantLifecycleInterface`, and dispatch `PlanChanged`.
- Cancellation marks the local subscription cancelled but deliberately leaves
  `tenants.plan_id` unchanged. Reverting entitlement is an explicit downgrade
  policy, not a silent side effect of a Stripe webhook.
- Unknown customers and unmapped prices are ignored with a warning; Cashier
  state may still be recorded, but no local entitlement is created.
- If Cashier or the projector throws, the webhook ledger row is deleted and the
  endpoint returns 500 so Stripe retries the delivery.
- Admin plan create/edit exposes `stripe_price_id`; the tenant Billing page
  shows a read-only subscription summary sourced from the local projection.

## Admin tenant registry card projection

The admin tenant registry displays a masked card summary without calling Stripe
for every row:

- `TenantPaymentCardProjector` applies the signed `payment_method.attached` /
  `detached` payload immediately, and recomputes the full list via
  `PaymentGatewayInterface::listPaymentMethods()` on `customer.updated` and on
  demand. It stores only `pm_type`, `pm_last_four`, `pm_exp_month`,
  `pm_exp_year`, `pm_card_count`, and `pm_synced_at` on the central tenant row.
- `TenantResource` exposes the summary as `payment_card`, including a `linked`
  flag so the UI can distinguish "no Stripe customer" from "no saved card".
  The tenant list reads this projection only — no Stripe call per row.
- `POST /admin/api/tenants/{tenant}/payment-methods/refresh` re-syncs one tenant
  if a webhook was missed or Stripe changed outside the portal.
- `POST /admin/api/tenants/{tenant}/billing-portal` creates a short-lived Stripe
  Customer Portal session and returns its URL. The admin browser is redirected
  to Stripe; the app never edits a card itself.
- Both endpoints reuse the existing `edit payment methods` admin permission and
  the `payment.rate.limit` middleware. Tenants must be active and already linked
  to a Stripe customer.
- This is separate from the admin **Payment methods** screen, which stores
  gateway/API credentials. The two concepts must not be merged.

## Stripe setup

1. Set `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` in the environment.
   `STRIPE_WEBHOOK_SECRET` is the signing secret of the endpoint below — not
   the API key.
2. In Stripe Dashboard → Developers → Webhooks, add an endpoint for
   `https://<central-domain>/api/v1/webhooks/stripe` and subscribe to:
   - `payment_method.attached`
   - `payment_method.detached`
   - `customer.updated`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
3. Enable the Stripe Customer Portal and allow the tenant domains as return
   destinations. The app supplies `return_url` when it creates each session;
   an allow-listed portal configuration is still required by Stripe.
4. Grant the existing `manage payments` permission to any tenant role that
   should see the Billing card and manage cards. Admin users need the existing
   `edit payment methods` permission to refresh the registry projection or open
   the Stripe portal for a tenant.
5. Allow the central admin domain as a Stripe portal return destination as well;
   the registry portal action returns to `/admin/dashboard?view=tenants`.

## Key decisions & invariants

- **Separation of concerns:** user profile data stays in the tenant database;
  company/billing/Stripe metadata stays on the central tenant row.
- **No raw PAN/CVC:** the UI only renders brand, last four digits and expiry
  returned by the gateway. Card entry happens exclusively in Stripe's portal.
- **Explicit sync:** Stripe customer contact details are updated only from the
  billing form, never as a side effect of a personal profile update.
- **Idempotency:** the webhook ledger is durable and central, because webhooks
  arrive outside tenant context.
- **Fail-soft rendering:** if Stripe is unavailable, the Billing page still
  renders the profile and shows a warning instead of a 500.

## Cross-references

- `docs/architecture/03-multi-tenancy.md` — central versus tenant data.
- `docs/architecture/05-permissions-and-plan-gating.md` — `manage payments`.
- `docs/architecture/09-notifications.md` — notification delivery conventions.
