<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        sanitizedContent,
        sourceUrl,
        displaySourceName,
    }: {
        sanitizedContent: string;
        sourceUrl?: string | null;
        displaySourceName?: string | null;
    } = $props();
</script>

<div
    class="rounded-[2.1rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] p-6 shadow-[0_30px_90px_-65px_rgba(15,23,42,0.46)] dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.84))] lg:p-8"
>
    <div
        class={cn(
            'max-w-none text-base leading-8 text-slate-700 dark:text-slate-200',
            '[&_a]:font-medium [&_a]:text-sky-600 [&_a]:underline [&_a]:underline-offset-4 dark:[&_a]:text-sky-300',
            '[&_blockquote]:my-6 [&_blockquote]:border-l-4 [&_blockquote]:border-sky-500 [&_blockquote]:bg-sky-50 [&_blockquote]:px-5 [&_blockquote]:py-4 dark:[&_blockquote]:bg-sky-950/30',
            '[&_h1]:mt-8 [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-slate-950 dark:[&_h1]:text-white',
            '[&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-slate-950 dark:[&_h2]:text-white',
            '[&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-slate-950 dark:[&_h3]:text-white',
            '[&_img]:my-6 [&_img]:rounded-2xl',
            '[&_li]:ml-5 [&_li]:list-disc',
            '[&_p]:my-4',
        )}
    >
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html sanitizedContent}
    </div>

    {#if sourceUrl}
        <div class="mt-8 border-t border-slate-200 pt-6 dark:border-white/10">
            <a
                href={sourceUrl}
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-2 text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-300 dark:hover:text-sky-200"
            >
                {displaySourceName
                    ? `Читать оригинал на ${displaySourceName} →`
                    : 'Читать оригинал →'}
            </a>
        </div>
    {/if}
</div>
