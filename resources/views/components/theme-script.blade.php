{{--
    Runs before first paint to avoid a flash of the wrong theme. Always stamps
    data-theme — from the stored choice if there is one, else the OS
    preference. This mattered acutely while Tailwind was in the build: its
    `dark:` variants keyed off `[data-theme="dark"]` alone and knew nothing
    about prefers-color-scheme, so an unstamped attribute let the tokens in
    _tokens.css go dark while those variants stayed off. Tailwind is gone now,
    but stamping before paint is still what prevents a flash — see
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
