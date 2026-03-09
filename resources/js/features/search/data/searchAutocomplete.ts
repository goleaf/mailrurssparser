export type SearchSuggestionCategory = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
    icon?: string | null;
};

export type SearchSuggestionTag = {
    id: number | string;
    name: string;
    slug: string;
    color?: string | null;
};

export type SearchSuggestionArticle = {
    id: number | string;
    title: string;
    slug: string;
    publishedAt?: string | null;
};

export type SearchSuggestions = {
    articles: SearchSuggestionArticle[];
    categories: SearchSuggestionCategory[];
    tags: SearchSuggestionTag[];
};

export type SearchAutocompleteItem =
    | {
          index: number;
          id: 'search';
          section: 'search';
          kind: 'search';
          label: string;
          query: string;
      }
    | {
          index: number;
          id: `article-${string | number}`;
          section: 'articles';
          kind: 'article';
          label: string;
          article: SearchSuggestionArticle;
      }
    | {
          index: number;
          id: `category-${string | number}`;
          section: 'categories';
          kind: 'category';
          label: string;
          category: SearchSuggestionCategory;
      }
    | {
          index: number;
          id: `tag-${string | number}`;
          section: 'tags';
          kind: 'tag';
          label: string;
          tag: SearchSuggestionTag;
      };

export const emptySearchSuggestions: SearchSuggestions = {
    articles: [],
    categories: [],
    tags: [],
};

export function hasSearchSuggestions(suggestions: SearchSuggestions): boolean {
    return (
        suggestions.articles.length > 0 ||
        suggestions.categories.length > 0 ||
        suggestions.tags.length > 0
    );
}

export function buildSearchAutocompleteItems(
    query: string,
    suggestions: SearchSuggestions,
): SearchAutocompleteItem[] {
    const normalized = query.trim();

    if (normalized.length < 2) {
        return [];
    }

    let index = 0;

    return [
        {
            index: index++,
            id: 'search',
            section: 'search',
            kind: 'search',
            label: normalized,
            query: normalized,
        },
        ...suggestions.articles.map(
            (article): SearchAutocompleteItem => ({
                index: index++,
                id: `article-${article.id}`,
                section: 'articles',
                kind: 'article',
                label: article.title,
                article,
            }),
        ),
        ...suggestions.categories.map(
            (category): SearchAutocompleteItem => ({
                index: index++,
                id: `category-${category.id}`,
                section: 'categories',
                kind: 'category',
                label: category.name,
                category,
            }),
        ),
        ...suggestions.tags.map(
            (tag): SearchAutocompleteItem => ({
                index: index++,
                id: `tag-${tag.id}`,
                section: 'tags',
                kind: 'tag',
                label: tag.name,
                tag,
            }),
        ),
    ];
}

export function highlightAutocompleteText(
    text: string,
    query: string,
): Array<{ text: string; highlighted: boolean }> {
    const normalizedQuery = query.trim();

    if (text === '' || normalizedQuery === '') {
        return [{ text, highlighted: false }];
    }

    const escapedQuery = normalizedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`(${escapedQuery})`, 'giu');
    const segments = text.split(pattern).filter((segment) => segment !== '');

    if (segments.length === 0) {
        return [{ text, highlighted: false }];
    }

    return segments.map((segment) => ({
        text: segment,
        highlighted:
            segment.toLocaleLowerCase() === normalizedQuery.toLocaleLowerCase(),
    }));
}
