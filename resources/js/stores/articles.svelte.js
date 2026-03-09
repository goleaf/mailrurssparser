import * as api from '@/lib/api';

export const listState = $state({
    articles: [],
    pagination: null,
    loading: false,
    error: null,
});

export const filters = $state({
    category: null,
    sub: null,
    tags: [],
    content_type: null,
    importance_min: null,
    date: null,
    date_from: null,
    date_to: null,
    sort: 'latest',
    search: '',
    page: 1,
    per_page: 20,
});

export function activeFiltersCount() {
    return [
        filters.category,
        filters.sub,
        filters.tags.length > 0,
        filters.content_type,
        filters.importance_min,
        filters.date || (filters.date_from && filters.date_to),
        filters.search,
    ].filter(Boolean).length;
}

export async function loadArticles({ append = false } = {}) {
    listState.loading = true;
    listState.error = null;

    try {
        const res = await api.getArticles(toApiParams(filters));
        const nextArticles = res.data;

        listState.articles = append
            ? [
                  ...listState.articles,
                  ...nextArticles.filter(
                      (article) =>
                          !listState.articles.some(
                              (current) => current.id === article.id,
                          ),
                  ),
              ]
            : nextArticles;
        listState.pagination = res.meta;
    } catch (e) {
        listState.error = e.message;
    } finally {
        listState.loading = false;
    }
}

function toApiParams(f) {
    return {
        category: f.category,
        sub: f.sub,
        tags: f.tags,
        content_type: f.content_type,
        importance_min: f.importance_min,
        date: f.date,
        date_from: f.date_from,
        date_to: f.date_to,
        sort: f.sort,
        search: f.search || undefined,
        page: f.page,
        per_page: f.per_page,
    };
}

export const resetFilters = () => {
    Object.assign(filters, {
        category: null,
        sub: null,
        tags: [],
        content_type: null,
        importance_min: null,
        date: null,
        date_from: null,
        date_to: null,
        sort: 'latest',
        search: '',
        page: 1,
        per_page: 20,
    });
};

export const setCategory = (slug) => {
    filters.category = slug;
    filters.sub = null;
    filters.page = 1;
};

export const setSubCategory = (slug) => {
    filters.sub = slug;
    filters.page = 1;
};

export const setDate = (date) => {
    filters.date = date;
    filters.date_from = null;
    filters.date_to = null;
    filters.page = 1;
};

export const setDateRange = (from, to) => {
    filters.date = null;
    filters.date_from = from;
    filters.date_to = to;
    filters.page = 1;
};

export const clearDate = () => {
    filters.date = null;
    filters.date_from = null;
    filters.date_to = null;
    filters.page = 1;
};

export const setContentType = (contentType) => {
    filters.content_type = contentType;
    filters.page = 1;
};

export const setImportance = (importance) => {
    filters.importance_min = importance;
    filters.page = 1;
};

export const setSearch = (search) => {
    filters.search = search;
    filters.page = 1;
};

export const setSort = (s) => {
    filters.sort = s;
    filters.page = 1;
};

export const setPage = (p) => {
    filters.page = p;
};

export const toggleTag = (slug) => {
    const idx = filters.tags.indexOf(slug);

    if (idx > -1) {
        filters.tags.splice(idx, 1);
    } else {
        filters.tags.push(slug);
    }

    filters.page = 1;
};
