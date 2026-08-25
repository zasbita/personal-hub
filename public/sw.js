// ponytail: cache-first for assets, network-first for /api — minimal offline
const CACHE = 'ph-v1';
const ASSETS = ['/', '/manifest.json'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // API: network first, fallback to cache
  if (url.pathname.startsWith('/api/')) {
    e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
    return;
  }
  // Assets: cache first
  e.respondWith(caches.match(e.request).then((r) => r || fetch(e.request).then((res) => {
    if (res.ok && e.request.method === 'GET') {
      const clone = res.clone();
      caches.open(CACHE).then((c) => c.put(e.request, clone));
    }
    return res;
  }).catch(() => caches.match('/'))));
});
