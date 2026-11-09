/**
 * POS Pharma - Progressive Web App Service Worker
 * Version: 1.0.0
 */

const CACHE_NAME = 'pospharma-v1.0.0';
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

// Install: Pre-cache core shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[PWA SW] Pre-cache non-fatal error:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up old cache versions
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

    // 1. Never cache non-GET requests or API calls
    if (request.method !== 'GET' || url.pathname.includes('/api/')) {
        return; // standard network fetch
    }

    // 2. Navigation Requests (HTML Pages): Network First, fallback to offline.html
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // 3. Static Assets (CSS, JS, Fonts, Images): Stale While Revalidate
    if (
        url.pathname.includes('/assets/') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.ico')
    ) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cachedResponse = await cache.match(request);
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        cache.put(request, networkResponse.clone());
                    }
                    return networkResponse;
                }).catch(() => null);

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // 4. Default: Network with Cache Fallback
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
