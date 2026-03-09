/** @import { ApiArticleListItem, ApiCategory, ApiTag } from '@/features/portal/data/api' */

import {
    derived,
    get,
    readonly,
    writable,
} from 'svelte/store';
import * as api from '@/features/portal/data/api';

/** @type {Promise<void> | null} */
let initPromise = null;

/** @type {{
    categories: ApiCategory[];
    trendingTags: ApiTag[];
    breakingNews: ApiArticleListItem[];
    initialized: boolean;
    darkMode: boolean;
    sidebarOpen: boolean;
}} */
function createAppState() {
    return {
        categories: [],
        trendingTags: [],
        breakingNews: [],
        initialized: false,
        darkMode: false,
        sidebarOpen: false,
    };
}

function replaceAppState(nextState) {
    appStateStore.update((state) => ({
        ...state,
        ...nextState,
    }));
}

const appStateStore = writable(createAppState());

export const appState = readonly(appStateStore);

export const appCategories = derived(appStateStore, ($appState) => $appState.categories);

export const appTrendingTags = derived(appStateStore, ($appState) => $appState.trendingTags);

export const appBreakingNews = derived(appStateStore, ($appState) => $appState.breakingNews);

export const appInitialized = derived(appStateStore, ($appState) => $appState.initialized);

export const appDarkMode = derived(appStateStore, ($appState) => $appState.darkMode);

export const appSidebarOpen = derived(appStateStore, ($appState) => $appState.sidebarOpen);

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

    replaceAppState({
        darkMode: isDark,
    });
    applyDarkMode(isDark);

    return isDark;
}

export async function initApp() {
    initializeDarkMode();

    if (get(appStateStore).initialized) {
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

        replaceAppState({
            categories: cats.data,
            trendingTags: tags.data,
            breakingNews: breaking.data,
            initialized: true,
        });
    })();

    try {
        await initPromise;
    } finally {
        initPromise = null;
    }
}

export function toggleDarkMode() {
    const darkMode = !get(appStateStore).darkMode;

    replaceAppState({
        darkMode,
    });
    applyDarkMode(darkMode);

    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('darkMode', String(darkMode));
        localStorage.setItem('appearance', darkMode ? 'dark' : 'light');
    }

    setAppearanceCookie(darkMode ? 'dark' : 'light');
}

export function toggleSidebar() {
    replaceAppState({
        sidebarOpen: !get(appStateStore).sidebarOpen,
    });
}

export function setSidebarOpen(sidebarOpen) {
    replaceAppState({
        sidebarOpen,
    });
}

export function syncDarkModeState(darkMode) {
    replaceAppState({
        darkMode,
    });
}

/** @param {ApiArticleListItem[]} items */
export function setBreakingNews(items) {
    replaceAppState({
        breakingNews: Array.isArray(items) ? items : [],
    });
}
