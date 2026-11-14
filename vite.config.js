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
        vue({
            template: {
                /*
                 * Leave absolute asset URLs alone.
                 *
                 * Vite rewrites src/href in SFC templates into module imports
                 * and bundles the result. That is right for assets living in
                 * resources/, and wrong for this app: its images sit in
                 * public/ and are served by the webserver, which is exactly
                 * what Blade's asset() emits. Without this the build fails on
                 * "/images/header_logo.png" -- Rollup looks for a module and
                 * finds a file the webserver was always going to serve.
                 */
                transformAssetUrls: {
                    img: [],
                    image: [],
                    use: [],
                },
            },
        }),
    ],
});
