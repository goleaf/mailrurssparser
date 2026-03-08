<script lang="ts">
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Newspaper from 'lucide-svelte/icons/newspaper';
    import { cn } from '@/lib/utils';

    type Category = {
        id: number | string;
        name: string;
        slug: string;
        color?: string | null;
        icon?: string | null;
    };

    type HeaderCategoryLink = Category & {
        href: string;
        indicatorStyle: string;
        isActive: boolean;
        menuLabel: string;
    };

    let {
        categories = [],
        currentHash = '#/',
        onHome = () => {},
    }: {
        categories?: Category[];
        currentHash?: string;
        onHome?: () => void;
    } = $props();

    let moreMenuOpen = $state(false);

    const categoryLinks = $derived.by(() =>
        categories.map(
            (category): HeaderCategoryLink => ({
                ...category,
                href: `/#/category/${category.slug}`,
                indicatorStyle: `background-color: ${category.color ?? '#2563EB'};`,
                isActive: currentHash.startsWith(`#/category/${category.slug}`),
                menuLabel: `${category.icon ?? '•'} ${category.name}`,
            }),
        ),
    );
    const featuredCategoryLinks = $derived(categoryLinks.slice(0, 6));
    const overflowCategoryLinks = $derived(categoryLinks.slice(6));
    const allCategoriesLinkClass = $derived(
        cn(
            'rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
            currentHash === '#/' &&
                'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950',
        ),
    );

    function featuredCategoryLinkClass(isActive: boolean): string {
        return cn(
            'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
            isActive &&
                'bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-950',
        );
    }

    function overflowCategoryLinkClass(isActive: boolean): string {
        return cn(
            'flex items-center gap-3 rounded-2xl border border-transparent px-3 py-2 text-sm text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 dark:text-slate-200 dark:hover:border-white/10 dark:hover:bg-white/5',
            isActive &&
                'border-slate-200 bg-slate-50 text-slate-900 dark:border-white/10 dark:bg-white/8 dark:text-white',
        );
    }

    function openMoreMenu(): void {
        moreMenuOpen = true;
    }

    function closeMoreMenu(): void {
        moreMenuOpen = false;
    }

    function toggleMoreMenu(): void {
        moreMenuOpen = !moreMenuOpen;
    }
</script>

<nav class="hidden min-w-0 flex-1 items-center gap-2 lg:flex">
    <a href="/#/" onclick={onHome} class={allCategoriesLinkClass}>
        Все новости
    </a>

    {#each featuredCategoryLinks as category (category.id)}
        <a
            href={category.href}
            class={featuredCategoryLinkClass(category.isActive)}
        >
            <span class="size-2 rounded-full" style={category.indicatorStyle}
            ></span>
            <span class="truncate">{category.name}</span>
        </a>
    {/each}

    {#if overflowCategoryLinks.length > 0}
        <div
            class="relative"
            role="presentation"
            onmouseenter={openMoreMenu}
            onmouseleave={closeMoreMenu}
        >
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                aria-expanded={moreMenuOpen}
                aria-haspopup="menu"
                onclick={toggleMoreMenu}
            >
                Ещё
                <ChevronDown
                    class={cn(
                        'size-4 transition-transform duration-200',
                        moreMenuOpen && 'rotate-180',
                    )}
                />
            </button>

            {#if moreMenuOpen}
                <div
                    class="absolute left-0 top-full mt-3 w-84 rounded-[2rem] border border-slate-200 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] p-4 shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(15,23,42,0.98),rgba(2,6,23,0.96))] dark:shadow-black/50"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <div
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                Все рубрики
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Быстрый переход по разделам
                            </div>
                        </div>
                        <Newspaper class="size-4 text-slate-400" />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        {#each categoryLinks as category (category.id)}
                            <a
                                href={category.href}
                                class={overflowCategoryLinkClass(
                                    category.isActive,
                                )}
                            >
                                <span
                                    class="size-2 rounded-full"
                                    style={category.indicatorStyle}
                                ></span>
                                <span class="truncate"
                                    >{category.menuLabel}</span
                                >
                            </a>
                        {/each}
                    </div>
                </div>
            {/if}
        </div>
    {/if}
</nav>
