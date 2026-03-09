import { router } from '@inertiajs/svelte';

type PublicVisitOptions = {
    preserveScroll?: boolean;
    preserveState?: boolean;
    replace?: boolean;
};

function encodeSegment(value: string): string {
    return encodeURIComponent(value);
}

export function homeUrl(): string {
    return '/';
}

export function categoryUrl(slug: string): string {
    return `/category/${encodeSegment(slug)}`;
}

export function tagUrl(slug: string): string {
    return `/tag/${encodeSegment(slug)}`;
}

export function articleUrl(slug: string): string {
    return `/articles/${encodeSegment(slug)}`;
}

export function searchUrl(query?: string | null): string {
    const normalized = query?.trim() ?? '';

    if (normalized === '') {
        return '/search';
    }

    const params = new URLSearchParams({
        q: normalized,
    });

    return `/search?${params.toString()}`;
}

export function bookmarksUrl(): string {
    return '/bookmarks';
}

export function statsUrl(): string {
    return '/stats';
}

export function aboutUrl(): string {
    return '/about';
}

export function contactUrl(): string {
    return '/contact';
}

export function privacyUrl(): string {
    return '/privacy';
}

export function absolutePublicUrl(url: string): string | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    return new URL(url, window.location.origin).toString();
}

export function searchQueryFromUrl(url: string): string {
    try {
        return new URL(url, 'https://public-route.local').searchParams.get('q')?.trim() ?? '';
    } catch {
        return '';
    }
}

export function isPublicInertiaPath(pathname: string): boolean {
    return (
        pathname === '/' ||
        pathname === '/search' ||
        pathname === '/bookmarks' ||
        pathname === '/stats' ||
        pathname === '/about' ||
        pathname === '/contact' ||
        pathname === '/privacy' ||
        pathname.startsWith('/category/') ||
        pathname.startsWith('/tag/') ||
        pathname.startsWith('/articles/')
    );
}

export function visitPublic(
    url: string,
    options: PublicVisitOptions = {},
): void {
    router.visit(url, {
        preserveScroll: options.preserveScroll ?? false,
        preserveState: options.preserveState ?? false,
        replace: options.replace ?? false,
    });
}

export function replacePublic(
    url: string,
    options: Omit<PublicVisitOptions, 'replace'> = {},
): void {
    router.replace({
        url,
        preserveScroll: options.preserveScroll ?? true,
        preserveState: options.preserveState ?? true,
    });
}
