import {
    onDestroy,
    onMount,
} from 'svelte';

/**
 * @template T
 * @param {() => Promise<T>} fetchFn
 * @param {number} [intervalMs=60000]
 * @returns {{ readonly data: T | null, readonly lastFetched: number | null }}
 */
export function usePolling(fetchFn, intervalMs = 60000) {
    let timer;
    let active = false;
    /** @type {T | null} */
    let data = $state(null);
    /** @type {number | null} */
    let lastFetched = $state(null);

    function clearTimer() {
        if (timer !== undefined && timer !== null && typeof window !== 'undefined') {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    async function run() {
        try {
            const result = await fetchFn();
            if (!active) {
                return;
            }
            data = result;
            lastFetched = Date.now();
        } catch (error) {
            void error;
        } finally {
            if (typeof window !== 'undefined' && active) {
                clearTimer();
                timer = window.setTimeout(run, intervalMs);
            }
        }
    }

    onMount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        active = true;
        void run();
    });

    onDestroy(() => {
        active = false;
        clearTimer();
    });

    return {
        get data() {
            return data;
        },
        get lastFetched() {
            return lastFetched;
        },
    };
}
