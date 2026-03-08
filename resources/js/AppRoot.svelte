<script lang="ts">
    import { onMount } from 'svelte';
    import Toast from '@/components/ui/Toast.svelte';
    import { initApp } from '@/stores/app.svelte.js';

    type AppRootProps = {
        App: any;
        props: any;
    };

    type UpdateDetail = {
        registration: ServiceWorkerRegistration;
    };

    let { App, props }: AppRootProps = $props();

    let updateRegistration = $state<ServiceWorkerRegistration | null>(null);

    function applyUpdate(): void {
        updateRegistration?.waiting?.postMessage({
            type: 'SKIP_WAITING',
        });
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        void initApp();

        const handleUpdate = (event: Event): void => {
            const detail = (event as CustomEvent<UpdateDetail>).detail;

            updateRegistration = detail?.registration ?? null;
        };

        window.addEventListener('sw:update-ready', handleUpdate);

        return () => {
            window.removeEventListener('sw:update-ready', handleUpdate);
        };
    });
</script>

{#if updateRegistration?.waiting}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
        <div class="pointer-events-auto flex max-w-xl items-center gap-4 rounded-2xl border border-sky-200 bg-white px-5 py-4 text-sm shadow-xl dark:border-gray-700 dark:bg-gray-800 dark:text-white">
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
