<script lang="ts">
    type Tag = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
    };

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type Article = {
        title: string;
        short_description?: string | null;
        image_url?: string | null;
        image_caption?: string | null;
        author?: string | null;
        is_breaking?: boolean;
        published_at_date?: string | null;
        category: Omit<Category, 'id' | 'slug'>;
        tags?: Tag[];
    };

    let {
        article,
        publishedDate,
        displaySourceName,
        contentTypeLabel,
        headerFacts,
        homeHref,
        categoryHref,
        tagHref,
    }: {
        article: Article;
        publishedDate: string;
        displaySourceName: string | null;
        contentTypeLabel: string | null;
        headerFacts: Array<{ label: string; value: string }>;
        homeHref: string;
        categoryHref: string;
        tagHref: (slug: string) => string;
    } = $props();
</script>

<section
    class="relative overflow-hidden rounded-[2.5rem] border border-slate-200/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,250,252,0.94),rgba(239,246,255,0.96))] p-6 shadow-[0_40px_120px_-60px_rgba(15,23,42,0.46)] backdrop-blur dark:border-white/10 dark:bg-[linear-gradient(135deg,rgba(15,23,42,0.94),rgba(15,23,42,0.88),rgba(8,47,73,0.84))] lg:p-8"
>
    <div
        class="absolute right-0 top-0 h-56 w-56 rounded-full bg-sky-200/60 blur-3xl dark:bg-sky-500/18"
    ></div>
    <div
        class="absolute bottom-0 left-0 h-40 w-40 rounded-full blur-3xl"
        style={`background-color: ${article.category.color ?? '#2563EB'}22`}
    ></div>
    <header class="relative mx-auto max-w-5xl">
        <nav
            class="mb-5 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
        >
            <a
                href={homeHref}
                class="transition hover:text-slate-900 dark:hover:text-white"
            >
                Главная
            </a>
            <span>→</span>
            <a
                href={categoryHref}
                class="transition hover:text-slate-900 dark:hover:text-white"
            >
                {article.category.name}
            </a>
            <span>→</span>
            <span class="line-clamp-1 text-slate-700 dark:text-slate-200">
                {article.title}
            </span>
        </nav>

        <div class="mb-5 flex flex-wrap items-center gap-3">
            <span
                class="rounded-full px-3 py-1.5 text-xs font-semibold text-white shadow-sm"
                style={`background-color: ${article.category.color ?? '#2563EB'}`}
            >
                {article.category.icon ?? '📰'}
                {article.category.name}
            </span>
            {#if contentTypeLabel}
                <span
                    class="rounded-full border border-slate-200/80 bg-white/85 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                >
                    {contentTypeLabel}
                </span>
            {/if}

            {#if article.is_breaking}
                <span
                    class="rounded-full bg-red-500 px-3 py-1.5 text-xs font-semibold text-white"
                >
                    СРОЧНО
                </span>
            {/if}
        </div>

        <h1
            class="max-w-4xl text-3xl leading-tight font-bold text-slate-950 sm:text-4xl lg:text-5xl dark:text-white"
        >
            {article.title}
        </h1>

        <div
            class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500 dark:text-slate-400"
        >
            <span>{publishedDate || article.published_at_date}</span>
            <span>•</span>
            <span>{article.author || 'Редакция'}</span>
            {#if displaySourceName}
                <span>•</span>
                <span>{displaySourceName}</span>
            {/if}
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {#each headerFacts as fact (fact.label)}
                <div
                    class="rounded-[1.5rem] border border-slate-200/80 bg-white/80 px-4 py-3 shadow-sm dark:border-white/10 dark:bg-white/5"
                >
                    <div
                        class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-400"
                    >
                        {fact.label}
                    </div>
                    <div
                        class="mt-2 text-base font-semibold text-slate-950 dark:text-white"
                    >
                        {fact.value}
                    </div>
                </div>
            {/each}
        </div>

        {#if article.tags?.length}
            <div class="mt-5 flex flex-wrap gap-2">
                {#each article.tags as tag (tag.id)}
                    <a
                        href={tagHref(tag.slug)}
                        class="rounded-full border border-slate-200/80 px-3 py-1.5 text-xs font-medium text-slate-700 dark:border-white/10 dark:text-slate-200"
                        style={`background-color: ${tag.color ? `${tag.color}1E` : '#E2E8F0'}`}
                    >
                        #{tag.name}
                    </a>
                {/each}
            </div>
        {/if}
    </header>

    <div class="relative mx-auto mt-10 max-w-5xl">
        {#if article.image_url}
            <figure
                class="overflow-hidden rounded-[2.1rem] border border-slate-200/80 bg-white shadow-[0_30px_90px_-60px_rgba(15,23,42,0.42)] dark:border-white/10 dark:bg-white/5"
            >
                <img
                    src={article.image_url}
                    alt={article.title}
                    loading="eager"
                    decoding="async"
                    class="max-h-[34rem] w-full object-cover"
                />
                {#if article.image_caption}
                    <figcaption
                        class="border-t border-slate-200/80 px-5 py-4 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
                    >
                        {article.image_caption}
                    </figcaption>
                {/if}
            </figure>
        {:else}
            <div
                class="flex min-h-80 items-center justify-center rounded-[2.1rem] border border-slate-200/80 text-white shadow-[0_30px_90px_-60px_rgba(15,23,42,0.42)] dark:border-white/10"
                style={`background: linear-gradient(135deg, ${article.category.color ?? '#2563EB'} 0%, #0f172a 100%)`}
            >
                <div class="text-center">
                    <div class="text-7xl">{article.category.icon || '📰'}</div>
                    <div class="mt-4 text-lg font-semibold">
                        {article.category.name}
                    </div>
                </div>
            </div>
        {/if}
    </div>

    {#if article.short_description}
        <div
            class="mx-auto mt-8 max-w-4xl rounded-[1.9rem] border border-sky-100 bg-[linear-gradient(180deg,rgba(240,249,255,0.95),rgba(239,246,255,0.85))] p-6 text-base leading-7 text-slate-700 shadow-sm dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-100"
        >
            {article.short_description}
        </div>
    {/if}
</section>
