import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

/**
 * Mounts Vue, but only on a page Inertia actually rendered.
 *
 * This module is only reached from inertia-app.ts, which only the Inertia root
 * view loads, so in practice the element is always there. The check stays as a
 * cheap guard against a Blade layout being pointed at the wrong entry: that
 * mistake would otherwise surface as createInertiaApp throwing on a public
 * page, which is a confusing way to learn about a one-line typo.
 */
export function initInertia(): void {
    if (document.getElementById('app') === null) {
        return;
    }

    void createInertiaApp({
        resolve: (name: string) => {
            // Eager, not lazy. Lazy resolution returns a promise per page and
            // splits the bundle, which is the right default for a large app
            // and the wrong one here: this build ships a single file to a
            // league of 206 people, and a request per navigation on a phone at
            // a bar is a worse trade than a few kilobytes up front.
            const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
            const page = pages[`./Pages/${name}.vue`];

            if (page === undefined) {
                // Loud on purpose. A controller rendering a component that
                // does not exist otherwise mounts nothing and leaves a blank
                // page with no error anywhere.
                throw new Error(`Inertia page not found: ${name}`);
            }

            return page;
        },

        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el);
        },
    });
}
