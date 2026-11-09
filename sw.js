/**
 * POS Pharma - Progressive Web App Service Worker
 * Version: 1.0.1
 */

const CACHE_NAME = 'pospharma-v1.0.1';
const OFFLINE_URL = './offline.html';

const PRECACHE_ASSETS = [
    './offline.html',
    './assets/css/style.css',
    './assets/vendor/fonts/noto-sans-lao.css',
    './assets/vendor/fontawesome/css/all.min.css',
    './assets/vendor/fontawesome/webfonts/fa-solid-900.woff2',
    './assets/vendor/sweetalert2.all.min.js',
    './assets/js/main.js',
    './assets/js/pwa.js',
    './assets/icons/icon-192x192.png',
    './assets/icons/icon-512x512.png',
    './assets/icons/icon-maskable-512x512.png',
    './assets/icons/apple-touch-icon.png'
];

// Fallback HTML response if offline.html cannot be read from cache
const FALLBACK_HTML = `<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ບໍ່ມີສັນຍານອິນເຕີເນັດ | POS Pharma</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            text-align: center;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 24px;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .icon {
            font-size: 48px;
            color: #2e7d32;
            margin-bottom: 16px;
        }
        h1 { font-size: 1.25rem; margin-bottom: 8px; color: #0f172a; }
        p { font-size: 0.95rem; color: #64748b; margin-bottom: 24px; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: #2e7d32;
            color: #ffffff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#x1F4F6;</div>
        <h1>ບໍ່ມີສັນຍານອິນເຕີເນັດ</h1>
        <p>ກະລຸນາກວດສອບການເຊື່ອມຕໍ່ອິນເຕີເນັດຂອງທ່ານ ແລ້ວກົດປຸ່ມດ້ານລຸ່ມເພື່ອໂຫຼດຄືນໃໝ່</p>
        <button class="btn" onclick="window.location.reload()">ໂຫຼດຄືນໃໝ່</button>
    </div>
</body>
</html>`;

// Install: Pre-cache core shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            // Cache offline page first
            try {
                await cache.add(OFFLINE_URL);
            } catch (err) {
                console.warn('[PWA SW] Pre-cache offline page warning:', err);
            }

            // Cache remaining assets resiliently (one failure will not cancel the rest)
            await Promise.allSettled(
                PRECACHE_ASSETS.map((asset) =>
                    cache.add(asset).catch((err) => {
                        console.warn(`[PWA SW] Non-fatal precache asset skip: ${asset}`, err);
                    })
                )
            );
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up old cache versions and claim clients immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Strategy depending on request type
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // 1. Only intercept GET requests, and skip API endpoints
    if (request.method !== 'GET' || url.pathname.includes('/api/')) {
        return; // standard browser network handling
    }

    // 2. Navigation Requests (HTML Pages): Network First, fallback to offline.html
    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    const networkResponse = await fetch(request);
                    return networkResponse;
                } catch (error) {
                    const cache = await caches.open(CACHE_NAME);
                    const cachedOffline = (await cache.match(OFFLINE_URL)) ||
                                          (await cache.match('/offline.html')) ||
                                          (await caches.match(OFFLINE_URL)) ||
                                          (await caches.match('/offline.html'));
                    if (cachedOffline) {
                        return cachedOffline;
                    }
                    // Always return a valid Response (never null or undefined)
                    return new Response(FALLBACK_HTML, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                        status: 503,
                        statusText: 'Service Unavailable'
                    });
                }
            })()
        );
        return;
    }

    // 3. Static Assets (CSS, JS, Fonts, Images): Stale While Revalidate
    if (
        url.pathname.includes('/assets/') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.woff') ||
        url.pathname.endsWith('.ttf') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.webp') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico')
    ) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(CACHE_NAME);
                const cachedResponse = await cache.match(request);

                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(() => null);

                // If found in cache, return immediately and update in background
                if (cachedResponse) {
                    return cachedResponse;
                }

                // If not in cache, wait for network
                const networkResponse = await fetchPromise;
                if (networkResponse) {
                    return networkResponse;
                }

                // Never return null/undefined to respondWith: return safe empty response
                return new Response('', { status: 408, statusText: 'Request Timeout' });
            })()
        );
        return;
    }

    // 4. Default: Let browser handle other requests natively without calling respondWith
    // (This avoids unhandled rejections and prevents 'Failed to convert value to Response')
});
