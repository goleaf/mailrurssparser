import { toUrl } from '@/lib/utils';
import type { HrefLike } from '@/types/inertia';

export type CurrentUrlState = {
    isCurrentUrl: (
        urlToCheck: HrefLike,
        currentUrl: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: HrefLike,
        currentUrl: string,
    ) => boolean;
    whenCurrentUrl: <TIfTrue, TIfFalse = null>(
        urlToCheck: HrefLike,
        currentUrl: string,
        ifTrue: TIfTrue,
        ifFalse?: TIfFalse,
    ) => TIfTrue | TIfFalse;
};

export function currentPath(url: string): string {
    const origin =
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin;

    try {
        return new URL(url, origin).pathname;
    } catch {
        return url;
    }
}

export function currentUrlState(): CurrentUrlState {
    function isCurrentUrl(
        urlToCheck: HrefLike,
        current: string,
        startsWith: boolean = false,
    ): boolean {
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? current.startsWith(path) : path === current;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);
            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    }

    function isCurrentOrParentUrl(
        urlToCheck: HrefLike,
        current: string,
    ): boolean {
        return isCurrentUrl(urlToCheck, current, true);
    }

    function whenCurrentUrl<TIfTrue, TIfFalse = null>(
        urlToCheck: HrefLike,
        current: string,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse {
        return isCurrentUrl(urlToCheck, current) ? ifTrue : ifFalse;
    }

    return {
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
