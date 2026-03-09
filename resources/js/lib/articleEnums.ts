export const filterBarArticleContentTypeOptions = [
    { value: null, label: 'Все' },
    { value: 'news', label: '📰 Новости' },
    { value: 'article', label: '📝 Статьи' },
    { value: 'opinion', label: '💬 Мнения' },
    { value: 'analysis', label: '📊 Аналитика' },
    { value: 'interview', label: '🎤 Интервью' },
] as const;

export const searchArticleContentTypeOptions = [
    { value: '', label: 'Все форматы' },
    { value: 'news', label: 'Новости' },
    { value: 'article', label: 'Статьи' },
    { value: 'opinion', label: 'Мнения' },
    { value: 'analysis', label: 'Аналитика' },
    { value: 'interview', label: 'Интервью' },
] as const;

const articleContentTypeLabels: Record<string, string> = {
    news: 'Новость',
    article: 'Статья',
    opinion: 'Мнение',
    analysis: 'Аналитика',
    interview: 'Интервью',
};

const articleContentTypeFilterLabels: Record<string, string> = {
    news: 'Новости',
    article: 'Статьи',
    opinion: 'Мнения',
    analysis: 'Аналитика',
    interview: 'Интервью',
};

export function getArticleContentTypeLabel(value?: string | null): string {
    if (!value) {
        return '';
    }

    return articleContentTypeLabels[value] ?? value;
}

export function getArticleContentTypeFilterLabel(
    value?: string | null,
): string {
    if (!value) {
        return '';
    }

    return articleContentTypeFilterLabels[value] ?? value;
}
