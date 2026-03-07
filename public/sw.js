const CACHE_NAME = 'news-portal-v1';
const STATIC_CACHE = [
    '/',
    '/manifest.json',
    '/offline.html',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

async function getBuildAssets() {
    try {
        const response = await fetch('/build/manifest.json', {
            cache: 'no-store',
        });

        if (!response.ok) {
            return [];
        }

        const manifest = await response.json();
        const assets = new Set(['/build/manifest.json']);

        Object.values(manifest).forEach((entry) => {
            if (entry.file) {
                assets.add(`/build/${entry.file}`);
            }

            if (Array.isArray(entry.css)) {
                entry.css.forEach((asset) => {
                    assets.add(`/build/${asset}`);
                });
            }

            if (Array.isArray(entry.assets)) {
                entry.assets.forEach((asset) => {
                    assets.add(`/build/${asset}`);
                });
            }
        });

        return Array.from(assets);
    } catch {
        return [];
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const buildAssets = await getBuildAssets();

            await cache.addAll([...STATIC_CACHE, ...buildAssets]);
        })(),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys.map((key) => {
                        if (key !== CACHE_NAME) {
                            return caches.delete(key);
                        }

                        return Promise.resolve(false);
                    }),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

async function networkFirst(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const response = await fetch(request);

        if (response && response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        if (request.mode === 'navigate') {
            return cache.match('/offline.html');
        }

        if (request.url.includes('/api/')) {
            return new Response(
                JSON.stringify({
                    message: 'Offline',
                }),
                {
                    status: 503,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                },
            );
        }

        throw new Error('Network request failed');
    }
}

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response && response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const isApiRequest = url.pathname.startsWith('/api/');
    const isStaticAsset =
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font' ||
        STATIC_CACHE.includes(url.pathname);

    if (isApiRequest) {
        event.respondWith(networkFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request));

        return;
    }

    if (isStaticAsset) {
        event.respondWith(cacheFirst(request));
    }
});
