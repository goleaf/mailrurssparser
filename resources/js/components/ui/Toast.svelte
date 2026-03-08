<script context="module" lang="ts">
    export {
        dismissToast,
        showToast,
        subscribeToToasts,
        type ToastItem,
        type ToastType,
    } from '@/components/ui/toast-state';
</script>

<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import { fade, fly } from 'svelte/transition';
    import { onMount } from 'svelte';
    import { cn } from '@/lib/utils';
    import {
        dismissToast,
        subscribeToToasts,
        type ToastItem,
    } from '@/components/ui/toast-state';

    let toasts = $state<ToastItem[]>([]);

    const toneClasses: Record<string, string> = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-100',
        error: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-100',
        warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-100',
        info: 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-500/30 dark:bg-sky-500/15 dark:text-sky-100',
    };

    onMount(() => subscribeToToasts((items) => {
        toasts = items;
    }));
</script>

<div class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-full max-w-sm flex-col gap-3 px-4 sm:bottom-6 sm:right-6">
    {#each toasts as toast (toast.id)}
        <div
            class={cn(
                'pointer-events-auto rounded-2xl border px-4 py-3 shadow-xl backdrop-blur',
                toneClasses[toast.type] ?? toneClasses.info,
            )}
            in:fly={{ x: 24, duration: 180 }}
            out:fade={{ duration: 160 }}
        >
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1 text-sm font-medium">
                    {toast.message}
                </div>

                <button
                    type="button"
                    class="rounded-full p-1 opacity-70 transition hover:opacity-100"
                    onclick={() => {
                        dismissToast(toast.id);
                    }}
                    aria-label="Закрыть уведомление"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>
    {/each}
</div>
