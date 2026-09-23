const SHELL = 'digsignage-player-shell-v3';
const ASSETS = 'digsignage-assets-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(caches.open(SHELL));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== SHELL && key !== ASSETS)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(SHELL).then((cache) => cache.put(request, copy));
                    }

                    return response;
                });
            }),
        );

        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && (url.pathname === '/player' || url.pathname.startsWith('/player'))) {
                    const copy = response.clone();
                    caches.open(SHELL).then((cache) => cache.put(request, copy));
                }

                return response;
            })
            .catch(async () => {
                const cached = await caches.match(request);

                if (cached) {
                    return cached;
                }

                if (request.mode === 'navigate') {
                    const shell = await caches.match('/player');

                    if (shell) {
                        return shell;
                    }
                }

                return Response.error();
            }),
    );
});
