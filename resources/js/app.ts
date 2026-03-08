import type { VisitOptions } from '@inertiajs/core';
import { createInertiaApp } from '@inertiajs/svelte';
import type { ResolvedComponent } from '@inertiajs/svelte';
import { hydrate, mount } from 'svelte';
import '../css/app.css';
import AppRoot from '@/AppRoot.svelte';
import { initializeTheme } from '@/lib/theme.svelte';
import { initializeDarkMode } from '@/stores/app.svelte.js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte');

function defaultVisitOptions(
    _href: string,
    options: VisitOptions,
): VisitOptions {
    if (options.viewTransition !== undefined) {
        return {};
    }

    const method = (options.method ?? 'get').toLowerCase();

    return method === 'get' ? { viewTransition: true } : {};
}

function resolvePage(name: string): Promise<ResolvedComponent> {
    const page = pages[`./pages/${name}.svelte`];

    if (!page) {
        return Promise.reject(new Error(`Unknown Inertia page: ${name}`));
    }

    return page();
}

function dispatchServiceWorkerUpdate(
    registration: ServiceWorkerRegistration,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(
        new CustomEvent('sw:update-ready', {
            detail: { registration },
        }),
    );
}

function registerServiceWorker(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    let refreshing = false;

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) {
            return;
        }

        refreshing = true;
        window.location.reload();
    });

    window.addEventListener('load', () => {
        void navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                const announceUpdate = (): void => {
                    if (registration.waiting) {
                        dispatchServiceWorkerUpdate(registration);
                    }
                };

                announceUpdate();

                registration.addEventListener('updatefound', () => {
                    const installingWorker = registration.installing;

                    if (!installingWorker) {
                        return;
                    }

                    installingWorker.addEventListener('statechange', () => {
                        if (
                            installingWorker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            announceUpdate();
                        }
                    });
                });
            })
            .catch(() => {});
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePage,
    defaults: {
        visitOptions: defaultVisitOptions,
    },
    setup({ el, App, props }) {
        if (!el) return;

        const rootProps = { App, props };

        if (el.dataset.serverRendered === 'true') {
            hydrate(AppRoot, { target: el, props: rootProps });
        } else {
            mount(AppRoot, { target: el, props: rootProps });
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
initializeDarkMode();
registerServiceWorker();
