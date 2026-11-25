/*
 * Offline support. Pages you have opened (and the ones you chose to save) stay
 * readable without a connection. Anything that changes data still needs one.
 */
const VERSION = 'coach-v1';
const PAGES = `${VERSION}-pages`;
const ASSETS = `${VERSION}-assets`;

// Never keep these: they are actions, downloads or one-off lookups.
const SKIP = [
    /^\/pdf/,
    /^\/search/,
    /^\/team\/backup/,
    /^\/logout/,
    /^\/pdf\/session/,
    /^\/up$/,
];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// Logging out removes everything that was saved for the signed-in person.
self.addEventListener('message', (event) => {
    if (event.data === 'clear') {
        event.waitUntil(caches.delete(PAGES));
    }
});

/** Inertia page data and full HTML share a URL, so they are stored under different keys. */
function pageKey(request) {
    const url = new URL(request.url);

    return new Request(
        `${url.origin}${url.pathname}${url.search}#${request.headers.has('X-Inertia') ? 'inertia' : 'html'}`,
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/fonts/')
    ) {
        event.respondWith(
            caches.open(ASSETS).then(async (cache) => {
                const hit = await cache.match(request);

                if (hit) {
                    return hit;
                }

                const response = await fetch(request);

                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );

        return;
    }

    const isPage =
        request.mode === 'navigate' || request.headers.has('X-Inertia');

    if (!isPage || SKIP.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Only plain successful pages are kept; redirects (login, actions) are not.
                if (
                    response.ok &&
                    !response.redirected &&
                    response.type === 'basic'
                ) {
                    const copy = response.clone();
                    caches
                        .open(PAGES)
                        .then((cache) => cache.put(pageKey(request), copy));
                }

                return response;
            })
            .catch(async () => {
                const cached = await caches
                    .open(PAGES)
                    .then((cache) => cache.match(pageKey(request)));

                return (
                    cached ??
                    new Response(
                        'You are offline and this page has not been saved yet.',
                        {
                            status: 503,
                            headers: {
                                'Content-Type': 'text/plain; charset=utf-8',
                            },
                        },
                    )
                );
            }),
    );
});
