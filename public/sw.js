/*
 * ALMARA Gateway WA — Service Worker
 * Hanya meng-cache shell & aset statis yang aman. Halaman admin, API, token,
 * dan isi pesan TIDAK pernah di-cache. Navigasi offline -> /offline.html.
 * @2026-Alifiandra
 */
const VERSION = 'almara-wa-v0.4.0';
const STATIC_ASSETS = [
  '/offline.html',
  '/css/app.css',
  '/js/app.js',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Navigasi halaman: selalu jaringan; bila offline tampilkan halaman fallback.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Aset statis yang terdaftar: cache-first.
  if (STATIC_ASSETS.includes(url.pathname)) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
  }
  // Selain itu (API, data) tidak disentuh service worker.
});
