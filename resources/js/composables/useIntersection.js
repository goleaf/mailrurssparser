export function useIntersection(callback, options = {}) {
    return (node) => {
        if (
            typeof window === 'undefined' ||
            typeof IntersectionObserver === 'undefined'
        ) {
            return {
                destroy() {},
            };
        }

        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    callback(entry);
                }
            }
        }, options);

        observer.observe(node);

        return {
            destroy() {
                observer.disconnect();
            },
        };
    };
}
