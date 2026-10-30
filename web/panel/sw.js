/* Service worker del panel (solo cuando se usa desde el navegador / iPhone). */
const CACHE = 'panel-v18';
const SHELL = ['./', 'index.html', 'app.css?v=18', 'app.js?v=18', 'manifest.webmanifest', 'icons/icon-192.png', 'alergenos.svg?v=3'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('panel-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // La API nunca se guarda en caché: siempre datos frescos
  if (e.request.method !== 'GET' || url.pathname.includes('/api/') || url.origin !== location.origin) return;
  e.respondWith(
    fetch(e.request)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy));
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((list) => {
      if (list.length) return list[0].focus();
      return self.clients.openWindow('./');
    })
  );
});
