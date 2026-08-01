{{--
    Applies the saved theme before first paint.

    This has to run inline in <head>, ahead of any stylesheet, otherwise the
    browser paints the light palette first and dark-mode users see a white
    flash on every navigation.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (stored === 'dark' || (stored !== 'light' && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {
            // Private browsing can block localStorage. Fall back to light.
        }
    })();
</script>
