import { get } from 'svelte/store';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ApiArticleListItem } from '@/features/portal/data/api';
import { getArticles } from '@/features/portal/data/api';
import {
    activeFiltersCount,
    filters,
    listState,
    loadArticles,
    resetFilters,
    setCategory,
    setDateRange,
    setSearch,
    toggleTag,
} from './articles.svelte.js';

vi.mock('@/features/portal/data/api', () => ({
    getArticles: vi.fn(),
}));

function buildArticle(
    overrides: Partial<ApiArticleListItem> = {},
): ApiArticleListItem {
    return {
        id: overrides.id ?? 1,
        title: overrides.title ?? 'Главная новость дня',
        slug: overrides.slug ?? 'glavnaya-novost-dnya',
        category: overrides.category ?? {
            id: 7,
            name: 'Экономика',
            slug: 'economy',
        },
        tags: overrides.tags ?? [],
        ...overrides,
    };
}

describe('articles store', () => {
    const mockedGetArticles = vi.mocked(getArticles);

    beforeEach(() => {
        mockedGetArticles.mockReset();
        resetFilters();
    });

    it('tracks the active feed filters a user applies', () => {
        setSearch('санкции');
        setCategory('economy');
        toggleTag('markets');
        setDateRange('2026-03-01', '2026-03-09');

        expect(get(filters)).toMatchObject({
            search: 'санкции',
            category: 'economy',
            tags: ['markets'],
            date_from: '2026-03-01',
            date_to: '2026-03-09',
            page: 1,
            sort: 'latest',
        });
        expect(get(activeFiltersCount)).toBe(4);

        toggleTag('markets');

        expect(get(filters).tags).toEqual([]);
        expect(get(activeFiltersCount)).toBe(3);

        resetFilters();

        expect(get(filters)).toMatchObject({
            search: '',
            category: null,
            tags: [],
            date_from: null,
            date_to: null,
            page: 1,
            sort: 'latest',
        });
        expect(get(activeFiltersCount)).toBe(0);
    });

    it('appends the next page without duplicating cards users already saw', async () => {
        mockedGetArticles.mockResolvedValueOnce({
            data: [
                buildArticle({
                    id: 1,
                    slug: 'glavnaya-novost-dnya',
                }),
            ],
            meta: {
                current_page: 1,
                last_page: 2,
                total: 2,
            },
            status: 200,
        });

        await loadArticles();

        mockedGetArticles.mockResolvedValueOnce({
            data: [
                buildArticle({
                    id: 1,
                    slug: 'glavnaya-novost-dnya',
                }),
                buildArticle({
                    id: 2,
                    slug: 'vtoraya-volna-novostey',
                    title: 'Вторая волна новостей',
                }),
            ],
            meta: {
                current_page: 2,
                last_page: 2,
                total: 2,
            },
            status: 200,
        });

        await loadArticles(get(filters), { append: true });

        expect(get(listState)).toMatchObject({
            loading: false,
            error: null,
        });
        expect(get(listState).articles.map((article) => article.id)).toEqual([
            1, 2,
        ]);
    });
});
