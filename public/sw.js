const VERSION = 'wsm-v1';
const CORE_CACHE = `wsm-core-${VERSION}`;
const RUNTIME_CACHE = `wsm-runtime-${VERSION}`;
const OFFLINE_URL = '/offline';

const CORE_URLS = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CORE_CACHE).then((cache) => cache.addAll(CORE_URLS)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('wsm-') && ![CORE_CACHE, RUNTIME_CACHE].includes(k)).map((k) => caches.delete(k)),
            ))
            .then(() => self.clients.claim()),
    );
});

// Offline-first for static GET assets, network-first for navigations.
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Never cache non-GET or Livewire/API/ajax calls.
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (
        url.pathname.startsWith('/livewire') ||
        url.pathname.startsWith('/api') ||
        url.pathname === '/livewire/update' ||
        request.headers.get('X-Livewire') ||
        request.headers.get('x-livewire-id')
    ) {
        return;
    }

    const isStatic = /\.(?:css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|otf|eot|json|webmanifest)$/.test(url.pathname);

    if (isStatic) {
        event.respondWith(
            caches.match(request, { ignoreSearch: false }).then(( cached ) =>
                cached ||
                fetch(request).then((response) => {
                    const copy = response.clone();
                    caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                    return response;
                }).catch(() => caches.match(request, { ignoreSearch: false })),
            ),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(OFFLINE_URL)),
        );
    }
});
