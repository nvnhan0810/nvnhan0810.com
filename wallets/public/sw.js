const CACHE = 'mywallet-v3';
const PRECACHE = ['/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== location.origin) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok && url.pathname === '/') {
                    const clone = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request).then((r) => {
                    if (r) return r;
                    // Only fallback to '/' for navigation requests (HTML)
                    if (event.request.mode === 'navigate') {
                        return caches.match('/').then(cached => {
                            return cached || new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
                        });
                    }
                    return new Response('', { status: 404, statusText: 'Not Found' });
                });
            })
    );
});
