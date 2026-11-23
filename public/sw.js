// FGLA service worker: makes the ERP and student portal installable and shows a friendly
// page when there is no connection. It never caches signed-in pages (privacy on shared phones).
const CACHE = 'fgla-shell-v1';
const SHELL = ['/offline.html', '/images/app-icon-192.png'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    if (e.request.mode !== 'navigate') return;
    e.respondWith(fetch(e.request).catch(() => caches.match('/offline.html')));
});
