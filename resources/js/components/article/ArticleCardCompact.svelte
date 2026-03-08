<script lang="ts">
    type ArticleCategory = {
        icon?: string | null;
    };

    type Article = {
        title: string;
        slug: string;
        image_url?: string | null;
        published_at?: string | null;
        category?: ArticleCategory | null;
    };

    let { article }: { article: Article } = $props();

    const formattedDate = $derived(
        article.published_at
            ? new Intl.DateTimeFormat('ru-RU', {
                  day: 'numeric',
                  month: 'short',
              }).format(new Date(article.published_at))
            : 'Без даты',
    );
</script>

<article
    class="group flex items-center gap-3 rounded-[1.4rem] border border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] p-3 transition-all duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.92),rgba(15,23,42,0.82))]"
>
    <a href={`/#/articles/${article.slug}`} class="shrink-0">
        {#if article.image_url}
            <img
                src={article.image_url}
                alt={article.title}
                loading="lazy"
                decoding="async"
                class="h-22 w-22 rounded-[1rem] object-cover transition-transform duration-500 group-hover:scale-105"
            />
        {:else}
            <div
                class="flex h-22 w-22 items-center justify-center rounded-[1rem] bg-[linear-gradient(135deg,#dbeafe,#e2e8f0,#cbd5e1)] text-2xl dark:bg-[linear-gradient(135deg,#1e293b,#0f172a,#334155)]"
            >
                {article.category?.icon || '📰'}
            </div>
        {/if}
    </a>

    <div class="min-w-0 flex-1">
        <a href={`/#/articles/${article.slug}`}>
            <h3
                class="line-clamp-3 text-sm leading-snug font-semibold text-slate-900 transition-colors hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
            >
                {article.title}
            </h3>
        </a>

        <div class="mt-2 flex items-center gap-2 text-[0.72rem] uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">
            <span>Лента</span>
            <span class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
            <time>{formattedDate}</time>
        </div>
    </div>
</article>
