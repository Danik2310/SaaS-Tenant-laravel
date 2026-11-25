import { expect, test } from '@playwright/test';

const email = process.env.ADMIN_EMAIL;
const password = process.env.ADMIN_PASSWORD;

test('authenticate admin and save storage state', async ({ page }) => {
    if (!email || !password) {
        throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env for admin E2E setup.');
    }

    await page.goto('/central/login');
    await expect(page.getByRole('heading', { name: 'Admin', level: 1 })).toBeVisible();

    // MUI marks required fields with an asterisk, so match the label prefix.
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Password/).fill(password);
    await page.getByRole('button', { name: 'Log In' }).click();

    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.getByRole('heading', { name: 'Dashboard Overview', level: 1 })).toBeVisible();

    await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
});
