import { nextPortalTheme, resolvePortalTheme } from './lib/theme';

import.meta.glob([
    '../images/**',
    '../fonts/**',
]);

function applyTheme(theme) {
    const isDark = theme === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
}

function toggleTheme() {
    const nextTheme = nextPortalTheme(
        document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
    );

    localStorage.setItem('portal-theme', nextTheme);
    applyTheme(nextTheme);
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(
        resolvePortalTheme(
            localStorage.getItem('portal-theme'),
            window.matchMedia('(prefers-color-scheme: dark)').matches,
        ),
    );

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', toggleTheme);
    });
});
