import { describe, expect, it } from 'vitest';
import {
    buildSearchAutocompleteItems,
    emptySearchSuggestions,
    hasSearchSuggestions,
    highlightAutocompleteText,
} from './searchAutocomplete';

describe('searchAutocomplete helpers', () => {
    it('waits for a meaningful query and keeps the result order stable', () => {
        expect(
            buildSearchAutocompleteItems('с', emptySearchSuggestions),
        ).toEqual([]);
        expect(hasSearchSuggestions(emptySearchSuggestions)).toBe(false);

        const suggestions = {
            articles: [
                {
                    id: 101,
                    title: 'Новые санкции для импорта',
                    slug: 'novye-sanktsii-dlya-importa',
                },
            ],
            categories: [
                {
                    id: 7,
                    name: 'Экономика',
                    slug: 'economy',
                },
            ],
            tags: [
                {
                    id: 17,
                    name: 'Импорт',
                    slug: 'import',
                },
            ],
        };

        const items = buildSearchAutocompleteItems('санкции', suggestions);

        expect(hasSearchSuggestions(suggestions)).toBe(true);
        expect(items.map((item) => item.kind)).toEqual([
            'search',
            'article',
            'category',
            'tag',
        ]);
    });

    it('highlights matching fragments without dropping the surrounding text', () => {
        expect(
            highlightAutocompleteText('Новые Санкции ЕС', 'санкции'),
        ).toEqual([
            { text: 'Новые ', highlighted: false },
            { text: 'Санкции', highlighted: true },
            { text: ' ЕС', highlighted: false },
        ]);
    });
});
