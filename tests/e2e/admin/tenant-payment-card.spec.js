import { expect, test } from '@playwright/test';

const cardTenant = {
    id: 'tenant-card-1',
    reference_id: 'TEN-20261125-0001',
    name: 'Card Tenant',
    email: 'card-tenant@example.test',
    domain: 'card-tenant.test',
    status: 'Active',
    plan_name: 'Pro',
    subscription_ends_at: null,
    is_deleted: false,
    created_at: '2026-11-01T00:00:00Z',
    payment_card: {
        linked: true,
        brand: 'Visa',
        last4: '4242',
        exp_month: 12,
        exp_year: 2029,
        card_count: 2,
        synced_at: '2026-11-25T10:00:00Z',
    },
};

const unlinkedTenant = {
    id: 'tenant-card-2',
    reference_id: 'TEN-20261125-0002',
    name: 'Unlinked Tenant',
    email: 'unlinked-tenant@example.test',
    domain: 'unlinked-tenant.test',
    status: 'Trial',
    plan_name: 'Starter',
    subscription_ends_at: null,
    is_deleted: false,
    created_at: '2026-11-02T00:00:00Z',
    payment_card: {
        linked: false,
        brand: null,
        last4: null,
        exp_month: null,
        exp_year: null,
        card_count: 0,
        synced_at: null,
    },
};

test('tenant registry shows masked cards and exposes refresh/portal actions', async ({ page }) => {
    let refreshCalls = 0;
    let portalCalls = 0;

    await page.route('**/admin/api/tenants?**', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ tenants: [cardTenant, unlinkedTenant], total: 2 }),
        });
    });

    await page.route(`**/admin/api/tenants/${cardTenant.id}/payment-methods/refresh`, async (route) => {
        refreshCalls += 1;
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                message: 'Payment card refreshed.',
                payment_card: { ...cardTenant.payment_card, brand: 'Mastercard', last4: '5555', card_count: 1 },
            }),
        });
    });

    await page.route(`**/admin/api/tenants/${cardTenant.id}/billing-portal`, async (route) => {
        portalCalls += 1;
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ message: 'Stripe Customer Portal session created.', url: '/admin/dashboard' }),
        });
    });

    await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Dashboard Overview', level: 1 })).toBeVisible();

    await page.getByTestId('nav-tenants').first().click();
    await expect(page.getByRole('heading', { name: 'Tenant Management', level: 1 })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Payment card/i })).toBeVisible();

    const cards = page.getByTestId('tenant-payment-card');
    await expect(cards).toHaveCount(2);
    await expect(cards.nth(0)).toContainText('Visa');
    await expect(cards.nth(0)).toContainText('4242');
    await expect(cards.nth(0)).toContainText('+1 more');
    await expect(cards.nth(1)).toContainText('Not linked');

    const row = page.getByRole('row').filter({ hasText: cardTenant.name });
    await expect(row.getByTestId('tenant-card-refresh')).toBeEnabled();
    await row.getByTestId('tenant-card-refresh').click();
    await expect(page.getByText('Payment card refreshed.')).toBeVisible();
    expect(refreshCalls).toBe(1);

    await expect(row.getByTestId('tenant-card-manage')).toBeEnabled();
    await row.getByTestId('tenant-card-manage').click();
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    expect(portalCalls).toBe(1);
});
