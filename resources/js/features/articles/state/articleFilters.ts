export type ArticleFilters = {
    category: string | null;
    sub: string | null;
    tags: string[];
    contentType: string | null;
    importanceMin: number | null;
    date: string | null;
    dateFrom: string | null;
    dateTo: string | null;
    sort: string;
    search: string;
    page: number;
    perPage: number;
};

export type ArticleListItem = {
    id: number;
    title: string;
};

export function createArticleFilters(): ArticleFilters {
    return {
        category: null,
        sub: null,
        tags: [],
        contentType: null,
        importanceMin: null,
        date: null,
        dateFrom: null,
        dateTo: null,
        sort: 'latest',
        search: '',
        page: 1,
        perPage: 20,
    };
}

export function countActiveArticleFilters(filters: ArticleFilters): number {
    return [
        filters.category,
        filters.sub,
        filters.tags.length > 0,
        filters.contentType,
        filters.importanceMin,
        filters.date || (filters.dateFrom && filters.dateTo),
        filters.search.trim(),
    ].filter(Boolean).length;
}

export function mergeArticlesById(
    currentArticles: ArticleListItem[],
    nextArticles: ArticleListItem[],
): ArticleListItem[] {
    const seenIds = new Set<number>(currentArticles.map((article) => article.id));

    return [
        ...currentArticles,
        ...nextArticles.filter((article) => !seenIds.has(article.id)),
    ];
}

export function updateArticleFilters(
    filters: ArticleFilters,
    nextState: Partial<ArticleFilters>,
): ArticleFilters {
    return {
        ...filters,
        ...nextState,
    };
}
