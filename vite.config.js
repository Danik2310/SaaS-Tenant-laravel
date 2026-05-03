import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                // main Inertia React entry (no app.js anymore, we use app.jsx)
                'resources/js/app.jsx',
                // include the admin bundle so it is built and watched
                'resources/js/landlord/app.jsx',
            ],
            refresh: true,
        }),
    ],
    server: {
        port: 5174,
        strictPort: true, // fail if the port is already taken so we notice conflicts
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: './tests/setup.js',
    },
});
