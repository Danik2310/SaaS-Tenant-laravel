import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';

// Playwright does not load .env automatically. Keep an explicitly exported
// APP_URL, ignore the stale APP_URL in .env, and default to Laragon on :80.
const shellAppUrl = process.env.APP_URL;
dotenv.config();

process.env.APP_URL = process.env.E2E_BASE_URL ?? shellAppUrl ?? 'http://localhost';

const tenantSubdomain = process.env.E2E_TENANT_SUBDOMAIN ?? 'empresaabc';
const tenantDomainSuffix = process.env.TENANT_DOMAIN_SUFFIX ?? 'sasapp';
const tenantBaseURL = process.env.E2E_TENANT_URL ?? `http://${tenantSubdomain}.${tenantDomainSuffix}`;

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 90_000,
    expect: { timeout: 20_000 },
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'setup-admin',
            testDir: './tests/e2e/auth',
            testMatch: /admin\.setup\.js/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'setup-tenant',
            testDir: './tests/e2e/auth',
            testMatch: /tenant\.setup\.js/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'public',
            testDir: './tests/e2e/public',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'admin',
            testDir: './tests/e2e/admin',
            dependencies: ['setup-admin'],
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'tests/e2e/.auth/admin.json',
            },
        },
        {
            name: 'tenant',
            testDir: './tests/e2e/tenant',
            dependencies: ['setup-tenant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: tenantBaseURL,
                storageState: 'tests/e2e/.auth/tenant.json',
            },
        },
    ],
});
