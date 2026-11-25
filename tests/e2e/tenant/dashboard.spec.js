import { expect, test } from '@playwright/test';

test('tenant dashboard loads for an authenticated user', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await expect(page.getByText('Active Products')).toBeVisible();
    await expect(page.getByText('Recent Orders')).toBeVisible();
});
