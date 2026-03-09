import { createInertiaApp } from '@inertiajs/svelte';
import type { ResolvedComponent } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { render } from 'svelte/server';
import AppRoot from '@/AppRoot.svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte');

function resolvePage(name: string): Promise<ResolvedComponent> {
    const page = pages[`./pages/${name}.svelte`];

    if (!page) {
        return Promise.reject(new Error(`Unknown Inertia page: ${name}`));
    }

    return page();
}

createServer((page) =>
    createInertiaApp({
        page,
        resolve: resolvePage,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        setup({ App, props }) {
            return render(AppRoot, {
                props: {
                    App,
                    props,
                },
            });
        },
    }),
);
