import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/spa/main.jsx',
                'resources/js/scalar.js',
            ],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        extensions: ['.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json'],
    },
    // LLM-11: strip console.* and debugger from production bundles so
    // full Axios error objects don't end up dumped in user devtools (or
    // screenshots / browser extensions). The standard pattern in
    // resources/js/spa/** is `console.error('… failed:', err)` where
    // `err.response.data` may carry sensitive upstream content.
    esbuild: {
        drop: mode === 'production' ? ['console', 'debugger'] : [],
    },
}));
