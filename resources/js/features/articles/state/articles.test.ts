import { describe, expect, it } from 'vitest';
import {
    countActiveArticleFilters,
    createArticleFilters,
    mergeArticlesById,
    updateArticleFilters,
} from './articleFilters';

describe('articleFilters helpers', () => {
    it('tracks how many meaningful filters are active', () => {
        const filters = updateArticleFilters(createArticleFilters(), {
            category: 'economy',
            tags: ['markets'],
            dateFrom: '2026-03-01',
            dateTo: '2026-03-09',
            search: 'санкции',
        });

        expect(countActiveArticleFilters(filters)).toBe(4);
    });

    it('merges appended article batches without duplicating ids', () => {
        const currentArticles = [
            { id: 1, title: 'Первая новость' },
            { id: 2, title: 'Вторая новость' },
        ];
        const nextArticles = [
            { id: 2, title: 'Вторая новость' },
            { id: 3, title: 'Третья новость' },
        ];

        expect(mergeArticlesById(currentArticles, nextArticles)).toEqual([
            { id: 1, title: 'Первая новость' },
            { id: 2, title: 'Вторая новость' },
            { id: 3, title: 'Третья новость' },
        ]);
    });
});
