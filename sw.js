// Roster Pro Service Worker
// Cache only same-origin static assets. Never intercept authenticated PHP pages,
// API/AJAX calls, or third-party CDN requests.

const CACHE_NAME = 'roster-pro-shell-v6';
const STATIC_ASSETS = [
    './manifest.json'
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
                    .filter(name => name !== CACHE_NAME && name.startsWith('roster'))
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

    const url = new URL(request.url);

    // Critical: do not proxy CDN/cross-origin requests through the Service Worker.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache/intercept dynamic application pages or AJAX endpoints.
    if (url.pathname.endsWith('.php') || url.search) {
        return;
    }

    // Cache only static same-origin resources.
    const isStatic = /\.(?:css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|json)$/i.test(url.pathname);
    if (!isStatic) {
        return;
    }

    event.respondWith(
        caches.match(request).then(cached => {
            const network = fetch(request)
                .then(response => {
                    if (response && response.ok && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
                    }
                    return response;
                });

            if (cached) {
                event.waitUntil(network.catch(() => undefined));
                return cached;
            }

            return network.catch(() => Response.error());
        })
    );
});
