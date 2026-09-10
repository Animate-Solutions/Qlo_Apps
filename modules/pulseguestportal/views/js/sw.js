/* Pulse Guest Portal service worker.
   The shell (page, CSS, JS, images) is cached so the TV still starts when the link to the server is down.
   Read-only API calls that a guest can safely see stale — the directory, the channel list, the menu and the
   home payload — are cached network-first and served from cache on failure; everything that writes, and
   anything personal (folio, messages), is never cached: the app queues those itself and replays them. */
var CACHE = 'pulse-gp-v1';
var CACHEABLE = ['resource=directory', 'resource=channels', 'resource=menu', 'resource=vod', 'resource=home', 'resource=ping'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll([self.registration.scope]); }).catch(function () {}));
  self.skipWaiting();
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) { return Promise.all(keys.map(function (k) { return k === CACHE ? null : caches.delete(k); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var url = e.request.url;
  if (e.request.method !== 'GET') { return; }
  var isApi = url.indexOf('resource=') > -1;
  if (isApi) {
    var ok = false;
    for (var i = 0; i < CACHEABLE.length; i++) { if (url.indexOf(CACHEABLE[i]) > -1) { ok = true; break; } }
    if (!ok) { return; }
  }
  e.respondWith(
    fetch(e.request).then(function (r) {
      if (r && r.status === 200) { var copy = r.clone(); caches.open(CACHE).then(function (c) { c.put(e.request, copy); }).catch(function () {}); }
      return r;
    }).catch(function () { return caches.match(e.request).then(function (m) { return m || Response.error(); }); })
  );
});
