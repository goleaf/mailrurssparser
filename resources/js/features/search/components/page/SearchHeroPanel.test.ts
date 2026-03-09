import { describe, expect, it } from 'vitest';
import {
    buildSearchUrl,
    canSubmitSearch,
    normalizeSearchQuery,
} from './searchHeroState';

describe('searchHeroState helpers', () => {
    it('normalizes whitespace before the query reaches the server route', () => {
        expect(normalizeSearchQuery('  санкции   и   импорт  ')).toBe(
            'санкции и импорт',
        );
    });

    it('requires a meaningful search phrase before submission', () => {
        expect(canSubmitSearch(' а ')).toBe(false);
        expect(canSubmitSearch(' импорт ')).toBe(true);
    });

    it('builds a shareable search url from the active filters', () => {
        expect(
            buildSearchUrl('  импорт ', {
                category: 'economy',
                contentType: 'analysis',
                sort: 'latest',
            }),
        ).toBe('/search?q=%D0%B8%D0%BC%D0%BF%D0%BE%D1%80%D1%82&category=economy&content_type=analysis&sort=latest');
    });
});
