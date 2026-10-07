// Roster Pro service worker — static assets only
const CACHE_NAME = 'rosterpro-static-v1.2';
const STATIC_ASSETS = [
    './manifest.json',
    './assets/icons/roster-pro.svg'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(
                names
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    let url;
    try {
        url = new URL(request.url);
    } catch (_) {
        return;
    }

    // Never intercept dynamic/authenticated application pages.
    if (
        url.origin !== self.location.origin ||
        url.pathname.endsWith('/index.php') ||
        url.pathname.endsWith('.php') ||
        url.searchParams.has('c') ||
        request.mode === 'navigate'
    ) {
        return;
    }

    const isStaticAsset =
        /\.(?:css|js|svg|png|jpg|jpeg|webp|ico|woff2?|ttf)$/i.test(url.pathname) ||
        url.pathname.endsWith('/manifest.json');

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then(cached => {
            const network = fetch(request)
                .then(response => {
                    if (response && response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => null);

            if (cached) {
                event.waitUntil(network);
                return cached;
            }

            return network.then(response => {
                if (response instanceof Response) {
                    return response;
                }

                return new Response('', {
                    status: 503,
                    statusText: 'Offline'
                });
            });
        })
    );
});
