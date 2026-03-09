import { describe, expect, it, vi } from 'vitest';
import { cn, debounce, formatNumber, throttle, timeAgo, toUrl } from './utils';

describe('utils helpers', () => {
    it('merges class names and normalizes href-like values', () => {
        expect(cn('px-4', null, 'px-2', 'text-sm')).toBe('px-2 text-sm');
        expect(toUrl('/stats')).toBe('/stats');
        expect(
            toUrl({
                url: '/search?q=economy',
            }),
        ).toBe('/search?q=economy');
    });

    it('formats numbers and relative times for the public locale', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-09T12:00:00+03:00'));

        expect(formatNumber(12500)).toBe('12\u00a0500');
        expect(formatNumber(null)).toBe('0');
        expect(timeAgo('2026-03-09T11:00:00+03:00')).toBe('1 час назад');
        expect(timeAgo(null)).toBe('только что');

        vi.useRealTimers();
    });

    it('debounces rapid calls down to the latest intent', () => {
        vi.useFakeTimers();

        const spy = vi.fn<(value: string) => void>();
        const debounced = debounce(spy, 200);

        debounced('эко');
        debounced('эконом');
        debounced('экономика');

        vi.advanceTimersByTime(199);
        expect(spy).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(spy).toHaveBeenCalledTimes(1);
        expect(spy).toHaveBeenCalledWith('экономика');

        vi.useRealTimers();
    });

    it('throttles bursts while still delivering the trailing value', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-09T12:00:00+03:00'));

        const spy = vi.fn<(value: string) => void>();
        const throttled = throttle(spy, 200);

        throttled('первая волна');
        throttled('вторая волна');

        expect(spy).toHaveBeenCalledTimes(1);
        expect(spy).toHaveBeenLastCalledWith('первая волна');

        vi.advanceTimersByTime(200);

        expect(spy).toHaveBeenCalledTimes(2);
        expect(spy).toHaveBeenLastCalledWith('вторая волна');

        vi.useRealTimers();
    });
});
