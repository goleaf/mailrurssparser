import * as api from '@/lib/api';

export let appState = $state({
    categories: [],
    trendingTags: [],
    breakingNews: [],
    initialized: false,
    darkMode: false,
    sidebarOpen: false,
});

let initPromise = null;

function prefersDarkMode() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function setAppearanceCookie(value) {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = 60 * 60 * 24 * 365;
    document.cookie = `appearance=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function applyDarkMode(isDark) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
}

function resolveDarkModePreference() {
    if (typeof window === 'undefined') {
        return false;
    }

    const storedDarkMode = localStorage.getItem('darkMode');

    if (storedDarkMode === 'true' || storedDarkMode === 'false') {
        return storedDarkMode === 'true';
    }

    const storedAppearance = localStorage.getItem('appearance');

    if (storedAppearance === 'dark' || storedAppearance === 'light') {
        return storedAppearance === 'dark';
    }

    return prefersDarkMode();
}

export function initializeDarkMode() {
    const isDark = resolveDarkModePreference();

    appState.darkMode = isDark;
    applyDarkMode(isDark);

    return isDark;
}

export async function initApp() {
    initializeDarkMode();

    if (appState.initialized) {
        return;
    }

    if (initPromise) {
        await initPromise;

        return;
    }

    initPromise = (async () => {
        const [cats, tags, breaking] = await Promise.all([
            api.getCategories(),
            api.getTags({ limit: 30, trending: true }),
            api.getBreaking(),
        ]);

        appState.categories = cats.data.data;
        appState.trendingTags = tags.data.data;
        appState.breakingNews = breaking.data.data;
        appState.initialized = true;
    })();

    try {
        await initPromise;
    } finally {
        initPromise = null;
    }
}

export function toggleDarkMode() {
    appState.darkMode = !appState.darkMode;
    applyDarkMode(appState.darkMode);

    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('darkMode', String(appState.darkMode));
        localStorage.setItem(
            'appearance',
            appState.darkMode ? 'dark' : 'light',
        );
    }

    setAppearanceCookie(appState.darkMode ? 'dark' : 'light');
}

export function toggleSidebar() {
    appState.sidebarOpen = !appState.sidebarOpen;
}

export function setBreakingNews(items) {
    appState.breakingNews = Array.isArray(items) ? items : [];
}
