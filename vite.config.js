import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                // Two entries on purpose: app.ts for the Blade half, and
                // inertia-app.ts for the Vue half. One entry for both put Vue
                // into the bundle the public pages download.
                'resources/js/app.ts',
                'resources/js/inertia-app.ts',
            ],
            refresh: true,
        }),
        vue(),
    ],
});
