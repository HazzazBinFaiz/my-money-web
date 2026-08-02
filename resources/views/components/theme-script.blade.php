{{-- Applies the stored theme before first paint so there is no flash. --}}
<script>
    (function () {
        const stored = localStorage.getItem('theme') || 'system';
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

        const apply = (theme) => {
            const dark = theme === 'dark' || (theme === 'system' && prefersDark.matches);
            document.documentElement.classList.toggle('dark', dark);
        };

        apply(stored);

        prefersDark.addEventListener('change', () => {
            if ((localStorage.getItem('theme') || 'system') === 'system') {
                apply('system');
            }
        });

        window.setTheme = (theme) => {
            localStorage.setItem('theme', theme);
            apply(theme);
        };
    })();
</script>
