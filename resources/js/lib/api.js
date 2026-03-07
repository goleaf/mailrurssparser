const API_PREFIX = '/api/v1';

function getOrigin() {
    if (typeof window !== 'undefined') {
        return window.location.origin;
    }

    return 'http://localhost';
}

function buildUrl(path, params = {}) {
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

function getCsrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function request(path, options = {}) {
    const {
        method = 'GET',
        params = {},
        data,
        headers = {},
    } = options;

    const response = await fetch(buildUrl(path, params), {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(data !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(getCsrfToken() ? { 'X-CSRF-TOKEN': getCsrfToken() } : {}),
            ...headers,
        },
        body: data !== undefined ? JSON.stringify(data) : undefined,
    });

    const text = await response.text();
    let payload = null;

    if (text) {
        try {
            payload = JSON.parse(text);
        } catch {
            payload = { message: text };
        }
    }

    if (!response.ok) {
        const error = new Error(
            payload?.message ?? `Request failed with status ${response.status}`,
        );

        error.status = response.status;
        error.response = {
            data: payload,
            status: response.status,
        };

        throw error;
    }

    return {
        data: payload,
        status: response.status,
    };
}

export const getArticles = (params = {}) =>
    request(`${API_PREFIX}/articles`, { params });

export const getArticle = (slug) => request(`${API_PREFIX}/articles/${slug}`);

export const getRelated = (slug) =>
    request(`${API_PREFIX}/articles/${slug}/related`);

export const getSimilar = (slug) =>
    request(`${API_PREFIX}/articles/${slug}/similar`);

export const getFeatured = () => request(`${API_PREFIX}/articles/featured`);

export const getBreaking = () => request(`${API_PREFIX}/articles/breaking`);

export const getTrending = () => request(`${API_PREFIX}/articles/trending`);

export const getCategories = (params = {}) =>
    request(`${API_PREFIX}/categories`, { params });

export const getCategory = (slug) => request(`${API_PREFIX}/categories/${slug}`);

export const getCategoryArticles = (slug, params = {}) =>
    request(`${API_PREFIX}/categories/${slug}/articles`, { params });

export const getPinnedArticles = (slug) =>
    request(`${API_PREFIX}/category/${slug}/pinned`);

export const getTags = (params = {}) =>
    request(`${API_PREFIX}/tags`, { params });

export const getTag = (slug) => request(`${API_PREFIX}/tags/${slug}`);

export const getTagArticles = (slug, params = {}) =>
    request(`${API_PREFIX}/tags/${slug}/articles`, { params });

export const search = (query, params = {}) =>
    request(`${API_PREFIX}/search`, {
        params: {
            q: query,
            ...params,
        },
    });

export const suggestSearch = (query) =>
    request(`${API_PREFIX}/search/suggest`, {
        params: {
            q: query,
        },
    });

export const searchHighlights = (query, articleId) =>
    request(`${API_PREFIX}/search/highlights`, {
        params: {
            q: query,
            article_id: articleId,
        },
    });

export const getStats = () => request(`${API_PREFIX}/stats/overview`);

export const getStatsChart = (type, period = '30d') =>
    request(`${API_PREFIX}/stats/chart`, {
        params: {
            type,
            period,
        },
    });

export const getPopular = (params = {}) =>
    request(`${API_PREFIX}/stats/popular`, { params });

export const getCalendar = (year, month) =>
    request(`${API_PREFIX}/stats/calendar/${year}/${month}`);

export const getFeedsPerformance = () =>
    request(`${API_PREFIX}/stats/feeds`);

export const getCategoryBreakdown = () =>
    request(`${API_PREFIX}/stats/categories`);

export const getBookmarks = () => request(`${API_PREFIX}/bookmarks`);

export const toggleBookmark = (articleId) =>
    request(`${API_PREFIX}/bookmarks/${articleId}`, {
        method: 'POST',
    });

export const checkBookmarks = (ids) =>
    request(`${API_PREFIX}/bookmarks/check`, {
        method: 'POST',
        data: { ids },
    });

export const shareArticle = (articleId, platform) =>
    request(`${API_PREFIX}/share/${articleId}`, {
        method: 'POST',
        data: { platform },
    });

export const subscribe = (payload) =>
    request(`${API_PREFIX}/newsletter/subscribe`, {
        method: 'POST',
        data: payload,
    });

export const trackView = (slug) =>
    request(`${API_PREFIX}/articles/${slug}/view`, {
        method: 'POST',
    }).catch(() => null);
