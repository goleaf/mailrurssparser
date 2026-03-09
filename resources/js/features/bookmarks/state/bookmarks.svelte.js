import {
    derived,
    get,
    readonly,
    writable,
} from 'svelte/store';
import * as api from '@/features/portal/data/api';

function replaceBookmarkIds(nextBookmarkIds) {
    bookmarkIdsStore.set(nextBookmarkIds);
}

const bookmarkIdsStore = writable([]);

export const bookmarkIds = readonly(bookmarkIdsStore);

export const bookmarkCount = derived(bookmarkIdsStore, ($bookmarkIds) => $bookmarkIds.length);

export async function loadBookmarks() {
    const res = await api.getBookmarks();

    replaceBookmarkIds(res.data.map((bookmark) => bookmark.id));
}

export async function toggleBookmark(articleId) {
    const res = await api.toggleBookmark(articleId);
    const currentBookmarkIds = get(bookmarkIdsStore);

    if (res.data.bookmarked) {
        if (!currentBookmarkIds.includes(articleId)) {
            replaceBookmarkIds([...currentBookmarkIds, articleId]);
        }
    } else {
        replaceBookmarkIds(
            currentBookmarkIds.filter((id) => id !== articleId),
        );
    }

    return res.data;
}

export function isBookmarked(id) {
    return get(bookmarkIdsStore).includes(id);
}
