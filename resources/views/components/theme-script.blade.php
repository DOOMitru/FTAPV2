{{--
    Runs before first paint to avoid a flash of the wrong theme. Always stamps
    data-theme — from the stored choice if there is one, else the OS
    preference — because tailwind.config.js:6 keys `dark:` variants off
    `[data-theme="dark"]` only and knows nothing about prefers-color-scheme.
    An unstamped attribute let the tokens in _tokens.css (which do fall back
    to prefers-color-scheme on their own) go dark while Tailwind's `dark:`
    variants stayed off for anyone who never touched the toggle — see
    resources/js/theme.ts's currentTheme(), which this mirrors so the two
    can never disagree.
--}}
<script>
    (function () {
        var stored = null;

        try {
            stored = localStorage.getItem('theme');
        } catch (e) {
            // Site data blocked. Fall through to the system preference.
        }

        var theme = (stored === 'dark' || stored === 'light')
            ? stored
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
