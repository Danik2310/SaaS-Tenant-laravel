import { expect, test } from '@playwright/test';

test('admin login screen renders its form', async ({ page }) => {
    await page.goto('/central/login');

    await expect(page.getByRole('heading', { name: 'Admin', level: 1 })).toBeVisible();
    await expect(page.getByLabel(/^Email/)).toBeVisible();
    await expect(page.getByLabel(/^Password/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log In' })).toBeVisible();
});

test('admin login rejects invalid credentials', async ({ page }) => {
    await page.goto('/central/login');

    await page.getByLabel(/^Email/).fill('nobody@example.invalid');
    await page.getByLabel(/^Password/).fill('definitely-wrong');
    await page.getByRole('button', { name: 'Log In' }).click();

    await expect(page.getByRole('alert')).toBeVisible();
});
