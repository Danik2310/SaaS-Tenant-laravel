import { defineConfig, defaultAllowedOrigins } from 'vite';
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
        // Vite 6.4 defaults to loopback-only CORS (`defaultAllowedOrigins`).
        // The app is served from `<tenant>.sasapp:8000` while its module
        // scripts come from `127.0.0.1:5174`, so the tenant host needs an
        // explicit Access-Control-Allow-Origin or the browser drops app.jsx
        // and every page renders blank. Keep Vite's loopback list, add tenants.
        cors: {
            origin: [defaultAllowedOrigins, /^https?:\/\/(?:[a-z0-9-]+\.)*sasapp(:\d+)?$/i],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-react':  ['react', 'react-dom', '@inertiajs/react'],
                    'vendor-mui':    ['@mui/material', '@mui/icons-material'],
                    'vendor-mrt':    ['material-react-table'],
                    'vendor-charts': ['chart.js', 'react-chartjs-2'],
                    'vendor-utils':  ['axios'],
                },
            },
        },
    },
});
