/* Progressiv forbedring: alt indhold virker uden dette script. */
(function () {
  'use strict';
  var body = document.body;
  var rootUrl = body.getAttribute('data-base') || new URL('./', location.href).href;
  var root = rootUrl;
  var statusEl = document.getElementById('app-status');
  var clearTimer = null;

  // Meddelelse i den fælles live-region (annonceres af skærmlæsere).
  function announce(nodes, keep) {
    if (!statusEl) return;
    clearTimeout(clearTimer);
    statusEl.textContent = '';
    // Lille pause, så skærmlæsere opfatter ændringen, også ved samme tekst.
    setTimeout(function () {
      (Array.isArray(nodes) ? nodes : [nodes]).forEach(function (n) {
        statusEl.appendChild(typeof n === 'string' ? document.createTextNode(n) : n);
      });
      statusEl.classList.add('is-visible');
      if (!keep) clearTimer = setTimeout(function () { statusEl.textContent = ''; statusEl.classList.remove('is-visible'); }, 8000);
    }, 100);
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }
  if (isStandalone()) document.documentElement.classList.add('standalone');

  // ---------- Service worker ----------
  if (body.hasAttribute('data-nosw')) {
    // Fejlsøgning (?ibg-nosw med WP_DEBUG): ingen offline-cache.
    if ('serviceWorker' in navigator) navigator.serviceWorker.getRegistrations().then(function (rs) { rs.forEach(function (r) { r.unregister(); }); });
  } else if ('serviceWorker' in navigator && window.isSecureContext) {
    var reloading = false;
    var hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.register(root + 'sw.js', { scope: root }).then(function (reg) {
      if (reg.waiting && hadController) offerUpdate(reg.waiting);
      reg.addEventListener('updatefound', function () {
        var nw = reg.installing;
        if (!nw) return;
        nw.addEventListener('statechange', function () {
          if (nw.state === 'installed') {
            if (navigator.serviceWorker.controller) offerUpdate(nw);
          } else if (nw.state === 'activated' && !hadController) {
            announce('Appen er gemt på enheden og kan nu bruges uden internet.');
          }
        });
      });
      // Tjek for ny version, når appen kommer i forgrunden igen.
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') reg.update().catch(function () {});
      });
    }).catch(function () {});
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (reloading) location.reload();
    });

    var offerUpdate = function (worker) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'button';
      btn.textContent = 'Genindlæs';
      btn.addEventListener('click', function () {
        reloading = true;
        // Har en anden fane allerede skiftet til den nye version, genindlæses blot.
        if (worker.state === 'activated' || worker.state === 'redundant') { location.reload(); return; }
        worker.postMessage({ type: 'SKIP_WAITING' });
      });
      announce(['Ny version klar – ', btn], true);
    };
  }

  // ---------- Installation ----------
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    setupInstallButton();
  });
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    var b = document.getElementById('install-button');
    if (b) b.hidden = true;
    setInstallStatus('Appen er installeret. Du finder den på hjemmeskærmen eller blandt dine apps.');
  });

  function setInstallStatus(t) {
    var s = document.getElementById('install-status');
    if (s) s.textContent = t;
  }
  function setupInstallButton() {
    var b = document.getElementById('install-button');
    if (!b || !deferredPrompt) return;
    b.hidden = false;
    setInstallStatus('Din browser kan installere appen direkte. Brug knappen herunder.');
    if (b.dataset.bound) return;
    b.dataset.bound = '1';
    b.addEventListener('click', function () {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function (choice) {
        if (choice.outcome !== 'accepted') setInstallStatus('Installationen blev afbrudt. Du kan prøve igen med knappen.');
        deferredPrompt = null;
        if (choice.outcome === 'accepted') b.hidden = true;
      });
    });
  }

  function initInstallPage() {
    if (!document.getElementById('install-box')) return;
    var ua = navigator.userAgent;
    var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isStandalone()) {
      setInstallStatus('Appen er allerede installeret – du bruger den nu.');
    } else if (ios) {
      var s = document.getElementById('install-status');
      if (s) {
        s.textContent = 'Det ser ud til, at du bruger iPhone eller iPad. ';
        var a = document.createElement('a');
        a.href = '#iphone';
        a.textContent = 'Gå til vejledningen for iPhone og iPad';
        s.appendChild(a);
      }
    } else if (!deferredPrompt) {
      setInstallStatus('Følg vejledningen for din enhed herunder.');
    }
    setupInstallButton();

    // QR-kode og adresse til deling.
    var share = document.getElementById('share');
    if (share && window.qrcode) {
      try {
        var qr = window.qrcode(0, 'M');
        qr.addData(rootUrl);
        qr.make();
        var holder = document.getElementById('qr');
        holder.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true });
        var svg = holder.querySelector('svg');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'QR-kode til appens adresse');
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        document.getElementById('app-url').textContent = rootUrl;
        var copy = document.getElementById('copy-url');
        if (navigator.clipboard) {
          copy.addEventListener('click', function () {
            navigator.clipboard.writeText(rootUrl).then(function () { announce('Adressen er kopieret.'); },
              function () { announce('Adressen kunne ikke kopieres.'); });
          });
        } else copy.hidden = true;
        share.hidden = false;
      } catch (e) { /* ingen QR-kode – vejledningen virker stadig */ }
    }
  }

  // ---------- Søgning ----------
  function initSearch() {
    var form = document.getElementById('search-form');
    if (!form) return;
    form.hidden = false;
    var input = document.getElementById('q');
    var status = document.getElementById('search-status');
    var list = document.getElementById('search-results');
    var wrap = document.getElementById('search-results-wrap');
    var index = null;
    var loading = null;
    var timer = null;

    function load() {
      if (!loading) {
        loading = fetch(root + 'search-index.json').then(function (r) { return r.json(); })
          .then(function (d) { index = d; return d; })
          .catch(function () { status.textContent = 'Søgningen kunne ikke indlæses. Brug menuen på forsiden.'; });
      }
      return loading;
    }
    function norm(s) { return (s || '').toLocaleLowerCase('da'); }
    function snippet(text, term) {
      var i = norm(text).indexOf(term);
      if (i < 0) return text.slice(0, 140) + (text.length > 140 ? ' …' : '');
      var start = Math.max(0, i - 60);
      return (start > 0 ? '… ' : '') + text.slice(start, start + 160) + (start + 160 < text.length ? ' …' : '');
    }
    function run(announceEmpty) {
      var q = input.value.trim();
      if (!q) {
        list.innerHTML = '';
        wrap.hidden = true;
        status.textContent = announceEmpty ? 'Skriv et eller flere ord for at søge.' : '';
        return;
      }
      load().then(function () {
        if (!index) return;
        var terms = norm(q).split(/\s+/).filter(Boolean);
        var results = [];
        index.forEach(function (p) {
          var t = norm(p.t);
          var x = norm(p.x);
          var score = 0;
          for (var i = 0; i < terms.length; i++) {
            var inT = t.indexOf(terms[i]) !== -1;
            var inX = x.indexOf(terms[i]) !== -1;
            if (!inT && !inX) return;
            score += (inT ? 10 : 0) + (inX ? 1 : 0);
          }
          results.push({ p: p, score: score });
        });
        results.sort(function (a, b) { return b.score - a.score; });
        list.innerHTML = '';
        results.forEach(function (r) {
          var li = document.createElement('li');
          var a = document.createElement('a');
          a.href = root + r.p.u;
          a.textContent = r.p.t;
          li.appendChild(a);
          if (r.p.s) {
            var sec = document.createElement('span');
            sec.className = 'result-section';
            sec.textContent = ' (' + r.p.s + ')';
            li.appendChild(sec);
          }
          var p = document.createElement('p');
          p.textContent = snippet(r.p.x, terms[0]);
          li.appendChild(p);
          list.appendChild(li);
        });
        wrap.hidden = results.length === 0;
        var n = results.length;
        status.textContent = (n === 0 ? 'Ingen resultater' : n === 1 ? '1 resultat' : n + ' resultater') + ' for »' + q + '«.';
      });
    }

    input.addEventListener('focus', load, { once: true });
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { run(false); }, 400);
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      try { history.replaceState(null, '', '?q=' + encodeURIComponent(input.value.trim())); } catch (err) { /* ok */ }
      run(true);
    });
    var initial = new URLSearchParams(location.search).get('q');
    if (initial) { input.value = initial; run(false); }
  }

  // ---------- Video ----------
  // Videoer kan hentes til brug uden internet på siden "Installér appen". De ligger i en
  // særskilt cache, som service workeren afspiller fra.
  var videoCache = 'ibos-guides:' + new URL(root).pathname + ':videos';
  function videoCached(url) {
    if (!('caches' in window) || !url) return Promise.resolve(false);
    return caches.open(videoCache).then(function (c) { return c.match(url); }).then(function (r) { return !!r; }, function () { return false; });
  }

  // Uden net: hentede videoer afspilles som normalt. Andre erstattes af en besked, der siger,
  // om og hvor videoen kan hentes.
  function initVideos() {
    var figs = Array.prototype.slice.call(document.querySelectorAll('figure.wp-block-video'));
    if (!figs.length) return;
    var install = root + 'installer/#videos';
    figs.forEach(function (fig) {
      var note = document.createElement('p');
      note.className = 'video-offline';
      note.hidden = true;
      note.appendChild(document.createTextNode('Videoen kan kun ses med internet. Teksten på siden beskriver det vigtigste.'));
      if (fig.getAttribute('data-offline') === '1') {
        note.appendChild(document.createTextNode(' '));
        var a = document.createElement('a');
        a.href = install;
        a.textContent = 'Hent videoerne til brug uden internet';
        note.appendChild(a);
        note.appendChild(document.createTextNode(', næste gang du har net.'));
      }
      fig.insertBefore(note, fig.firstChild);
      var v = fig.querySelector('video');
      // Også på wifi uden internet: kan videoen ikke hentes, vises beskeden.
      if (v) v.addEventListener('error', function () { fig.dataset.failed = '1'; show(fig, true); }, true);
    });
    function show(fig, off) {
      fig.classList.toggle('is-offline', off);
      fig.querySelector('.video-offline').hidden = !off;
    }
    function update() {
      var off = !navigator.onLine;
      figs.forEach(function (fig) {
        var v = fig.querySelector('video');
        if (!off && fig.dataset.failed) {
          // Nettet er tilbage: prøv at hente videoen igen.
          delete fig.dataset.failed;
          if (v) v.load();
        }
        if (!off) { show(fig, false); return; }
        videoCached(v && (v.currentSrc || v.src)).then(function (cached) { show(fig, !cached); });
      });
    }
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    update();
  }

  function mb(bytes) {
    var m = bytes / 1048576;
    return (m < 10 ? Math.max(0.1, Math.round(m * 10) / 10) : Math.round(m)).toLocaleString('da-DK');
  }

  // "Installér appen": hent eller fjern videoerne.
  function initVideoDownload() {
    var sec = document.getElementById('videos');
    if (!sec || !('caches' in window) || !window.fetch) return;
    var list;
    try { list = JSON.parse(sec.getAttribute('data-videos')) || []; } catch (e) { return; }
    if (!list.length) return;
    sec.hidden = false;
    var status = document.getElementById('videos-status');
    var bar = document.getElementById('videos-progress');
    var get = document.getElementById('videos-get');
    var remove = document.getElementById('videos-remove');
    var busy = false;

    function missing() {
      return Promise.all(list.map(function (v) { return videoCached(v.u); })).then(function (have) {
        return list.filter(function (v, i) { return !have[i]; });
      });
    }
    function refresh() {
      return missing().then(function (miss) {
        var have = list.length - miss.length;
        var left = miss.reduce(function (n, v) { return n + (v.s || 0); }, 0);
        remove.hidden = have === 0;
        get.hidden = miss.length === 0;
        if (miss.length === 0) {
          status.textContent = list.length === 1 ? 'Videoen er hentet og virker uden internet.' : 'Alle ' + list.length + ' videoer er hentet og virker uden internet.';
        } else if (have > 0) {
          status.textContent = have + ' af ' + list.length + ' videoer er hentet.';
          get.textContent = 'Hent de resterende videoer (ca. ' + mb(left) + ' MB)';
        } else {
          status.textContent = '';
          get.textContent = 'Hent videoer (ca. ' + mb(left) + ' MB)';
        }
        return miss;
      });
    }

    get.addEventListener('click', function () {
      if (busy) return;
      if (!navigator.onLine) { status.textContent = 'Du er ikke på internettet. Prøv igen, når du har forbindelse – helst wifi.'; return; }
      busy = true;
      get.disabled = true;
      remove.disabled = true;
      // Bed browseren om ikke at rydde de hentede videoer, når der mangler plads.
      if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});
      var cache, miss, total = 0, loaded = 0;
      refresh().then(function (m) {
        miss = m;
        total = miss.reduce(function (n, v) { return n + (v.s || 0); }, 0) || 1;
        if (navigator.storage && navigator.storage.estimate) {
          return navigator.storage.estimate().then(function (e) {
            if (e && e.quota && e.quota - (e.usage || 0) < total * 1.1) throw new Error('plads');
          }, function () {});
        }
      }).then(function () {
        return caches.open(videoCache);
      }).then(function (c) {
        cache = c;
        bar.hidden = false;
        bar.value = 0;
        var i = 0;
        function next() {
          if (i >= miss.length) return Promise.resolve();
          var v = miss[i++];
          status.textContent = 'Henter video ' + i + ' af ' + miss.length + ' …';
          return fetch(v.u, { credentials: 'omit' }).then(function (res) {
            if (!res.ok) throw new Error('http');
            var type = res.headers.get('content-type') || 'video/mp4';
            var done = function (blob) { return cache.put(v.u, new Response(blob, { headers: { 'Content-Type': type } })); };
            if (!res.body || !res.body.getReader) {
              return res.blob().then(function (b) { loaded += v.s || b.size; bar.value = Math.min(100, loaded / total * 100); return done(b); });
            }
            var reader = res.body.getReader();
            var chunks = [];
            function pump() {
              return reader.read().then(function (r) {
                if (r.done) return done(new Blob(chunks, { type: type }));
                chunks.push(r.value);
                loaded += r.value.length;
                bar.value = Math.min(100, loaded / total * 100);
                return pump();
              });
            }
            return pump();
          }).then(next);
        }
        return next();
      }).then(function () {
        return refresh();
      }).catch(function (e) {
        return refresh().then(function () {
          status.textContent = e && e.message === 'plads'
            ? 'Der er ikke plads nok på telefonen til videoerne. Frigør plads, og prøv igen.'
            : 'Hentningen stoppede – måske mistede du forbindelsen, eller der er ikke plads nok. Prøv igen; videoer, der allerede er hentet, er gemt.';
        });
      }).then(function () {
        busy = false;
        get.disabled = false;
        remove.disabled = false;
        bar.hidden = true;
      });
    });

    remove.addEventListener('click', function () {
      if (busy) return;
      caches.delete(videoCache).then(refresh).then(function () {
        status.textContent = 'De hentede videoer er fjernet fra telefonen. De kan stadig ses med internet.';
        get.focus();
      });
    });

    refresh();
  }

  initInstallPage();
  initSearch();
  initVideos();
  initVideoDownload();
})();
