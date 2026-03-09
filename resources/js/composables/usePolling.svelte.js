/**
 * @template T
 * @param {() => Promise<T>} fetchFn
 * @param {number} [intervalMs=60000]
 * @returns {{ readonly data: T | null, readonly lastFetched: number | null }}
 */
export function usePolling(fetchFn, intervalMs = 60000) {
    let timer;
    /** @type {T | null} */
    let data = $state(null);
    /** @type {number | null} */
    let lastFetched = $state(null);

    async function run() {
        try {
            const result = await fetchFn();
            data = result;
            lastFetched = Date.now();
        } catch (error) {
            void error;
        } finally {
            if (typeof window !== 'undefined') {
                timer = window.setTimeout(run, intervalMs);
            }
        }
    }

    $effect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        void run();

        return () => {
            if (timer) {
                window.clearTimeout(timer);
            }
        };
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
