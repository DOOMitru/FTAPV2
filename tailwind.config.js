import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['selector', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Aligned with --font-body (self-hosted Archivo, see
                // resources/css/1-base/_typography.css). Unconverted views
                // still carry Tailwind's font-sans class during the
                // transition, so it must resolve to the same face as the
                // design system's body rule. This whole file is removed in
                // Phase 5 once Tailwind is fully replaced.
                sans: ['Archivo', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
