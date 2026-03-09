<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import type { Component } from 'svelte';
    import Toast, { showToast } from '@/components/ui/Toast.svelte';
    import type { ToastType } from '@/components/ui/Toast.svelte';
    import {
        initApp,
        isPublicInertiaPath,
        visitPublic,
    } from '@/features/portal';

    type AppPageProps = Record<string, unknown>;

    interface AppRootProps {
        App: Component<AppPageProps>;
        props: AppPageProps;
    }

    type UpdateDetail = {
        registration: ServiceWorkerRegistration;
    };

    type FlashToast = {
        message: string;
        type?: ToastType;
    };

    type InertiaPage = {
        flash?: {
            toast?: FlashToast | null;
        } | null;
    };

    let { App, props }: AppRootProps = $props();

    let updateRegistration = $state<ServiceWorkerRegistration | null>(null);
    let lastFlashToastSignature = $state('');

    async function sendSchedulerHeartbeat(): Promise<void> {
        try {
            await fetch('/scheduler/pulse', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                keepalive: true,
            });
        } catch {
            return;
        }
    }

    function applyUpdate(): void {
        updateRegistration?.waiting?.postMessage({
            type: 'SKIP_WAITING',
        });
    }

    function syncFlashToast(currentPage?: InertiaPage): void {
        const toast = currentPage?.flash?.toast;

        if (!toast || toast.message === '') {
            lastFlashToastSignature = '';

            return;
        }

        const signature = JSON.stringify(toast);

        if (signature === lastFlashToastSignature) {
            return;
        }

        lastFlashToastSignature = signature;

        showToast(toast.message, toast.type ?? 'info');
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        void initApp();

        const heartbeatTimer = window.setInterval(() => {
            void sendSchedulerHeartbeat();
        }, 60_000);

        const handleUpdate = (event: Event): void => {
            const detail = (event as CustomEvent<UpdateDetail>).detail;

            updateRegistration = detail?.registration ?? null;
        };

        const handleDocumentClick = (event: MouseEvent): void => {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const anchor = target.closest('a');

            if (!(anchor instanceof HTMLAnchorElement)) {
                return;
            }

            const href = anchor.getAttribute('href');

            if (
                !href ||
                href.startsWith('#') ||
                anchor.target === '_blank' ||
                anchor.hasAttribute('download') ||
                anchor.getAttribute('rel')?.includes('external')
            ) {
                return;
            }

            const url = new URL(anchor.href, window.location.origin);

            if (
                url.origin !== window.location.origin ||
                !isPublicInertiaPath(url.pathname)
            ) {
                return;
            }

            event.preventDefault();

            visitPublic(`${url.pathname}${url.search}${url.hash}`);
        };

        window.addEventListener('sw:update-ready', handleUpdate);
        document.addEventListener('click', handleDocumentClick);

        return () => {
            window.clearInterval(heartbeatTimer);
            window.removeEventListener('sw:update-ready', handleUpdate);
            document.removeEventListener('click', handleDocumentClick);
        };
    });

    $effect(() => {
        syncFlashToast(page as InertiaPage | undefined);
    });
</script>

{#if updateRegistration?.waiting}
    <div
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4"
    >
        <div
            class="pointer-events-auto flex max-w-xl items-center gap-4 rounded-2xl border border-sky-200 bg-white px-5 py-4 text-sm shadow-xl dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        >
            <div class="flex-1">
                <div class="font-semibold text-slate-900 dark:text-white">
                    Доступно обновление
                </div>
                <div class="mt-1 text-slate-600 dark:text-gray-300">
                    Загрузить новую версию приложения и обновить страницу.
                </div>
            </div>

            <button
                type="button"
                class="rounded-full bg-sky-600 px-4 py-2 font-medium text-white transition hover:bg-sky-500"
                onclick={applyUpdate}
            >
                Обновить
            </button>
        </div>
    </div>
{/if}

<Toast />
<App {...props} />
