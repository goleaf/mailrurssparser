import { render, screen, within } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'svelte';
import { describe, expect, it } from 'vitest';
import type { SearchSuggestions } from '@/features/search';
import SearchHeroPanel from './SearchHeroPanel.svelte';

type SearchHeroPanelProps = ComponentProps<typeof SearchHeroPanel>;

function createProps(
    overrides: Partial<SearchHeroPanelProps> = {},
): SearchHeroPanelProps {
    const suggestions: SearchSuggestions = {
        articles: [
            {
                id: 101,
                title: 'Новые санкции для импорта',
                slug: 'novye-sanktsii-dlya-importa',
                published_at: '2026-03-09T07:00:00+03:00',
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

    return {
        query: '',
        categories: [
            {
                id: 7,
                name: 'Экономика',
                slug: 'economy',
            },
        ],
        selectedCategory: null,
        selectedContentType: null,
        selectedDateFrom: null,
        selectedDateTo: null,
        selectedSort: 'relevance',
        contentTypeOptions: [
            {
                value: '',
                label: 'Все форматы',
            },
            {
                value: 'analysis',
                label: 'Аналитика',
            },
        ],
        sortTabs: [
            {
                key: 'relevance',
                label: 'По релевантности',
            },
            {
                key: 'latest',
                label: 'Сначала новые',
            },
        ],
        searchSnapshots: [
            {
                label: 'Материалов',
                value: 124,
                caption: 'В открытой выдаче',
            },
        ],
        suggestions,
        suggestionsLoading: false,
        activeSuggestionIndex: -1,
        ...overrides,
    };
}

describe('SearchHeroPanel', () => {
    it('lets users refine a search and pick a suggestion without touching internal state', async () => {
        const user = userEvent.setup();
        const props = createProps();
        const submittedQueries: string[] = [];
        const selectedRoutes = {
            article: null as string | null,
            category: null as string | null,
            tag: null as string | null,
        };

        const rendered = render(SearchHeroPanel, {
            props,
            events: {
                queryinput: (event) => {
                    props.query = event.detail;
                },
                clear: () => {
                    props.query = '';
                },
                search: (event) => {
                    submittedQueries.push(event.detail);
                },
                categorychange: (event) => {
                    props.selectedCategory = event.detail;
                },
                contenttypechange: (event) => {
                    props.selectedContentType = event.detail;
                },
                articleselect: (event) => {
                    selectedRoutes.article = event.detail;
                },
                categoryselect: (event) => {
                    selectedRoutes.category = event.detail;
                },
                tagselect: (event) => {
                    selectedRoutes.tag = event.detail;
                },
            },
        });

        await user.type(
            screen.getByPlaceholderText('Например: санкции, спорт, интервью'),
            'санкции',
        );
        await rendered.rerender({ ...props });

        expect(
            screen.getByPlaceholderText('Например: санкции, спорт, интервью'),
        ).toHaveValue('санкции');
        expect(
            screen.getByRole('button', { name: 'Очистить поиск' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: /Искать по запросу/i }),
        ).toBeInTheDocument();

        await user.selectOptions(screen.getByLabelText('Рубрика'), 'economy');
        await rendered.rerender({ ...props });
        expect(screen.getByLabelText('Рубрика')).toHaveValue('economy');

        await user.selectOptions(screen.getByLabelText('Формат'), 'analysis');
        await rendered.rerender({ ...props });
        expect(screen.getByLabelText('Формат')).toHaveValue('analysis');

        const autocompleteList = screen.getByRole('listbox', {
            name: 'Подсказки поиска',
        });

        await user.click(
            within(autocompleteList).getByRole('option', {
                name: /Новые санкции для импорта/i,
            }),
        );
        await user.click(
            within(autocompleteList).getByRole('option', { name: /Экономика/i }),
        );
        await user.click(
            within(autocompleteList).getByRole('option', { name: /^# Импорт$/i }),
        );
        await user.click(
            within(autocompleteList).getByRole('option', {
                name: /Искать по запросу/i,
            }),
        );

        expect(selectedRoutes).toEqual({
            article: 'novye-sanktsii-dlya-importa',
            category: 'economy',
            tag: 'import',
        });
        expect(submittedQueries).toContain('санкции');

        await user.click(screen.getByRole('button', { name: 'Очистить поиск' }));
        await rendered.rerender({ ...props });

        expect(
            screen.getByPlaceholderText('Например: санкции, спорт, интервью'),
        ).toHaveValue('');
        expect(
            screen.queryByRole('button', { name: 'Очистить поиск' }),
        ).not.toBeInTheDocument();
    });
});
