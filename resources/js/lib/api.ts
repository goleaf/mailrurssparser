import type { SearchSuggestions } from '@/lib/searchAutocomplete';

const API_PREFIX = '/api/v1';

type HttpMethod = 'DELETE' | 'GET' | 'PATCH' | 'POST' | 'PUT';

type RequestOptions = {
    method?: HttpMethod;
    params?: Record<string, unknown>;
    data?: unknown;
    headers?: HeadersInit;
    signal?: AbortSignal;
};

export type ApiResponse<T> = {
    data: T;
    status: number;
};

export type ApiRequestError = Error & {
    status?: number;
    response?: {
        data: unknown;
        status: number;
    };
};

export type ApiNewsletterSubscriptionResponse = {
    success?: boolean;
    message?: string | null;
    already_subscribed?: boolean;
    resent?: boolean;
};

export type ApiShareResponse = {
    success?: boolean;
    platform?: string | null;
    share_url?: string | null;
    total?: number | null;
};

export type ApiBookmarkToggleResponse = {
    bookmarked: boolean;
    total: number;
};

export type ApiPaginationMeta = {
    current_page?: number;
    last_page?: number;
    total?: number;
    total_results?: number;
    [key: string]: unknown;
};

export type PaginatedApiResponse<
    T,
    M extends ApiPaginationMeta = ApiPaginationMeta,
> = {
    data: T[];
    meta: M | null;
    status: number;
};

export type ApiSubCategory = {
    id: number | string;
    name: string;
    slug: string;
};

export type ApiRssFeed = {
    id?: number | string;
    title?: string | null;
};

export type ApiCategory = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    icon?: string | null;
    description?: string | null;
    articles_count_cache?: number | null;
    article_count?: number | null;
    usage_count?: number | null;
    sub_categories?: ApiSubCategory[];
    rss_feeds?: ApiRssFeed[];
};

export type ApiTag = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    description?: string | null;
    usage_count?: number | null;
    article_count?: number | null;
};

export type ApiArticleCategory = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    icon?: string | null;
};

export type ApiArticleListItem = {
    id: number | string;
    title: string;
    slug: string;
    short_description?: string | null;
    image_url?: string | null;
    content_type?: string | null;
    is_breaking?: boolean;
    is_recent?: boolean;
    views_count?: number | null;
    reading_time?: number | null;
    published_at?: string | null;
    category: ApiArticleCategory;
    sub_category?: ApiSubCategory | null;
    tags?: ApiTag[];
};

export type ApiArticleDetail = ApiArticleListItem & {
    image_caption?: string | null;
    source_url?: string | null;
    author?: string | null;
    source_name?: string | null;
    content_type_label?: string | null;
    reading_time_text?: string | null;
    shares_count?: number | null;
    published_at_date?: string | null;
    rss_parsed_at?: string | null;
    full_content?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    structured_data?: Record<string, unknown> | null;
    rss_feed?: ApiRssFeed | null;
    related_articles?: ApiArticleListItem[];
    similar_articles?: ApiArticleListItem[];
    more_from_category?: ApiArticleListItem[];
};

export type SearchSuggestionFallback = {
    type: 'category' | 'tag';
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
};

export type SearchResultMeta = ApiPaginationMeta & {
    query?: string;
    suggestions?: SearchSuggestionFallback[];
};

export type StatsOverviewCategory = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    icon?: string | null;
    article_count: number;
};

export type StatsTrendingTag = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    usage_count?: number | null;
};

export type StatsOverview = {
    articles: {
        total: number;
        today: number;
        this_week: number;
        breaking: number;
        featured: number;
    };
    views: {
        total: number;
        today: number;
        this_week: number;
        unique_today: number;
    };
    top_countries?: Array<{
        country_code: string;
        view_count: number;
    }>;
    top_timezones?: Array<{
        timezone: string;
        view_count: number;
    }>;
    top_categories: StatsOverviewCategory[];
    trending_tags: StatsTrendingTag[];
    last_parse?: string | null;
    feeds: {
        total: number;
        active: number;
        errors: number;
    };
};

export type StatsChartSeries = {
    id: number;
    name: string;
    color: string;
    data: number[];
};

export type StatsChartPayload = {
    labels: string[];
    data: number[];
    period: '7d' | '30d' | '90d';
    series?: StatsChartSeries[];
};

export type StatsPopularRow = {
    article_id: number | string;
    title: string;
    slug: string;
    category?: string | null;
    view_count: number;
    shares_count: number;
    bookmarks_count: number;
    change_percent?: number | null;
};

export type StatsCategoryBreakdownItem = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    article_count: number;
    percentage: number;
    top_article?: {
        id: number | string;
        title: string;
        slug: string;
        views_count?: number | null;
    } | null;
};

export type StatsFeedPerformance = {
    id: number | string;
    title: string;
    category?: string | null;
    total_articles: number;
    today_articles_count: number;
    last_run?: {
        new_count: number;
        skip_count: number;
        error_count: number;
        duration_ms?: number | null;
        started_at?: string | null;
    } | null;
    avg_duration_ms?: number | null;
};

type PaginatedPayload<T, M extends ApiPaginationMeta = ApiPaginationMeta> = {
    data: T[];
    meta: M | null;
};

function getOrigin(): string {
    if (typeof window !== 'undefined') {
        return window.location.origin;
    }

    return 'http://localhost';
}

function buildUrl(path: string, params: Record<string, unknown> = {}): string {
    const url = new URL(path, getOrigin());

    Object.entries(params).forEach(([key, value]) => {
        if (
            value === undefined ||
            value === null ||
            value === '' ||
            (Array.isArray(value) && value.length === 0)
        ) {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => {
                url.searchParams.append(key, String(item));
            });

            return;
        }

        url.searchParams.set(key, String(value));
    });

    return url.toString();
}

function getCsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function getClientTimezone(): string {
    if (typeof Intl === 'undefined') {
        return '';
    }

    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
    } catch {
        return '';
    }
}

function getClientLocale(): string {
    if (typeof navigator !== 'undefined') {
        return navigator.languages?.[0] ?? navigator.language ?? '';
    }

    if (typeof document !== 'undefined') {
        return document.documentElement?.lang ?? '';
    }

    return '';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function unwrapDataArray<T>(payload: unknown): T[] {
    if (Array.isArray(payload)) {
        return payload as T[];
    }

    if (isRecord(payload) && Array.isArray(payload.data)) {
        return payload.data as T[];
    }

    return [];
}

function unwrapResource<T>(payload: unknown): T | null {
    if (isRecord(payload) && 'data' in payload) {
        return (payload.data as T | null | undefined) ?? null;
    }

    if (isRecord(payload)) {
        return payload as T;
    }

    return null;
}

function unwrapMeta<M extends ApiPaginationMeta = ApiPaginationMeta>(
    payload: unknown,
): M | null {
    if (isRecord(payload) && isRecord(payload.meta)) {
        return payload.meta as M;
    }

    return null;
}

function unwrapPaginated<T, M extends ApiPaginationMeta = ApiPaginationMeta>(
    payload: unknown,
): PaginatedPayload<T, M> {
    return {
        data: unwrapDataArray<T>(payload),
        meta: unwrapMeta<M>(payload),
    };
}

function toCollectionResponse<T>(
    response: ApiResponse<unknown>,
): ApiResponse<T[]> {
    return {
        ...response,
        data: unwrapDataArray<T>(response.data),
    };
}

function toResourceResponse<T>(
    response: ApiResponse<unknown>,
): ApiResponse<T | null> {
    return {
        ...response,
        data: unwrapResource<T>(response.data),
    };
}

function toPaginatedResponse<
    T,
    M extends ApiPaginationMeta = ApiPaginationMeta,
>(response: ApiResponse<unknown>): PaginatedApiResponse<T, M> {
    const payload = unwrapPaginated<T, M>(response.data);

    return {
        status: response.status,
        data: payload.data,
        meta: payload.meta,
    };
}

function normalizeSearchSuggestions(payload: unknown): SearchSuggestions {
    const data =
        isRecord(payload) && isRecord(payload.data) ? payload.data : payload;

    if (!isRecord(data)) {
        return {
            articles: [],
            categories: [],
            tags: [],
        };
    }

    return {
        articles: Array.isArray(data.articles)
            ? (data.articles as SearchSuggestions['articles'])
            : [],
        categories: Array.isArray(data.categories)
            ? (data.categories as SearchSuggestions['categories'])
            : [],
        tags: Array.isArray(data.tags)
            ? (data.tags as SearchSuggestions['tags'])
            : [],
    };
}

function normalizeCalendarData(payload: unknown): Record<string, number> {
    const rawData =
        isRecord(payload) && isRecord(payload.data) ? payload.data : payload;

    if (!isRecord(rawData)) {
        return {};
    }

    return Object.entries(rawData).reduce<Record<string, number>>(
        (counts, [day, value]) => {
            if (!/^\d+$/.test(day)) {
                return counts;
            }

            const numericValue = Number(value);

            if (!Number.isFinite(numericValue) || numericValue <= 0) {
                return counts;
            }

            counts[day] = numericValue;

            return counts;
        },
        {},
    );
}

async function request<T = unknown>(
    path: string,
    options: RequestOptions = {},
): Promise<ApiResponse<T>> {
    const { method = 'GET', params = {}, data, headers = {}, signal } = options;

    const response = await fetch(buildUrl(path, params), {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(data !== undefined
                ? { 'Content-Type': 'application/json' }
                : {}),
            ...(getCsrfToken() ? { 'X-CSRF-TOKEN': getCsrfToken() } : {}),
            ...(getClientTimezone()
                ? { 'X-Timezone': getClientTimezone() }
                : {}),
            ...(getClientLocale() ? { 'X-Locale': getClientLocale() } : {}),
            ...headers,
        },
        body: data !== undefined ? JSON.stringify(data) : undefined,
    });

    const text = await response.text();
    let payload: unknown = null;

    if (text) {
        try {
            payload = JSON.parse(text);
        } catch {
            payload = { message: text };
        }
    }

    if (!response.ok) {
        const error = new Error(
            isRecord(payload) && typeof payload.message === 'string'
                ? payload.message
                : `Request failed with status ${response.status}`,
        ) as ApiRequestError;

        error.status = response.status;
        error.response = {
            data: payload,
            status: response.status,
        };

        throw error;
    }

    return {
        data: payload as T,
        status: response.status,
    };
}

export const getArticles = async (
    params: Record<string, unknown> = {},
): Promise<PaginatedApiResponse<ApiArticleListItem>> => {
    const response = await request(`${API_PREFIX}/articles`, { params });

    return toPaginatedResponse<ApiArticleListItem>(response);
};

export const getArticle = (
    slug: string,
    params: Record<string, unknown> = {},
) =>
    request(`${API_PREFIX}/articles/${slug}`, { params }).then((response) =>
        toResourceResponse<ApiArticleDetail>(response),
    );

export const getRelated = (slug: string) =>
    request(`${API_PREFIX}/articles/${slug}/related`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getSimilar = (slug: string) =>
    request(`${API_PREFIX}/articles/${slug}/similar`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getFeatured = () =>
    request(`${API_PREFIX}/articles/featured`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getBreaking = () =>
    request(`${API_PREFIX}/articles/breaking`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getTrending = () =>
    request(`${API_PREFIX}/articles/trending`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getCategories = (params: Record<string, unknown> = {}) =>
    request(`${API_PREFIX}/categories`, { params }).then((response) =>
        toCollectionResponse<ApiCategory>(response),
    );

export const getCategory = (slug: string) =>
    request(`${API_PREFIX}/categories/${slug}`).then((response) =>
        toResourceResponse<ApiCategory>(response),
    );

export const getCategoryArticles = (
    slug: string,
    params: Record<string, unknown> = {},
) =>
    request(`${API_PREFIX}/categories/${slug}/articles`, { params }).then(
        (response) => toPaginatedResponse<ApiArticleListItem>(response),
    );

export const getPinnedArticles = (slug: string) =>
    request(`${API_PREFIX}/category/${slug}/pinned`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const getTags = (params: Record<string, unknown> = {}) =>
    request(`${API_PREFIX}/tags`, { params }).then((response) =>
        toCollectionResponse<ApiTag>(response),
    );

export const getTag = (slug: string) =>
    request(`${API_PREFIX}/tags/${slug}`).then((response) =>
        toResourceResponse<ApiTag>(response),
    );

export const getTagArticles = (
    slug: string,
    params: Record<string, unknown> = {},
) =>
    request(`${API_PREFIX}/tags/${slug}/articles`, { params }).then(
        (response) => toPaginatedResponse<ApiArticleListItem>(response),
    );

export const search = async (
    query: string,
    params: Record<string, unknown> = {},
): Promise<PaginatedApiResponse<ApiArticleListItem, SearchResultMeta>> => {
    const response = await request(`${API_PREFIX}/search`, {
        params: {
            q: query,
            ...params,
        },
    });

    return toPaginatedResponse<ApiArticleListItem, SearchResultMeta>(response);
};

export const suggestSearch = async (
    query: string,
    options: Pick<RequestOptions, 'signal'> = {},
): Promise<ApiResponse<SearchSuggestions>> => {
    const response = await request(`${API_PREFIX}/search/suggest`, {
        params: {
            q: query,
        },
        signal: options.signal,
    });

    return {
        ...response,
        data: normalizeSearchSuggestions(response.data),
    };
};

export const searchHighlights = (query: string, articleId: number | string) =>
    request<{ excerpt?: string }>(`${API_PREFIX}/search/highlights`, {
        params: {
            q: query,
            article_id: articleId,
        },
    });

export const getStats = () =>
    request<StatsOverview>(`${API_PREFIX}/stats/overview`);

export const getStatsChart = (
    type: 'articles' | 'shares' | 'views',
    period: StatsChartPayload['period'] = '30d',
) =>
    request<StatsChartPayload>(`${API_PREFIX}/stats/chart`, {
        params: {
            type,
            period,
        },
    });

export const getPopular = async (
    params: Record<string, unknown> = {},
): Promise<ApiResponse<StatsPopularRow[]>> => {
    const response = await request(`${API_PREFIX}/stats/popular`, { params });

    return {
        ...response,
        data: unwrapDataArray<StatsPopularRow>(response.data),
    };
};

export const getCalendar = async (
    year: number,
    month: number,
): Promise<ApiResponse<Record<string, number>>> => {
    const response = await request(
        `${API_PREFIX}/stats/calendar/${year}/${month}`,
    );

    return {
        ...response,
        data: normalizeCalendarData(response.data),
    };
};

export const getFeedsPerformance = async (): Promise<
    ApiResponse<StatsFeedPerformance[]>
> => {
    const response = await request(`${API_PREFIX}/stats/feeds`);

    return {
        ...response,
        data: unwrapDataArray<StatsFeedPerformance>(response.data),
    };
};

export const getCategoryBreakdown = async (): Promise<
    ApiResponse<StatsCategoryBreakdownItem[]>
> => {
    const response = await request(`${API_PREFIX}/stats/categories`);

    return {
        ...response,
        data: unwrapDataArray<StatsCategoryBreakdownItem>(response.data),
    };
};

export const getBookmarks = () =>
    request(`${API_PREFIX}/bookmarks`).then((response) =>
        toCollectionResponse<ApiArticleListItem>(response),
    );

export const toggleBookmark = (articleId: number | string) =>
    request<ApiBookmarkToggleResponse>(`${API_PREFIX}/bookmarks/${articleId}`, {
        method: 'POST',
    });

export const checkBookmarks = (ids: Array<number | string>) =>
    request(`${API_PREFIX}/bookmarks/check`, {
        method: 'POST',
        data: { ids },
    });

export const shareArticle = (articleId: number | string, platform: string) =>
    request<ApiShareResponse>(`${API_PREFIX}/share/${articleId}`, {
        method: 'POST',
        data: { platform },
    });

export const subscribe = (payload: Record<string, unknown>) =>
    request<ApiNewsletterSubscriptionResponse>(
        `${API_PREFIX}/newsletter/subscribe`,
        {
            method: 'POST',
            data: payload,
        },
    );

export const trackView = (slug: string) =>
    request(`${API_PREFIX}/articles/${slug}/view`, {
        method: 'POST',
    }).catch(() => null);
