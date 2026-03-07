import * as api from '@/lib/api';

export let appState = $state({
    categories: [],
    trendingTags: [],
    breakingNews: [],
    initialized: false,
    darkMode: false,
    sidebarOpen: false,
});

export async function initApp() {
    const [cats, tags, breaking] = await Promise.all([
        api.getCategories(),
        api.getTags({ limit: 30, trending: true }),
        api.getBreaking(),
    ]);

    appState.categories = cats.data.data;
    appState.trendingTags = tags.data.data;
    appState.breakingNews = breaking.data.data;
    appState.initialized = true;
}

export function toggleDarkMode() {
    appState.darkMode = !appState.darkMode;

    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', appState.darkMode);
    }

    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('darkMode', String(appState.darkMode));
    }
}

export function toggleSidebar() {
    appState.sidebarOpen = !appState.sidebarOpen;
}
