/* Pulse POS service worker: caches the app shell so the terminal loads during an outage; API calls are never cached (the app queues them). */
var CACHE = 'pulse-pos-v1';
self.addEventListener('install', function (e) { e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll([self.registration.scope]); }).catch(function () {})); self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  var url = e.request.url;
  if (url.indexOf('resource=') > -1 || e.request.method !== 'GET') { return; }
  e.respondWith(fetch(e.request).then(function (r) { var copy = r.clone(); caches.open(CACHE).then(function (c) { c.put(e.request, copy); }); return r; }).catch(function () { return caches.match(e.request); }));
});
