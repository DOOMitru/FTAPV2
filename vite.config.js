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
    build: {
        /*
         * Lightning CSS (Vite 8) writes media queries in range syntax,
         * (width<=48rem), for any target that supports it. Safari before
         * iOS 16.4 does not, and IGNORES such a query -- every phone-width
         * rule vanished there and the desktop layout was squeezed onto the
         * screen. iPhone 6s, 7 and the first SE cannot update past iOS 15.
         *
         * safari15 brings back (max-width:48rem). The app's real floor is
         * iOS 15.4, for :has(), so this targets the gap and nothing below it.
         * CSS only: the JS target is untouched.
         */
        cssTarget: ['chrome107', 'edge107', 'firefox104', 'safari15'],
    },
});
