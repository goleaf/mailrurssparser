export function normalizeSearchQuery(query: string): string {
    return query.replace(/\s+/g, ' ').trim();
}

export function canSubmitSearch(query: string): boolean {
    return normalizeSearchQuery(query).length >= 2;
}

export function buildSearchUrl(
    query: string,
    filters: {
        category?: string | null;
        contentType?: string | null;
        sort?: string | null;
    } = {},
): string {
    const params = new URLSearchParams();
    const normalizedQuery = normalizeSearchQuery(query);

    if (normalizedQuery !== '') {
        params.set('q', normalizedQuery);
    }

    if (filters.category) {
        params.set('category', filters.category);
    }

    if (filters.contentType) {
        params.set('content_type', filters.contentType);
    }

    if (filters.sort) {
        params.set('sort', filters.sort);
    }

    const queryString = params.toString();

    return queryString === '' ? '/search' : `/search?${queryString}`;
}
