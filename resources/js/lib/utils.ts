import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type HrefLike = string | { url: string };

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: HrefLike): string {
    return typeof href === 'string' ? href : href.url;
}

export function debounce<TArgs extends unknown[]>(
    fn: (...args: TArgs) => void,
    ms: number,
): (...args: TArgs) => void {
    let timeoutId: ReturnType<typeof setTimeout> | undefined;

    return (...args: TArgs): void => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            fn(...args);
        }, ms);
    };
}

export function throttle<TArgs extends unknown[]>(
    fn: (...args: TArgs) => void,
    ms: number,
): (...args: TArgs) => void {
    let lastRunAt = 0;
    let timeoutId: ReturnType<typeof setTimeout> | undefined;

    return (...args: TArgs): void => {
        const now = Date.now();
        const remaining = ms - (now - lastRunAt);

        if (remaining <= 0) {
            lastRunAt = now;
            fn(...args);

            return;
        }

        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            lastRunAt = Date.now();
            fn(...args);
        }, remaining);
    };
}

export function formatNumber(value: number | null | undefined): string {
    return new Intl.NumberFormat('ru-RU').format(Number(value ?? 0));
}

export function timeAgo(
    value: string | number | Date | null | undefined,
    locale = 'ru-RU',
): string {
    if (!value) {
        return 'только что';
    }

    const date = value instanceof Date ? value : new Date(value);
    const diffInSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(locale, {
        numeric: 'auto',
    });

    const intervals = [
        { unit: 'year', seconds: 60 * 60 * 24 * 365 },
        { unit: 'month', seconds: 60 * 60 * 24 * 30 },
        { unit: 'week', seconds: 60 * 60 * 24 * 7 },
        { unit: 'day', seconds: 60 * 60 * 24 },
        { unit: 'hour', seconds: 60 * 60 },
        { unit: 'minute', seconds: 60 },
    ] as const;

    for (const interval of intervals) {
        const valueForUnit = Math.round(diffInSeconds / interval.seconds);

        if (Math.abs(valueForUnit) >= 1) {
            return formatter.format(
                valueForUnit,
                interval.unit as Intl.RelativeTimeFormatUnit,
            );
        }
    }

    return formatter.format(diffInSeconds, 'second');
}
