import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        setupFiles: ['./tests/setup.js'],
        globals: true,
        // 'threads' starts workers far faster than the default 'forks' pool.
        pool: 'threads',
        // Reuse a worker's module registry/environment across test files. The
        // per-file jsdom boot + setupFile run + re-import of MUI/Emotion is the
        // bulk of the suite's wall time, and our tests clean up after
        // themselves (tests/setup.js runs RTL cleanup in afterEach). Revert to
        // the default (isolate: true) if cross-file state ever leaks.
        isolate: false,
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
                        'recharts',
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
