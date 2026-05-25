import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                'resources/js/landlord/app.jsx',
            ],
            refresh: true,
        }),
        visualizer({
            filename: 'build-stats.html',
            open: false,
            gzipSize: true,
            brotliSize: true,
        }),
    ],
    server: {
        port: 5174,
        strictPort: true,
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-react':  ['react', 'react-dom', '@inertiajs/react'],
                    'vendor-mui':    ['@mui/material', '@mui/icons-material'],
                    'vendor-utils':  ['axios'],
                },
            },
        },
    },
});
