/* IBOS Guides – service worker. Genereres pr. guide af pluginet (assets/sw.js er skabelonen). */
'use strict';
const VERSION = '__VERSION__';
// Sider, søgeindeks, manifest og appens CSS/JS: SKAL gemmes, ellers fejler installationen
// (så en halv version aldrig erstatter en hel).
const CRITICAL = __CRITICAL__;
// Billeder og ikoner: gemmes hvis muligt; mangler et, springes det over.
const OPTIONAL = __OPTIONAL__;
const PRECACHE = CRITICAL.concat(OPTIONAL);
const PRECACHE_SET = new Set(PRECACHE);
const SCOPE = self.registration.scope; // fx https://eksempel.dk/om-teknik/
// Sikkerhed: arbejd kun, hvis service workeren er registreret præcis på guidens adresse.
// URL'erne normaliseres (fx store/små bogstaver i værtsnavnet), før de sammenlignes.
const EXPECTED_SCOPE = new URL('__SCOPE__').href;
const ACTIVE = new URL(SCOPE).href === EXPECTED_SCOPE;
const PREFIX = 'ibos-guides:' + new URL(SCOPE).pathname + ':';
const ORIGIN = new URL(SCOPE).origin;
// Filer uden for appens adresse, som appen bruger (uploads og pluginets filer).
const SHARED = __SHARED__;
const CACHE = PREFIX + VERSION;
// Videoer, borgeren selv kan hente til brug uden internet (på siden "Installér appen").
// De ligger i en særskilt cache, som overlever nye versioner af appen.
// Adresserne normaliseres, som browseren gør (fx %20 og små bogstaver i værtsnavnet).
const VIDEOS = __VIDEOS__.map((u) => new URL(u, SCOPE).href);
const VIDEO_SET = new Set(VIDEOS);
// Senest brugte video holdes i hukommelsen, så spoling ikke læser hele filen fra cachen igen.
let videoMemo = { url: '', blob: null, type: '' };
const VIDEO_CACHE = PREFIX + 'videos';

async function removeApp() {
  videoMemo = { url: '', blob: null, type: '' };
  for (const k of await caches.keys()) if (k.startsWith(PREFIX)) await caches.delete(k);
  await self.registration.unregister();
}

self.addEventListener('install', (event) => {
  if (!ACTIVE) { self.skipWaiting(); return; }
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    const get = async (p) => {
      // cache: 'reload' går uden om browserens HTTP-cache; ingen cookies; ingen omdirigeringer.
      const res = await fetch(new Request(p, { cache: 'reload', credentials: 'omit', redirect: 'manual' }));
      if (!res.ok || res.redirected || res.type !== 'basic') throw new Error('Kunne ikke hente ' + p);
      await cache.put(p, res);
    };
    try {
      for (let i = 0; i < CRITICAL.length; i += 8) await Promise.all(CRITICAL.slice(i, i + 8).map(get));
    } catch (e) {
      await caches.delete(CACHE); // Ingen halv version – den gamle bliver stående.
      throw e;
    }
    for (let i = 0; i < OPTIONAL.length; i += 8) {
      await Promise.all(OPTIONAL.slice(i, i + 8).map((p) => get(p).catch(() => {})));
    }
  })());
  // Første installation aktiveres straks. Opdateringer venter, til brugeren vælger "Opdatér nu".
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    if (!ACTIVE) { await removeApp(); return; } // Forkert scope: ryd op i stedet for at gribe ind.
    for (const key of await caches.keys()) {
      if (key.startsWith(PREFIX) && key !== CACHE && key !== VIDEO_CACHE) await caches.delete(key);
    }
    // Videoer, der ikke længere er i appen (slettet, eller må ikke længere gemmes), fjernes.
    videoMemo = { url: '', blob: null, type: '' };
    if (await caches.has(VIDEO_CACHE)) {
      const vc = await caches.open(VIDEO_CACHE);
      for (const r of await vc.keys()) if (!VIDEO_SET.has(r.url)) await vc.delete(r);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  // Kun appens egne sider må bede om at skifte til en ny version.
  if (!event.source || !event.source.url || !event.source.url.startsWith(SCOPE)) return;
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});

// Findes appen stadig? Tjekkes i baggrunden (højst hvert minut), når siderne vises fra
// cachen. Kun svaret "410 Gone" – som WordPress giver, når guiden er gjort privat, slettet
// eller flyttet – fjerner appen fra enheden. En midlertidig fejl (plugin slået fra, 404,
// omdirigering, serverfejl) må aldrig slette borgerens app og hentede videoer.
let lastCheck = 0;
async function checkStillPublished() {
  if (Date.now() - lastCheck < 60 * 1000) return;
  lastCheck = Date.now();
  try {
    const res = await fetch(SCOPE + 'manifest.webmanifest', { cache: 'no-store', credentials: 'omit', redirect: 'manual' });
    if (res.status === 410) await removeApp();
  } catch (e) { /* offline eller netværksfejl: gør intet */ }
}

// En hentet video – hele filen eller den bid (Range), afspilleren beder om.
async function serveVideo(req) {
  let blob, type;
  // Findes videoen stadig i cachen? (Borgeren kan have fjernet den på "Installér appen".)
  const hit = await (await caches.open(VIDEO_CACHE)).match(req.url);
  if (!hit) { videoMemo = { url: '', blob: null, type: '' }; return fetch(req); }
  if (videoMemo.url === req.url && videoMemo.blob) {
    ({ blob, type } = videoMemo);
  } else {
    blob = await hit.blob();
    type = hit.headers.get('content-type') || 'video/mp4';
    videoMemo = { url: req.url, blob, type };
  }
  const size = blob.size;
  const range = /^bytes=(\d*)-(\d*)$/.exec((req.headers.get('range') || '').trim());
  if (!range) {
    return new Response(blob, { status: 200, headers: { 'Content-Type': type, 'Content-Length': String(size), 'Accept-Ranges': 'bytes' } });
  }
  if (range[1] === '' && range[2] === '') {
    return new Response(null, { status: 416, headers: { 'Content-Range': 'bytes */' + size } });
  }
  let start = range[1] === '' ? NaN : parseInt(range[1], 10);
  let end = range[2] === '' ? size - 1 : parseInt(range[2], 10);
  if (isNaN(start)) { start = Math.max(0, size - end); end = size - 1; } // "bytes=-500": de sidste 500
  end = Math.min(end, size - 1);
  if (start >= size || end < start) {
    return new Response(null, { status: 416, headers: { 'Content-Range': 'bytes */' + size } });
  }
  return new Response(blob.slice(start, end + 1), {
    status: 206,
    headers: { 'Content-Type': type, 'Content-Range': 'bytes ' + start + '-' + end + '/' + size, 'Content-Length': String(end - start + 1), 'Accept-Ranges': 'bytes' }
  });
}

function normalize(url) {
  const u = new URL(url);
  u.search = '';
  u.hash = '';
  if (u.pathname.endsWith('/index.html')) u.pathname = u.pathname.slice(0, -10);
  return u.href;
}

const IMG = /^(.*?)(-\d+x\d+)?(-scaled)?\.(jpe?g|png|webp|gif|avif)$/i;

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (!ACTIVE || req.method !== 'GET') return;
  // Hentede videoer afspilles fra telefonen (med spoling) – også når filen åbnes direkte.
  if (VIDEO_SET.has(req.url) && req.mode !== 'navigate') { event.respondWith(serveVideo(req)); return; }
  // Anden video og lyd hentes i bidder (Range) direkte fra serveren.
  if (req.headers.has('range') || req.destination === 'video' || req.destination === 'audio') return;
  const url = new URL(req.url);
  const inScope = req.url.startsWith(SCOPE);
  const shared = url.origin === ORIGIN && (PRECACHE_SET.has(req.url) || SHARED.some((p) => url.pathname.startsWith(p)));
  if (!inScope && !shared) return;
  const isNav = req.mode === 'navigate';
  if (isNav) event.waitUntil(checkStillPublished());

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
      // Offline: brug en forhåndsgemt udgave af SAMME billede (WordPress-størrelser hedder
      // fx foto-1024x768.jpg). Stammen skal matche præcist, så et andet foto aldrig vises.
      const m = url.pathname.match(IMG);
      if (m) {
        const stem = url.origin + m[1];
        const alt = PRECACHE.find((p) => { const n = new URL(p).pathname.match(IMG); return n && new URL(p).origin + n[1] === stem; });
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
