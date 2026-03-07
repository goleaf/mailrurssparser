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
    class="group flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 transition-all duration-300 hover:border-gray-200 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
>
    <a href={`/#/articles/${article.slug}`} class="shrink-0">
        {#if article.image_url}
            <img
                src={article.image_url}
                alt={article.title}
                loading="lazy"
                decoding="async"
                class="h-20 w-20 rounded-xl object-cover transition-transform duration-500 group-hover:scale-105"
            />
        {:else}
            <div
                class="flex h-20 w-20 items-center justify-center rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 text-2xl dark:from-gray-700 dark:to-gray-600"
            >
                {article.category?.icon || '📰'}
            </div>
        {/if}
    </a>

    <div class="min-w-0 flex-1">
        <a href={`/#/articles/${article.slug}`}>
            <h3
                class="line-clamp-3 text-sm leading-snug font-semibold text-gray-900 transition-colors hover:text-blue-600 dark:text-white"
            >
                {article.title}
            </h3>
        </a>

        <time class="mt-2 block text-xs text-gray-400">{formattedDate}</time>
    </div>
</article>
