import {
    derived,
    get,
    readonly,
    writable,
} from 'svelte/store';
import * as api from '@/features/portal/data/api';

/** @typedef {import('@/features/portal/data/api').ApiArticleListItem} ApiArticleListItem */
/** @typedef {import('@/features/portal/data/api').ApiPaginationMeta} ApiPaginationMeta */

/**
 * @typedef {{
 *   category: string | null;
 *   sub: string | null;
 *   tags: string[];
 *   content_type: string | null;
 *   importance_min: number | null;
 *   date: string | null;
 *   date_from: string | null;
 *   date_to: string | null;
 *   sort: string;
 *   search: string;
 *   page: number;
 *   per_page: number;
 * }} ArticleFilters
 */

/**
 * @typedef {{
 *   articles: ApiArticleListItem[];
 *   pagination: ApiPaginationMeta | null;
 *   loading: boolean;
 *   error: string | null;
 * }} ArticleListState
 */

/** @returns {ArticleListState} */
function createListState() {
    return {
        articles: [],
        pagination: null,
        loading: false,
        error: null,
    };
}

/** @returns {ArticleFilters} */
function createFiltersState() {
    return {
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
    };
}

/** @param {Partial<ArticleListState>} nextState */
function replaceListState(nextState) {
    listStateStore.update((state) => ({
        ...state,
        ...nextState,
    }));
}

/** @param {Partial<ArticleFilters>} nextState */
function replaceFilters(nextState) {
    filtersStore.update((state) => ({
        ...state,
        ...nextState,
    }));
}

const listStateStore = writable(createListState());

const filtersStore = writable(createFiltersState());

export const listState = readonly(listStateStore);

export const filters = readonly(filtersStore);

export const activeFiltersCount = derived(filtersStore, ($filters) =>
    [
        $filters.category,
        $filters.sub,
        $filters.tags.length > 0,
        $filters.content_type,
        $filters.importance_min,
        $filters.date || ($filters.date_from && $filters.date_to),
        $filters.search,
    ].filter(Boolean).length,
);

/**
 * @param {ArticleFilters} [activeFilters]
 * @param {{ append?: boolean }} [options]
 */
export async function loadArticles(
    activeFilters = get(filtersStore),
    { append = false } = {},
) {
    replaceListState({
        loading: true,
        error: null,
    });

    try {
        const res = await api.getArticles(toApiParams(activeFilters));
        const nextArticles = res.data;
        const currentArticles = get(listStateStore).articles;
        const articles = append
            ? [
                  ...currentArticles,
                  ...nextArticles.filter(
                      (article) =>
                          !currentArticles.some(
                              (current) => current.id === article.id,
                          ),
                  ),
              ]
            : nextArticles;

        replaceListState({
            articles,
            pagination: res.meta,
        });
    } catch (e) {
        replaceListState({
            error: e instanceof Error ? e.message : 'Не удалось загрузить статьи.',
        });
    } finally {
        replaceListState({
            loading: false,
        });
    }
}

/**
 * @param {ArticleFilters} f
 * @returns {Record<string, unknown>}
 */
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
    replaceFilters(createFiltersState());
};

/** @param {string | null} slug */
export const setCategory = (slug) => {
    replaceFilters({
        category: slug,
        sub: null,
        page: 1,
    });
};

/** @param {string | null} slug */
export const setSubCategory = (slug) => {
    replaceFilters({
        sub: slug,
        page: 1,
    });
};

/** @param {string | null} date */
export const setDate = (date) => {
    replaceFilters({
        date,
        date_from: null,
        date_to: null,
        page: 1,
    });
};

/**
 * @param {string | null} from
 * @param {string | null} to
 */
export const setDateRange = (from, to) => {
    replaceFilters({
        date: null,
        date_from: from,
        date_to: to,
        page: 1,
    });
};

export const clearDate = () => {
    replaceFilters({
        date: null,
        date_from: null,
        date_to: null,
        page: 1,
    });
};

/**
 * @param {'date_from' | 'date_to'} key
 * @param {string | null} value
 */
export const setDateBoundary = (key, value) => {
    replaceFilters({
        date: null,
        [key]: value,
        page: 1,
    });
};

/** @param {string | null} contentType */
export const setContentType = (contentType) => {
    replaceFilters({
        content_type: contentType,
        page: 1,
    });
};

/** @param {number | null} importance */
export const setImportance = (importance) => {
    replaceFilters({
        importance_min: importance,
        page: 1,
    });
};

/** @param {string} search */
export const setSearch = (search) => {
    replaceFilters({
        search,
        page: 1,
    });
};

/** @param {string} s */
export const setSort = (s) => {
    replaceFilters({
        sort: s,
        page: 1,
    });
};

/** @param {number} p */
export const setPage = (p) => {
    replaceFilters({
        page: p,
    });
};

export const clearTags = () => {
    replaceFilters({
        tags: [],
        page: 1,
    });
};

/** @param {string} slug */
export const toggleTag = (slug) => {
    const currentFilters = get(filtersStore);

    replaceFilters({
        tags: currentFilters.tags.includes(slug)
            ? currentFilters.tags.filter((tag) => tag !== slug)
            : [...currentFilters.tags, slug],
        page: 1,
    });
};
