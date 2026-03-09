export type PortalTheme = 'light' | 'dark';

export function isPortalTheme(value: string | null | undefined): value is PortalTheme {
    return value === 'light' || value === 'dark';
}

export function resolvePortalTheme(
    storedTheme: string | null | undefined,
    prefersDark: boolean,
): PortalTheme {
    if (isPortalTheme(storedTheme)) {
        return storedTheme;
    }

    return prefersDark ? 'dark' : 'light';
}

export function nextPortalTheme(theme: PortalTheme): PortalTheme {
    return theme === 'dark' ? 'light' : 'dark';
}
