import { configDefaults, defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        setupFiles: ['./tests/setup.js'],
        globals: true,
        // Playwright specs live under tests/e2e and are run by Playwright, not
        // Vitest (Vitest's default include also matches **/*.spec.js).
        exclude: [...configDefaults.exclude, 'tests/e2e/**'],
        // Isolate each test file in its own module registry/environment.
        // `isolate: false` is markedly faster but leaked state between files
        // (e.g. tests/frontend/hooks/usePlan.test.js passed alone but failed
        // 9/14 in the full run), so it is deliberately left at the default.
        isolate: true,
        // Pre-bundle the heavy UI dependencies once per worker instead of
        // transforming them on every test file that imports them.
        deps: {
            optimizer: {
                client: {
                    include: [
                        '@mui/material',
                        '@mui/icons-material',
                        '@mui/x-date-pickers',
                        '@emotion/react',
                        '@emotion/styled',
                        'material-react-table',
                        'chart.js',
                        'react-chartjs-2',
                        'sonner',
                        '@inertiajs/react',
                        'dayjs',
                    ],
                },
            },
        },
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov'],
            include: ['resources/js/**/*.{jsx,js,tsx,ts}'],
            exclude: [
                'resources/js/**/*.test.*',
                'resources/js/**/*.spec.*',
                'resources/js/bootstrap.js',
                'resources/js/ziggy-js.js',
            ],
            reportsDirectory: './coverage',
        },
    },
    resolve: {
        alias: {
            '@': new URL('./resources/js', import.meta.url).pathname,
            'ziggy-js': new URL('./vendor/tightenco/ziggy', import.meta.url).pathname,
        },
    },
});
