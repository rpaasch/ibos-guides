/* IBOS Guides – service worker. Genereres pr. guide af pluginet (assets/sw.js er skabelonen). */
'use strict';
const VERSION = '__VERSION__';
const PRECACHE = __PRECACHE__;
const SCOPE = self.registration.scope; // fx https://eksempel.dk/om-teknik/
// Sikkerhed: arbejd kun, hvis service workeren er registreret præcis på guidens adresse.
// Er den registreret med et bredere scope (fx hele sitet), griber den ikke ind i noget.
const EXPECTED_SCOPE = '__SCOPE__';
const ACTIVE = SCOPE === EXPECTED_SCOPE;
const PREFIX = 'ibos-guides:' + new URL(SCOPE).pathname + ':';
const ORIGIN = new URL(SCOPE).origin;
// Filer uden for appens adresse, som appen bruger (billeder og pluginets CSS/JS).
const SHARED = ['/wp-content/uploads/', '/wp-content/plugins/ibos-guides/'];
const CACHE = PREFIX + VERSION;

self.addEventListener('install', (event) => {
  if (!ACTIVE) return;
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    // cache: 'reload' går uden om browserens HTTP-cache, så vi får den nye version.
    // Hent i små portioner, så ét billede, der mangler, ikke stopper resten.
    for (let i = 0; i < PRECACHE.length; i += 8) {
      await Promise.all(PRECACHE.slice(i, i + 8).map(async (p) => {
        try {
          const res = await fetch(new Request(p, { cache: 'reload', credentials: 'omit' }));
          if (res.ok) await cache.put(p, res);
        } catch (e) { /* springes over – hentes ved næste besøg */ }
      }));
    }
  })());
  // Første installation aktiveres straks. Opdateringer venter, til brugeren vælger "Genindlæs".
});

self.addEventListener('activate', (event) => {
  if (!ACTIVE) return;
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith(PREFIX) && key !== CACHE) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  // Kun appens egne sider må bede om at skifte til en ny version.
  if (!event.source || !event.source.url || !event.source.url.startsWith(SCOPE)) return;
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
  if (event.data && event.data.type === 'GET_VERSION' && event.source) event.source.postMessage({ type: 'VERSION', version: VERSION });
});

function normalize(url) {
  const u = new URL(url);
  u.search = '';
  u.hash = '';
  if (u.pathname.endsWith('/index.html')) u.pathname = u.pathname.slice(0, -10);
  return u.href;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (!ACTIVE || req.method !== 'GET') return;
  const url = new URL(req.url);
  const inScope = req.url.startsWith(SCOPE);
  const shared = url.origin === ORIGIN && SHARED.some((p) => url.pathname.startsWith(p));
  if (!inScope && !shared) return;
  const isNav = req.mode === 'navigate';

  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    const key = isNav ? normalize(req.url) : req.url;
    let hit = await cache.match(key, { ignoreSearch: isNav });
    if (!hit && isNav && !key.endsWith('/')) hit = await cache.match(key + '/');
    if (hit) return hit;
    try {
      // Filer uden for appens adresse hentes uden cookies, så intet personligt havner i cachen.
      const res = shared ? await fetch(req.url, { credentials: 'omit' }) : await fetch(req);
      // Kun statiske filer (billeder, CSS, JS) gemmes løbende – aldrig HTML, omdirigeringer
      // eller svar, serveren har markeret som private.
      const type = res.headers.get('content-type') || '';
      const cc = res.headers.get('cache-control') || '';
      if (res.ok && !isNav && res.type === 'basic' && !res.redirected && !/no-store|private/i.test(cc)
        && /^(image\/|text\/css|text\/javascript|application\/javascript)/i.test(type)) {
        cache.put(req, res.clone());
      }
      return res;
    } catch (err) {
      // Offline: brug den forhåndsgemte (mindste) udgave af et billede.
      // WordPress-billedstørrelser hedder fx foto-1024x768.jpg – find en gemt udgave af samme billede.
      const m = url.pathname.match(/^(.*?)(-\d+x\d+)?(-scaled)?\.(jpe?g|png|webp|gif|avif)$/i);
      if (m) {
        const stem = url.origin + m[1];
        const alt = PRECACHE.find((p) => p.startsWith(stem) && /\.(jpe?g|png|webp|gif|avif)$/i.test(p));
        const hitAlt = alt && await cache.match(alt);
        if (hitAlt) return hitAlt;
      }
      if (isNav) {
        return new Response(
          '<!doctype html><html lang="da"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
          + '<title>Ingen forbindelse</title><main style="font-family:system-ui;padding:1rem;max-width:40rem">'
          + '<h1>Siden findes ikke offline</h1><p>Du er uden internet, og denne side er ikke gemt i appen.</p>'
          + '<p><a href="' + SCOPE + '">Gå til forsiden</a></p></main></html>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      }
      throw err;
    }
  })());
});
