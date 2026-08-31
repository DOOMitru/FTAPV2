{{--
    Runs before first paint to avoid a flash of the wrong theme. It only
    restores an explicit choice — with no stored choice the CSS falls through
    to prefers-color-scheme on its own.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {
            // Site data blocked. prefers-color-scheme still applies.
        }
    })();
</script>
