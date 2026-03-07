import * as api from '@/lib/api';

export let bookmarkIds = $state([]);

export async function loadBookmarks() {
    const res = await api.getBookmarks();

    bookmarkIds = res.data.data.map((b) => b.id);
}

export async function toggleBookmark(articleId) {
    const res = await api.toggleBookmark(articleId);

    if (res.data.bookmarked) {
        if (!bookmarkIds.includes(articleId)) {
            bookmarkIds.push(articleId);
        }
    } else {
        bookmarkIds = bookmarkIds.filter((id) => id !== articleId);
    }

    return res.data;
}

export const isBookmarked = (id) => $derived(bookmarkIds.includes(id));
