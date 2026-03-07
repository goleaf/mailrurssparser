import * as api from '@/lib/api';

export const bookmarkIds = $state([]);

export async function loadBookmarks() {
    const res = await api.getBookmarks();

    bookmarkIds.splice(0, bookmarkIds.length, ...res.data.data.map((b) => b.id));
}

export async function toggleBookmark(articleId) {
    const res = await api.toggleBookmark(articleId);

    if (res.data.bookmarked) {
        if (!bookmarkIds.includes(articleId)) {
            bookmarkIds.push(articleId);
        }
    } else {
        const bookmarkIndex = bookmarkIds.indexOf(articleId);

        if (bookmarkIndex !== -1) {
            bookmarkIds.splice(bookmarkIndex, 1);
        }
    }

    return res.data;
}

export function isBookmarked(id) {
    return bookmarkIds.includes(id);
}
