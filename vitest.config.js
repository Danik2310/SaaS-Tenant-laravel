import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        setupFiles: ['./tests/setup.js'],
        globals: true,
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
            '@': '/resources/js',
        },
    },
});
