import type { FlipParams } from 'svelte/animate';
import { readable } from 'svelte/store';
import type { Readable } from 'svelte/store';
import type { FadeParams, FlyParams, SlideParams } from 'svelte/transition';

type MotionTiming = {
    delay?: number;
    duration?: unknown;
};

const REDUCED_MOTION_MEDIA_QUERY = '(prefers-reduced-motion: reduce)';

function withoutMotion<TOptions extends MotionTiming>(options: TOptions): TOptions {
    return {
        ...options,
        delay: 0,
        duration: 0,
    };
}

export const prefersReducedMotion: Readable<boolean> = readable(
    false,
    (set) => {
        if (typeof window === 'undefined') {
            return;
        }

        const mediaQuery = window.matchMedia(REDUCED_MOTION_MEDIA_QUERY);
        const syncPreference = (): void => {
            set(mediaQuery.matches);
        };

        syncPreference();
        mediaQuery.addEventListener('change', syncPreference);

        return () => {
            mediaQuery.removeEventListener('change', syncPreference);
        };
    },
);

export function resolveFadeTransition(
    reduceMotion: boolean,
    options: FadeParams,
): FadeParams {
    return reduceMotion ? withoutMotion(options) : options;
}

export function resolveFlipAnimation(
    reduceMotion: boolean,
    options: FlipParams,
): FlipParams {
    return reduceMotion ? withoutMotion(options) : options;
}

export function resolveFlyTransition(
    reduceMotion: boolean,
    options: FlyParams,
): FlyParams {
    if (!reduceMotion) {
        return options;
    }

    return {
        ...withoutMotion(options),
        x: 0,
        y: 0,
    };
}

export function resolveSlideTransition(
    reduceMotion: boolean,
    options: SlideParams,
): SlideParams {
    return reduceMotion ? withoutMotion(options) : options;
}
