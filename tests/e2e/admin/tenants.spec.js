import { expect, test } from '@playwright/test';

test('tenant management loads for an authenticated admin', async ({ page }) => {
    await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'Dashboard Overview', level: 1 })).toBeVisible();

    // Navbar renders a desktop aside and a keep-mounted mobile drawer.
    await page.getByTestId('nav-tenants').first().click();

    await expect(page.getByRole('heading', { name: 'Tenant Management', level: 1 })).toBeVisible();
});
