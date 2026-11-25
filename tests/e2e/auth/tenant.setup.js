import { expect, test } from '@playwright/test';

const baseURL = process.env.E2E_TENANT_URL
    ?? `http://${process.env.E2E_TENANT_SUBDOMAIN ?? 'empresaabc'}.${process.env.TENANT_DOMAIN_SUFFIX ?? 'sasapp'}`;
const email = process.env.E2E_TENANT_EMAIL ?? process.env.TENANT_EMAIL ?? 'contacto@empresaabc.com';
const password = process.env.TENANT_PASSWORD;

test.use({ baseURL });

test('authenticate tenant user and save storage state', async ({ page }) => {
    if (!password) {
        throw new Error('TENANT_PASSWORD must be set in .env for tenant E2E setup.');
    }

    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Welcome back', level: 1 })).toBeVisible();

    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/dashboard/);
    await page.context().storageState({ path: 'tests/e2e/.auth/tenant.json' });
});
