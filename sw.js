/* Service worker de la app (PWA): permite instalarla y consultar la agenda sin conexión. */
const VERSION = 'agenda-v1';
const BASICOS = [
  './',
  'index.php',
  'assets/css/app.css',
  'assets/js/agenda.js',
  'assets/js/app.js',
  'assets/img/logo_600.png',
  'assets/img/logo_eg.jpg',
  'assets/icons/icon-192.png',
  'manifest.webmanifest'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(VERSION)
      .then((c) => Promise.all(BASICOS.map((u) => c.add(u).catch(() => null))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((ks) => Promise.all(ks.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  // La zona de gestión, los PDF y los .ics siempre van a la red
  if (url.pathname.includes('/admin/') || url.pathname.endsWith('cartel.php') || url.searchParams.has('ics')) return;

  const esEstatico = /\.(css|js|png|jpe?g|gif|svg|webmanifest)$/.test(url.pathname);
  if (esEstatico) {
    // Caché primero y actualización en segundo plano
    e.respondWith(caches.open(VERSION).then((c) =>
      c.match(req).then((guardado) => {
        const red = fetch(req).then((r) => { if (r.ok) c.put(req, r.clone()); return r; }).catch(() => guardado);
        return guardado || red;
      })
    ));
    return;
  }

  // Páginas y API: red primero; sin conexión, la última copia guardada
  e.respondWith(
    fetch(req).then((r) => {
      if (r.ok) {
        const copia = r.clone();
        caches.open(VERSION).then((c) => c.put(req, copia));
      }
      return r;
    }).catch(() =>
      caches.match(req, { ignoreSearch: false }).then((r) =>
        r || caches.match(req, { ignoreSearch: true }).then((r2) =>
          r2 || new Response(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="font-family:system-ui;padding:2rem;text-align:center"><h1>Sin conexión</h1><p>Conéctate a Internet para ver la agenda actualizada.</p>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          )
        )
      )
    )
  );
});
