/* Pulse Guest Portal — in-room application. Vanilla JS, no build step, no framework: it has to start on a
   2019 Samsung hospitality TV browser as well as on a tablet. Everything the guest sees comes from the API;
   the shell, the directory, the channel list and the menu are cached so the screen still works when the LAN
   link to the server drops, and anything the guest does while offline is queued and replayed. */
var GP = (function () {
  var B = window.GP_BOOT || {};
  var S = { dev: null, token: null, sess: null, lang: B.default_lang || 'en', rtl: false, home: null, view: 'home', hist: [],
            menu: null, cart: [], dir: null, chan: null, vod: null, ctl: null, thread: [], unread: 0, online: true, paired: false, adultPin: '' };
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var cur = function () { return (B.theme && B.theme.currency) || '₦'; };
  var money = function (v) { return cur() + Number(v || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

  /* ---------- language ---------- */
  var T = {
    en: { home: 'Home', folio: 'My bill', dining: 'In-room dining', requests: 'Requests', messages: 'Messages', directory: 'Hotel directory', tv: 'TV channels', vod: 'Movies', radio: 'Radio', games: 'Games & apps', cast: 'Cast my phone', controls: 'Room controls', checkout: 'Check out',
      welcome: 'Welcome', room: 'Room', balance: 'Balance', order: 'Order', cart: 'Tray', place: 'Place order', total: 'Total', send: 'Send', write: 'Write a message to the front desk', back: 'Back', ok: 'OK', cancel: 'Cancel', added: 'Added to your tray', empty: 'Your tray is empty', sent: 'Sent', thanks: 'Thank you', pin: 'Enter PIN', wrong: 'That did not work', ready: 'Your order is ready', offline: 'Working offline — we will send this when the link is back', queued: 'Saved — it will be sent shortly', nights: 'nights', departs: 'Departure', charges: 'Charges', payments: 'Payments', rate: 'How was your stay?', comment: 'Anything you would like to tell us?', submit: 'Submit', express: 'Express check-out', late: 'Ask for late check-out', wake: 'Wake-up call', dnd: 'Do not disturb', mur: 'Make up my room', vacant: 'No guest is checked in', vacantsub: 'The hotel directory and TV channels are available. Please see the front desk to check in.', pairing: 'Waiting for the front desk', paircode: 'Give this code to reception', price: 'Price', free: 'Free', play: 'Play', adult: 'Adults only', paid: 'This title will be added to your room bill', on: 'On', off: 'Off', open: 'Open', close: 'Close', warmer: 'Warmer', cooler: 'Cooler' },
    fr: { home: 'Accueil', folio: 'Ma note', dining: 'Service en chambre', requests: 'Demandes', messages: 'Messages', directory: 'Guide de l’hôtel', tv: 'Chaînes TV', vod: 'Films', radio: 'Radio', games: 'Jeux et apps', cast: 'Diffuser mon téléphone', controls: 'Commandes de la chambre', checkout: 'Départ',
      welcome: 'Bienvenue', room: 'Chambre', balance: 'Solde', order: 'Commander', cart: 'Plateau', place: 'Envoyer la commande', total: 'Total', send: 'Envoyer', write: 'Écrivez à la réception', back: 'Retour', ok: 'OK', cancel: 'Annuler', added: 'Ajouté au plateau', empty: 'Votre plateau est vide', sent: 'Envoyé', thanks: 'Merci', pin: 'Saisissez le code', wrong: 'Cela n’a pas fonctionné', ready: 'Votre commande est prête', offline: 'Hors ligne — nous enverrons dès le retour du réseau', queued: 'Enregistré — envoi imminent', nights: 'nuits', departs: 'Départ', charges: 'Débits', payments: 'Règlements', rate: 'Comment s’est passé votre séjour ?', comment: 'Un mot pour nous ?', submit: 'Envoyer', express: 'Départ express', late: 'Demander un départ tardif', wake: 'Réveil', dnd: 'Ne pas déranger', mur: 'Faire la chambre', vacant: 'Aucun client enregistré', vacantsub: 'Le guide de l’hôtel et les chaînes restent disponibles.', pairing: 'En attente de la réception', paircode: 'Communiquez ce code à la réception', price: 'Prix', free: 'Gratuit', play: 'Lire', adult: 'Adultes', paid: 'Ce titre sera ajouté à votre note', on: 'Allumé', off: 'Éteint', open: 'Ouvrir', close: 'Fermer', warmer: 'Plus chaud', cooler: 'Plus frais' },
    pcm: { home: 'Home', folio: 'My bill', dining: 'Food for room', requests: 'Wetin you need', messages: 'Message', directory: 'Hotel guide', tv: 'TV channel', vod: 'Movie', radio: 'Radio', games: 'Game and app', cast: 'Show my phone for TV', controls: 'Room control', checkout: 'Check out',
      welcome: 'Welcome', room: 'Room', balance: 'Wetin remain', order: 'Order', cart: 'Your tray', place: 'Send the order', total: 'Total', send: 'Send', write: 'Write message give front desk', back: 'Go back', ok: 'OK', cancel: 'Leave am', added: 'E don enter your tray', empty: 'Your tray empty', sent: 'E don go', thanks: 'Thank you', pin: 'Enter PIN', wrong: 'E no work', ready: 'Your food don ready', offline: 'Network no dey — we go send am when e come back', queued: 'We keep am — e go go soon', nights: 'nights', departs: 'Day wey you dey go', charges: 'Wetin you buy', payments: 'Wetin you don pay', rate: 'How your stay be?', comment: 'Talk anything you wan tell us', submit: 'Send am', express: 'Quick check out', late: 'Beg for late check out', wake: 'Wake me call', dnd: 'No disturb me', mur: 'Come clean my room', vacant: 'Nobody dey checked in', vacantsub: 'Hotel guide and TV channel still dey work. Abeg see front desk.', pairing: 'We dey wait front desk', paircode: 'Give reception this code', price: 'Price', free: 'Free', play: 'Play am', adult: 'Adults only', paid: 'Dem go add this one for your room bill', on: 'On', off: 'Off', open: 'Open', close: 'Close', warmer: 'Make e warm', cooler: 'Make e cold' },
    ar: { home: 'الرئيسية', folio: 'الفاتورة', dining: 'خدمة الغرف', requests: 'الطلبات', messages: 'الرسائل', directory: 'دليل الفندق', tv: 'القنوات', vod: 'الأفلام', radio: 'الراديو', games: 'الألعاب', cast: 'بث الهاتف', controls: 'تحكم الغرفة', checkout: 'مغادرة',
      welcome: 'أهلاً', room: 'غرفة', balance: 'الرصيد', order: 'اطلب', cart: 'الصينية', place: 'إرسال الطلب', total: 'الإجمالي', send: 'إرسال', write: 'اكتب إلى الاستقبال', back: 'رجوع', ok: 'حسناً', cancel: 'إلغاء', added: 'تمت الإضافة', empty: 'الصينية فارغة', sent: 'تم الإرسال', thanks: 'شكراً', pin: 'أدخل الرمز', wrong: 'لم ينجح', ready: 'طلبك جاهز', offline: 'دون اتصال — سنرسله لاحقاً', queued: 'تم الحفظ', nights: 'ليالٍ', departs: 'المغادرة', charges: 'المصروفات', payments: 'المدفوعات', rate: 'كيف كانت إقامتك؟', comment: 'ملاحظاتك', submit: 'إرسال', express: 'مغادرة سريعة', late: 'مغادرة متأخرة', wake: 'مكالمة إيقاظ', dnd: 'عدم الإزعاج', mur: 'تنظيف الغرفة', vacant: 'لا يوجد نزيل', vacantsub: 'دليل الفندق والقنوات متاحة.', pairing: 'بانتظار الاستقبال', paircode: 'أعط الاستقبال هذا الرمز', price: 'السعر', free: 'مجاناً', play: 'تشغيل', adult: 'للكبار', paid: 'ستضاف إلى فاتورتك', on: 'تشغيل', off: 'إيقاف', open: 'فتح', close: 'إغلاق', warmer: 'أدفأ', cooler: 'أبرد' }
  };
  function t(k) { var d = T[S.lang] || T.en; return d[k] || T.en[k] || k; }

  /* ---------- storage, offline cache and replay queue ---------- */
  function store(k, v) { try { if (v === undefined) { return JSON.parse(localStorage.getItem('gp_' + k) || 'null'); } localStorage.setItem('gp_' + k, JSON.stringify(v)); } catch (e) { return null; } return v; }
  function drop(k) { try { localStorage.removeItem('gp_' + k); } catch (e) {} }
  var QUEUEABLE = { request: 1, message_send: 1, order: 1, feedback: 1, wakeup: 1 };
  function queue() { return store('queue') || []; }
  function enqueue(item) { var q = queue(); q.push(item); store('queue', q); }
  function replay() {
    var q = queue(); if (!q.length || !S.token) { return; }
    var next = q.shift(); store('queue', q);
    api(next.resource, next.id, next.body).then(function () { toast(t('sent')); replay(); }).catch(function () {});
  }

  /* ---------- api ---------- */
  function api(res, id, body) {
    var url = B.api + (B.api.indexOf('?') > -1 ? '&' : '?') + 'resource=' + res + (id ? '&id=' + id : '') + '&lang=' + S.lang;
    var h = { 'Content-Type': 'application/json' };
    if (S.token) { h['X-Pulse-Device'] = S.token; }
    if (S.sess) { h['X-Pulse-Session'] = S.sess; }
    return fetch(url, { method: body ? 'POST' : 'GET', headers: h, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { return { http: r.status, j: j }; }); })
      .then(function (r) {
        setOnline(true);
        if (!r.j.ok) {
          if (r.http === 401 && res !== 'session' && res !== 'pair') { return newSession().then(function () { return api(res, id, body); }); }
          throw new Error(r.j.error || 'Error');
        }
        return r.j.data;
      })
      .catch(function (e) {
        if (e instanceof TypeError) {
          setOnline(false);
          if (QUEUEABLE[res] && body) { enqueue({ resource: res, id: id, body: body }); toast(t('queued')); return null; }
        }
        throw e;
      });
  }
  function setOnline(v) { if (S.online === v) { return; } S.online = v; $('gp-offline').className = 'gp-offline' + (v ? ' gp-hidden' : ''); if (v) { replay(); } }

  /* ---------- chrome ---------- */
  function toast(msg, err) { var el = $('gp-toast'); el.textContent = msg; el.className = 'gp-toast' + (err ? ' gp-err' : ''); clearTimeout(el._t); el._t = setTimeout(function () { el.className = 'gp-toast gp-hidden'; }, err ? 5000 : 2600); }
  function fail(e) { toast((e && e.message) || String(e), true); }
  function modal(title, html, after) { $('gp-modal-title').textContent = title; $('gp-modal-body').innerHTML = html; $('gp-modal').className = 'gp-modal'; wire($('gp-modal')); if (after) { after(); } }
  function closeModal() { $('gp-modal').className = 'gp-modal gp-hidden'; wire($('gp-app')); }
  function modalOpen() { return $('gp-modal').className.indexOf('gp-hidden') === -1; }

  /* ---------- D-pad focus: every actionable node carries data-gp-focus ---------- */
  var NODES = [], IDX = 0;
  function wire(root) {
    NODES = [].slice.call((root || $('gp-app')).querySelectorAll('[data-gp-focus]'));
    IDX = 0; paint();
    NODES.forEach(function (n, i) { n.onclick = function () { IDX = i; paint(); activate(n); }; });
  }
  function paint() { NODES.forEach(function (n, i) { n.className = n.className.replace(/\s*gp-on/g, '') + (i === IDX ? ' gp-on' : ''); }); if (NODES[IDX] && NODES[IDX].scrollIntoView) { try { NODES[IDX].scrollIntoView({ block: 'nearest' }); } catch (e) { NODES[IDX].scrollIntoView(); } } }
  function activate(n) {
    if (!n) { return; }
    if (n.dataset.gpClose) { return closeModal(); }
    if (n.dataset.gpGo) { return go(n.dataset.gpGo); }
    if (n.dataset.gpAct && ACT[n.dataset.gpAct]) { return ACT[n.dataset.gpAct](n); }
    if (n.tagName === 'INPUT' || n.tagName === 'TEXTAREA' || n.tagName === 'SELECT') { n.focus(); }
  }
  /* Columns are inferred from the layout so left/right walks a grid and up/down walks rows. */
  function move(dx, dy) {
    if (!NODES.length) { return; }
    var cur = NODES[IDX].getBoundingClientRect(); var best = -1, bestD = 1e9;
    NODES.forEach(function (n, i) {
      if (i === IDX) { return; }
      var r = n.getBoundingClientRect();
      var ddx = (r.left + r.width / 2) - (cur.left + cur.width / 2), ddy = (r.top + r.height / 2) - (cur.top + cur.height / 2);
      if (dx && (dx > 0 ? ddx <= 8 : ddx >= -8)) { return; }
      if (dy && (dy > 0 ? ddy <= 8 : ddy >= -8)) { return; }
      var d = dx ? Math.abs(ddx) + Math.abs(ddy) * 3 : Math.abs(ddy) + Math.abs(ddx) * 3;
      if (d < bestD) { bestD = d; best = i; }
    });
    if (best >= 0) { IDX = best; paint(); }
  }
  document.addEventListener('keydown', function (e) {
    var k = e.keyCode;
    var typing = document.activeElement && /INPUT|TEXTAREA/.test(document.activeElement.tagName);
    if (k === 37) { if (!typing) { move(S.rtl ? 1 : -1, 0); e.preventDefault(); } return; }
    if (k === 39) { if (!typing) { move(S.rtl ? -1 : 1, 0); e.preventDefault(); } return; }
    if (k === 38) { move(0, -1); e.preventDefault(); return; }
    if (k === 40) { move(0, 1); e.preventDefault(); return; }
    if (k === 13) { if (typing && document.activeElement.dataset.gpEnter) { ACT[document.activeElement.dataset.gpEnter](document.activeElement); e.preventDefault(); return; } if (!typing) { activate(NODES[IDX]); e.preventDefault(); } return; }
    if (k === 10009 || k === 27 || k === 8) { if (typing && k === 8) { return; } if (modalOpen()) { closeModal(); } else { back(); } e.preventDefault(); return; }
    if (k === 427 || k === 428) { if (S.chan) { go('tv'); } return; }
    if (k === 403) { go('home'); }
  });

  /* ---------- boot ---------- */
  function boot() {
    if ('serviceWorker' in navigator && B.sw) { navigator.serviceWorker.register(B.sw).catch(function () {}); }
    var saved = store('device');
    S.lang = store('lang') || B.default_lang || 'en';
    applyDir();
    if (saved && saved.token) { S.token = saved.token; S.dev = saved; }
    pair().then(function () { return newSession(); }).then(function () { return refresh(); })
      .then(function () { heartbeat(); replay(); })
      .catch(function (e) {
        var cached = store('home');
        if (cached) { S.home = cached; setOnline(false); render(); heartbeat(); } else { splashError(e); }
      });
  }
  function pair() {
    return api('pair', 0, { mac: B.mac, serial: B.serial, model: B.theme && B.theme.model, room_num: B.room_hint, type: B.type || 'tv' }).then(function (d) {
      if (!d) { throw new Error('no answer'); }
      if (d.status === 'active' && d.token) { S.token = d.token; S.dev = d; S.paired = true; store('device', { token: d.token, room_num: d.room_num, id_device: d.id_device }); return d; }
      S.dev = d; S.paired = false; drop('device'); pairingScreen(d); throw new Error('pending');
    });
  }
  function newSession() { return api('session', 0, { lang: S.lang }).then(function (d) { if (!d) { return null; } S.sess = d.token; S.lang = d.lang || S.lang; applyDir(); return d; }); }
  function refresh() { return api('home').then(function (d) { if (d) { S.home = d; S.lang = d.lang || S.lang; applyDir(); store('home', d); render(); } return d; }); }
  function applyDir() { S.rtl = S.lang === 'ar'; document.body.className = 'gp' + (S.rtl ? ' gp-rtl' : ''); document.documentElement.setAttribute('dir', S.rtl ? 'rtl' : 'ltr'); store('lang', S.lang); }
  function splashError(e) { $('gp-app').innerHTML = '<div class="gp-splash"><div class="gp-splash-inner"><h1>' + esc(B.hotel || '') + '</h1><p class="gp-muted">' + esc((e && e.message) || 'Starting…') + '</p><button class="gp-btn" data-gp-focus data-gp-act="reload">Try again</button></div></div>'; wire(); }
  function pairingScreen(d) {
    $('gp-app').innerHTML = '<div class="gp-screen gp-pair"><h1>' + esc(B.hotel || '') + '</h1><h2>' + esc(t('pairing')) + '</h2><p class="gp-muted">' + esc(t('paircode')) + '</p>'
      + '<div class="gp-code">' + esc(d.pair_code || '------') + '</div>'
      + '<p class="gp-muted">' + esc(d.label || '') + (B.mac ? ' &middot; ' + esc(B.mac) : '') + '</p>'
      + '<div class="gp-actions" style="justify-content:center"><button class="gp-btn" data-gp-focus data-gp-act="reload">' + esc(t('ok')) + '</button></div></div>';
    wire();
    setTimeout(function () { boot(); }, 20000);
  }

  /* ---------- heartbeat & commands ---------- */
  function heartbeat() {
    var every = Math.max(20, (B.heartbeat || 60)) * 1000;
    clearInterval(S._hb);
    S._hb = setInterval(function () {
      api('heartbeat', 0, { locale: S.lang, app_version: '1.0.0' }).then(function (d) {
        if (!d) { return; }
        S.unread = d.unread || 0;
        if (d.balance && S.home) { S.home.folio = Object.assign({}, S.home.folio || {}, d.balance); }
        if (d.orders && S.home) { S.home.orders = d.orders; }
        paintBadges();
        (d.commands || []).forEach(handleCommand);
        if (d.commands && d.commands.length) { api('heartbeat', 0, { ack: d.commands.map(function (c) { return c.id; }) }).catch(function () {}); }
      }).catch(function () {});
    }, every);
  }
  function handleCommand(c) {
    var p = c.payload || {};
    if (c.type === 'reload') { setTimeout(function () { location.reload(); }, 1500); return; }
    if (c.type === 'wipe') { drop('home'); drop('cart'); drop('queue'); S.cart = []; S.sess = null; S.home = null; S.thread = []; S.threadLoaded = 0; S.checkout = null; setTimeout(function () { location.reload(); }, 800); return; }
    if (c.type === 'message' || c.type === 'notify') { toast(p.text || t('sent')); if (p.kind === 'message') { S.unread++; S.threadLoaded = 0; paintBadges(); } return; }
    if (c.type === 'order_ready') { toast(t('ready') + (p.check_no ? ' (' + p.check_no + ')' : '')); return; }
    if (c.type === 'folio_refresh') { refresh().catch(function () {}); return; }
    if (c.type === 'lock') { S.sess = null; S.home = null; render(); }
  }
  function paintBadges() { var el = $('gp-badge-msg'); if (el) { el.textContent = S.unread ? S.unread + ' ' + t('messages') : t('messages'); el.className = 'gp-badge' + (S.unread ? ' gp-alert' : ''); } var b = $('gp-badge-bal'); if (b && S.home && S.home.folio) { b.textContent = t('balance') + ' ' + money(S.home.folio.balance); } }

  /* ---------- navigation ---------- */
  function go(view, arg) { if (S.view !== view) { S.hist.push(S.view); } S.view = view; S.arg = arg; render(); }
  function back() { var v = S.hist.pop(); S.view = v || 'home'; render(); }

  function shell(bodyHtml, title) {
    var h = S.home || {};
    var langs = B.languages || { en: 'English' };
    var lb = Object.keys(langs).map(function (k) { return '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="lang" data-lang="' + k + '">' + esc(langs[k]) + '</button>'; }).join('');
    return '<div class="gp-top"><div class="gp-brand">' + (window.GP_LOGO ? '<img src="' + esc(window.GP_LOGO) + '" alt="">' : '') + '<div><div class="gp-clock" id="gp-clock"></div><div class="gp-muted">' + esc(B.hotel || '') + (h.stay ? ' &middot; ' + esc(t('room')) + ' ' + esc(h.stay.room_num) : '') + '</div></div></div>'
      + '<div class="gp-badges">' + (h.weather && h.weather.temp !== null && h.weather.temp !== undefined ? '<span class="gp-badge">' + esc(h.weather.city) + ' ' + esc(h.weather.temp) + '°C ' + esc(h.weather.text || '') + '</span>' : '')
      + (h.in_house ? '<span class="gp-badge" id="gp-badge-bal"></span><span class="gp-badge" id="gp-badge-msg"></span>' : '')
      + '<span class="gp-langbar">' + lb + '</span></div></div>'
      + '<div class="gp-body">' + (title ? '<h2>' + esc(title) + '</h2>' : '') + bodyHtml + '</div>';
  }
  function tile(view, name, sub, icon) { return '<div class="gp-tile" data-gp-focus data-gp-go="' + view + '"><div class="gp-tile-icon">' + icon + '</div><div class="gp-tile-name">' + esc(name) + '</div><div class="gp-tile-sub">' + esc(sub || '') + '</div></div>'; }
  function backBtn() { return '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="back">' + esc(t('back')) + '</button>'; }

  /* ---------- screens ---------- */
  var VIEWS = {};
  function render() {
    var v = VIEWS[S.view] || VIEWS.home;
    $('gp-app').innerHTML = v();
    wire(); clock(); paintBadges();
  }
  function clock() { var el = $('gp-clock'); if (!el) { return; } var f = function () { var d = new Date(); el.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' }); }; f(); clearInterval(S._ck); S._ck = setInterval(f, 20000); }

  VIEWS.home = function () {
    var h = S.home || {};
    var on = function (s) { return (h.sections || B.sections || []).indexOf(s) > -1; };
    var body = '';
    if (h.in_house && h.guest) {
      body += '<h1>' + esc(t('welcome')) + ', ' + esc(h.guest.first || h.guest.name) + '</h1>';
      if (h.stay) { body += '<p class="gp-muted">' + esc(t('room')) + ' ' + esc(h.stay.room_num) + ' &middot; ' + esc(h.stay.room_type || '') + ' &middot; ' + esc(h.stay.date_from) + ' → ' + esc(h.stay.date_to) + ' (' + h.stay.nights + ' ' + esc(t('nights')) + ')</p>'; }
    } else {
      body += '<h1>' + esc(B.hotel || '') + '</h1><p class="gp-muted">' + esc(t('vacant')) + ' — ' + esc(t('vacantsub')) + '</p>';
    }
    (h.promos || []).forEach(function (p) {
      body += '<div class="gp-promo" data-gp-focus data-gp-go="' + esc(p.target || 'directory') + '">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '') + '<div><div class="gp-promo-title">' + esc(p.title) + '</div><div>' + esc(p.body || '') + '</div></div></div>';
    });
    var tiles = '';
    if (h.in_house && on('folio')) { tiles += tile('folio', t('folio'), h.folio ? money(h.folio.balance) : '', '▤'); }
    if (on('dining')) { tiles += tile('dining', t('dining'), (h.orders && h.orders.length ? h.orders.length + ' · ' + esc(h.orders[0].status) : ''), '☗'); }
    if (h.in_house && on('requests')) { tiles += tile('requests', t('requests'), '', '⚑'); }
    if (h.in_house && on('messages')) { tiles += tile('messages', t('messages'), S.unread ? String(S.unread) : '', '✉'); }
    if (on('directory')) { tiles += tile('directory', t('directory'), '', '☷'); }
    if (on('tv')) { tiles += tile('tv', t('tv'), '', '▶'); }
    if (on('vod')) { tiles += tile('vod', t('vod'), '', '★'); }
    if (on('radio')) { tiles += tile('radio', t('radio'), '', '♫'); }
    if (on('games')) { tiles += tile('games', t('games'), '', '❖'); }
    if (on('cast')) { tiles += tile('cast', t('cast'), '', '↯'); }
    if (h.in_house && on('controls')) { tiles += tile('controls', t('controls'), '', '☀'); }
    if (h.in_house && on('checkout')) { tiles += tile('checkout', t('checkout'), h.stay && h.stay.departs_today ? h.stay.checkout_time : '', '⇥'); }
    body += '<div class="gp-tiles">' + tiles + '</div>';
    return shell(body);
  };

  VIEWS.folio = function () {
    var f = (S.home && S.home.folio) || {};
    if (!f.available) { return shell('<p>' + esc(t('vacant')) + '</p><div class="gp-actions">' + backBtn() + '</div>', t('folio')); }
    var rows = (f.lines || []).map(function (l) {
      return '<div class="gp-row"><div class="gp-row-main"><div class="gp-row-title">' + esc(l.description) + '</div><div class="gp-row-sub">' + esc(l.business_date || l.date) + ' · ' + esc(l.department) + '</div></div><div class="gp-amount' + (l.is_payment ? ' gp-pay' : '') + '">' + (l.is_payment ? '-' : '') + money(l.amount) + '</div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    var body = rows + '<div class="gp-total"><span>' + esc(t('balance')) + '</span><span>' + money(f.balance) + '</span></div>'
      + '<p class="gp-muted">' + esc(t('charges')) + ' ' + money(f.charges) + ' · ' + esc(t('payments')) + ' ' + money(f.payments) + '</p>'
      + '<div class="gp-actions">' + backBtn() + '<button class="gp-btn" data-gp-focus data-gp-go="checkout">' + esc(t('express')) + '</button></div>';
    return shell(body, t('folio'));
  };

  VIEWS.dining = function () {
    if (!S.menu) { api('menu').then(function (m) { S.menu = m; render(); }).catch(fail); return shell('<p class="gp-muted">…</p>', t('dining')); }
    if (!S.menu.available) { return shell('<p>' + esc(S.menu.reason || '') + '</p><div class="gp-actions">' + backBtn() + '</div>', t('dining')); }
    var cats = S.menu.categories || [], items = S.menu.items || [];
    var catId = S.arg && S.arg.cat ? Number(S.arg.cat) : (cats.length ? Number(cats[0].id_pulse_pos_category) : 0);
    var head = '<div class="gp-actions">' + cats.map(function (c) { return '<button class="gp-btn ' + (Number(c.id_pulse_pos_category) === catId ? '' : 'gp-ghost') + '" data-gp-focus data-gp-act="cat" data-cat="' + c.id_pulse_pos_category + '">' + esc(c.name) + '</button>'; }).join('') + '</div>';
    if (S.menu.period) { head += '<p class="gp-muted">' + esc(S.menu.period) + '</p>'; }
    var cards = items.filter(function (i) { return Number(i.cat) === catId; }).map(function (i) {
      return '<div class="gp-card" data-gp-focus data-gp-act="add" data-item="' + i.id + '">' + (i.image ? '<img src="' + esc(i.image) + '" alt="">' : '')
        + '<div class="gp-card-body"><div class="gp-card-title">' + esc(i.name) + '</div><div class="gp-card-sub">' + (i.allergens ? '<span class="gp-chip gp-warn">' + esc(i.allergens) + '</span>' : '') + (i.prep ? '<span class="gp-chip">' + i.prep + ' min</span>' : '') + '</div><div class="gp-price">' + money(i.price) + '</div></div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    var cartLines = S.cart.map(function (c, ix) { return '<div class="gp-row" data-gp-focus data-gp-act="rm" data-ix="' + ix + '"><div class="gp-row-main"><div class="gp-row-title">' + c.qty + ' × ' + esc(c.name) + '</div></div><div class="gp-amount">' + money(c.price * c.qty) + '</div></div>'; }).join('');
    var total = S.cart.reduce(function (a, c) { return a + c.price * c.qty; }, 0);
    var orders = ((S.home && S.home.orders) || []).map(function (o) { return '<div class="gp-row"><div class="gp-row-main"><div class="gp-row-title">' + esc(o.check_no || o.status) + '</div><div class="gp-row-sub">' + esc(o.status) + (o.eta_minutes ? ' · ' + o.eta_minutes + ' min' : '') + (o.fail_reason ? ' · ' + esc(o.fail_reason) : '') + '</div></div><div class="gp-amount">' + money(o.total) + '</div></div>'; }).join('');
    var body = head + '<div class="gp-grid">' + cards + '</div>'
      + '<h3 style="margin-top:32px">' + esc(t('cart')) + '</h3>' + (cartLines || '<p class="gp-muted">' + esc(t('empty')) + '</p>')
      + (S.cart.length ? '<div class="gp-total"><span>' + esc(t('total')) + '</span><span>' + money(total) + '</span></div>' : '')
      + (orders ? '<h3 style="margin-top:28px">' + esc(t('order')) + '</h3>' + orders : '')
      + '<div class="gp-actions">' + backBtn() + (S.cart.length ? '<button class="gp-btn" data-gp-focus data-gp-act="placeOrder">' + esc(t('place')) + '</button>' : '') + '</div>';
    return shell(body, t('dining'));
  };

  VIEWS.requests = function () {
    var list = [['housekeeping', '✦'], ['towels', '☲'], ['amenities', '❀'], ['turndown', '☾'], ['maintenance', '⚒'], ['laundry', '☁'], ['mur', '✔'], ['dnd_on', '⛔'], ['wakeup', '⏰'], ['late_checkout', '⏳'], ['transport', '✈'], ['other', '…']];
    var tiles = list.map(function (r) { return '<div class="gp-tile" data-gp-focus data-gp-act="req" data-type="' + r[0] + '"><div class="gp-tile-icon">' + r[1] + '</div><div class="gp-tile-name">' + esc(labelFor(r[0])) + '</div></div>'; }).join('');
    var recent = ((S.home && S.home.requests) || []).map(function (r) { return '<div class="gp-row"><div class="gp-row-main"><div class="gp-row-title">' + esc(r.label) + '</div><div class="gp-row-sub">' + esc(r.date_add) + (r.scheduled_for ? ' · ' + esc(r.scheduled_for) : '') + '</div></div><span class="gp-chip">' + esc(r.status) + '</span></div>'; }).join('');
    return shell('<div class="gp-tiles">' + tiles + '</div>' + (recent ? '<h3 style="margin-top:30px">' + esc(t('requests')) + '</h3>' + recent : '') + '<div class="gp-actions">' + backBtn() + '</div>', t('requests'));
  };
  function labelFor(type) {
    var m = { housekeeping: { en: 'Clean my room', fr: 'Nettoyer la chambre', pcm: 'Clean my room', ar: 'تنظيف الغرفة' }, towels: { en: 'Extra towels', fr: 'Serviettes', pcm: 'More towel', ar: 'مناشف' },
      amenities: { en: 'Toiletries', fr: 'Articles de toilette', pcm: 'Soap and things', ar: 'مستلزمات' }, turndown: { en: 'Turndown', fr: 'Couverture', pcm: 'Prepare bed', ar: 'تجهيز السرير' },
      maintenance: { en: 'Something is broken', fr: 'Une réparation', pcm: 'Something spoil', ar: 'إصلاح' }, laundry: { en: 'Laundry pickup', fr: 'Blanchisserie', pcm: 'Come take my cloth', ar: 'الغسيل' },
      mur: { en: t('mur'), fr: t('mur'), pcm: t('mur'), ar: t('mur') }, dnd_on: { en: t('dnd'), fr: t('dnd'), pcm: t('dnd'), ar: t('dnd') }, wakeup: { en: t('wake'), fr: t('wake'), pcm: t('wake'), ar: t('wake') },
      late_checkout: { en: t('late'), fr: t('late'), pcm: t('late'), ar: t('late') }, transport: { en: 'Taxi / airport', fr: 'Taxi / aéroport', pcm: 'Taxi or airport', ar: 'سيارة' }, other: { en: 'Something else', fr: 'Autre chose', pcm: 'Another thing', ar: 'أخرى' } };
    return (m[type] && (m[type][S.lang] || m[type].en)) || type;
  }

  VIEWS.messages = function () {
    if (!S.threadLoaded) { S.threadLoaded = 1; api('messages').then(function (d) { S.thread = (d && d.thread) || []; S.unread = 0; paintBadges(); render(); }).catch(function (e) { S.threadLoaded = 0; fail(e); }); }
    var msgs = S.thread.map(function (m) { return '<div class="gp-msg gp-' + (m.direction === 'guest' ? 'guest' : 'desk') + '">' + esc(m.body) + '<div class="gp-msg-meta">' + esc(m.staff || '') + ' ' + esc(m.date_add) + '</div></div>'; }).join('') || '<p class="gp-muted">—</p>';
    var body = '<div class="gp-chat">' + msgs + '</div><div class="gp-field"><label>' + esc(t('write')) + '</label><textarea class="gp-input" id="gp-msg" rows="2" data-gp-focus data-gp-enter="sendMsg"></textarea></div>'
      + '<div class="gp-actions">' + backBtn() + '<button class="gp-btn" data-gp-focus data-gp-act="sendMsg">' + esc(t('send')) + '</button></div>';
    return shell(body, t('messages'));
  };

  VIEWS.directory = function () {
    if (!S.dir) { api('directory').then(function (d) { S.dir = d; store('directory', d); render(); }).catch(function () { S.dir = store('directory') || { pages: [] }; render(); }); return shell('<p class="gp-muted">…</p>', t('directory')); }
    var wifi = (S.home && S.home.wifi) || {};
    var cards = (S.dir.pages || []).map(function (p) {
      return '<div class="gp-card" data-gp-focus data-gp-act="page" data-code="' + esc(p.code) + '">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '')
        + '<div class="gp-card-body"><div class="gp-card-title">' + esc(p.title) + '</div><div class="gp-card-sub">' + esc(p.summary || '') + '</div>'
        + (p.opens ? '<div class="gp-chip">' + esc(p.opens) + '</div>' : '') + (p.extension ? '<div class="gp-chip">Ext ' + esc(p.extension) + '</div>' : '') + '</div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    var w = wifi.ssid ? '<div class="gp-promo"><div><div class="gp-promo-title">WiFi: ' + esc(wifi.ssid) + '</div><div>' + esc(wifi.password || '') + '</div></div></div>' : '';
    return shell(w + '<div class="gp-grid">' + cards + '</div><div class="gp-actions">' + backBtn() + '</div>', t('directory'));
  };

  VIEWS.tv = function () {
    if (!S.chan) { api('channels').then(function (d) { S.chan = d; store('channels', d); render(); }).catch(function () { S.chan = store('channels') || { channels: [] }; render(); }); return shell('<p class="gp-muted">…</p>', t('tv')); }
    var rows = (S.chan.channels || []).map(function (c) {
      return '<div class="gp-chan" data-gp-focus data-gp-act="tune" data-url="' + esc(c.url) + '" data-name="' + esc(c.name) + '"><div class="gp-num">' + c.number + '</div>' + (c.logo ? '<img src="' + esc(c.logo) + '" alt="">' : '') + '<div class="gp-row-main"><div class="gp-row-title">' + esc(c.name) + (c.hd ? ' <span class="gp-chip">HD</span>' : '') + '</div><div class="gp-row-sub">' + esc(c.category) + '</div></div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    var adult = S.chan.adult_locked ? '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="pin">' + esc(t('adult')) + '</button>' : '';
    return shell(rows + '<div class="gp-actions">' + backBtn() + adult + '</div>', t('tv'));
  };

  VIEWS.vod = function () {
    if (!S.vod) { api('vod').then(function (d) { S.vod = d; render(); }).catch(fail); return shell('<p class="gp-muted">…</p>', t('vod')); }
    var cards = (S.vod.titles || []).map(function (v) {
      return '<div class="gp-card" data-gp-focus data-gp-act="playVod" data-id="' + v.id + '">' + (v.poster ? '<img src="' + esc(v.poster) + '" alt="">' : '')
        + '<div class="gp-card-body"><div class="gp-card-title">' + esc(v.title) + '</div><div class="gp-card-sub">' + esc(v.year || '') + ' · ' + esc(v.rating) + ' · ' + (v.duration_min || 0) + ' min</div>'
        + '<div class="gp-price">' + (v.free ? esc(t('free')) : money(v.price)) + '</div></div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    return shell('<div class="gp-grid gp-4">' + cards + '</div><div class="gp-actions">' + backBtn() + '</div>', t('vod'));
  };

  VIEWS.radio = function () {
    var list = (S.chan && S.chan.radio) || [];
    if (!S.chan) { api('channels').then(function (d) { S.chan = d; render(); }).catch(fail); }
    var rows = list.map(function (r) { return '<div class="gp-chan" data-gp-focus data-gp-act="tune" data-url="' + esc(r.url) + '" data-name="' + esc(r.name) + '"><div class="gp-num">♫</div><div class="gp-row-main"><div class="gp-row-title">' + esc(r.name) + '</div><div class="gp-row-sub">' + esc(r.genre || '') + '</div></div></div>'; }).join('') || '<p class="gp-muted">—</p>';
    return shell(rows + '<div class="gp-actions">' + backBtn() + '</div>', t('radio'));
  };

  VIEWS.games = function () {
    var list = (S.chan && S.chan.apps) || [];
    if (!S.chan) { api('channels').then(function (d) { S.chan = d; render(); }).catch(fail); }
    var cards = list.map(function (a) { return '<div class="gp-card" data-gp-focus data-gp-act="app" data-url="' + esc(a.url) + '"><div class="gp-card-body"><div class="gp-card-title">' + esc(a.name) + '</div><div class="gp-card-sub">' + esc(a.type) + '</div></div></div>'; }).join('') || '<p class="gp-muted">—</p>';
    return shell('<div class="gp-grid gp-4">' + cards + '</div><div class="gp-actions">' + backBtn() + '</div>', t('games'));
  };

  VIEWS.cast = function () {
    if (!S.castInfo) { api('cast', 0, { protocol: 'chromecast' }).then(function (d) { S.castInfo = d; render(); }).catch(fail); return shell('<p class="gp-muted">…</p>', t('cast')); }
    var c = S.castInfo;
    var body = '<div class="gp-pair"><p class="gp-muted">Open your casting app on the hotel WiFi and enter this code</p><div class="gp-code">' + esc(c.code) + '</div><h3>PIN ' + esc(c.pin) + '</h3><p class="gp-muted">' + esc(c.status) + (c.guest_device ? ' · ' + esc(c.guest_device) : '') + '</p></div>'
      + '<div class="gp-actions" style="justify-content:center">' + backBtn() + '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="castEnd">' + esc(t('close')) + '</button></div>';
    return shell(body, t('cast'));
  };

  VIEWS.controls = function () {
    if (!S.ctl) { api('room_control').then(function (d) { S.ctl = d; render(); }).catch(fail); return shell('<p class="gp-muted">…</p>', t('controls')); }
    if (!S.ctl.enabled) { return shell('<p>' + esc(t('vacant')) + '</p><div class="gp-actions">' + backBtn() + '</div>', t('controls')); }
    var rows = (S.ctl.points || []).map(function (p) {
      var buttons = '';
      if (p.type === 'light' || p.type === 'socket' || p.type === 'tv') { buttons = btn(p.code, 'toggle', p.state === 'on' ? t('off') : t('on')); }
      else if (p.type === 'curtain') { buttons = btn(p.code, 'open', t('open')) + btn(p.code, 'close', t('close')); }
      else if (p.type === 'ac') { buttons = btn(p.code, 'set', t('cooler'), p.value - 1) + btn(p.code, 'set', t('warmer'), p.value + 1) + btn(p.code, 'toggle', p.state === 'on' ? t('off') : t('on')); }
      else { buttons = btn(p.code, 'scene', t('ok')); }
      var val = p.type === 'ac' ? p.value + '°' : (p.state === 'on' || p.state === 'open' ? t('on') : t('off'));
      return '<div class="gp-ctl"><div class="gp-row-main"><div class="gp-row-title">' + esc(p.label) + '</div><div class="gp-row-sub">' + esc(p.type) + (p.online ? '' : ' · offline') + '</div></div><div class="gp-ctl-val">' + esc(val) + '</div><div class="gp-actions" style="margin:0">' + buttons + '</div></div>';
    }).join('') || '<p class="gp-muted">—</p>';
    return shell(rows + '<p class="gp-muted">' + esc(S.ctl.adapter === 'PulseGpControlSimulator' ? 'Preferences are recorded for housekeeping — this room is not wired to a controller.' : '') + '</p><div class="gp-actions">' + backBtn() + '</div>', t('controls'));
    function btn(code, action, label, value) { return '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="ctl" data-code="' + esc(code) + '" data-action="' + action + '"' + (value === undefined ? '' : ' data-value="' + value + '"') + '>' + esc(label) + '</button>'; }
  };

  VIEWS.checkout = function () {
    if (!S.checkout) { api('checkout').then(function (d) { S.checkout = d; render(); }).catch(fail); return shell('<p class="gp-muted">…</p>', t('checkout')); }
    var f = S.checkout.folio || {};
    var rows = (f.lines || []).map(function (l) { return '<div class="gp-row"><div class="gp-row-main"><div class="gp-row-title">' + esc(l.description) + '</div><div class="gp-row-sub">' + esc(l.business_date) + '</div></div><div class="gp-amount' + (l.is_payment ? ' gp-pay' : '') + '">' + (l.is_payment ? '-' : '') + money(l.amount) + '</div></div>'; }).join('');
    var body = rows + '<div class="gp-total"><span>' + esc(t('balance')) + '</span><span>' + money(f.balance) + '</span></div>'
      + '<p class="gp-muted">' + esc(t('checkout')) + ' ' + esc(S.checkout.checkout_time) + ' · ' + esc(t('late')) + ' ≤ ' + esc(S.checkout.late_until) + '</p>'
      + '<div class="gp-actions">' + backBtn()
      + '<button class="gp-btn" data-gp-focus data-gp-act="express">' + esc(t('express')) + '</button>'
      + '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="lateco">' + esc(t('late')) + '</button>'
      + (S.checkout.feedback_given ? '' : '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="rate">' + esc(t('rate')) + '</button>') + '</div>';
    return shell(body, t('checkout'));
  };

  /* ---------- actions ---------- */
  var ACT = {
    back: back,
    reload: function () { location.reload(); },
    lang: function (n) { S.lang = n.dataset.lang; applyDir(); api('language', 0, { lang: S.lang }).then(function () { S.menu = null; S.dir = null; return refresh(); }).catch(function () { render(); }); },
    cat: function (n) { S.arg = { cat: n.dataset.cat }; render(); },
    add: function (n) {
      var i = (S.menu.items || []).filter(function (x) { return String(x.id) === n.dataset.item; })[0];
      if (!i) { return; }
      if (i.mods && i.mods.length) { return modsModal(i); }
      pushCart(i, []);
    },
    rm: function (n) { S.cart.splice(Number(n.dataset.ix), 1); store('cart', S.cart); render(); },
    placeOrder: function () {
      if (!S.cart.length) { return toast(t('empty'), true); }
      var lines = S.cart.map(function (c) { return { id_item: c.id, qty: c.qty, mods: c.mods, note: c.note || '' }; });
      var cid = 'c' + Date.now() + Math.random().toString(36).slice(2, 8);
      api('order', 0, { lines: lines, client_id: cid }).then(function (o) {
        S.cart = []; store('cart', []);
        if (o) { toast(t('sent') + (o.check_no ? ' · ' + o.check_no : '')); }
        return refresh();
      }).catch(fail);
    },
    req: function (n) {
      var type = n.dataset.type;
      if (type === 'wakeup') { return timeModal('wakeup'); }
      if (type === 'late_checkout') { return timeModal('late_checkout'); }
      if (type === 'maintenance' || type === 'other' || type === 'transport') { return textModal(type); }
      send(type, {});
    },
    sendMsg: function () {
      var el = $('gp-msg'); var body = el ? el.value.trim() : '';
      if (!body) { return; }
      el.value = '';
      api('message_send', 0, { body: body }).then(function (d) { if (d) { S.thread = d.thread || S.thread; S.threadLoaded = 1; } else { S.thread.push({ direction: 'guest', body: body, date_add: '' }); } render(); }).catch(fail);
    },
    page: function (n) {
      var p = (S.dir.pages || []).filter(function (x) { return x.code === n.dataset.code; })[0];
      if (!p) { return; }
      modal(p.title, (p.image ? '<img src="' + esc(p.image) + '" style="width:100%;max-height:320px;object-fit:cover;border-radius:14px">' : '')
        + '<p>' + esc(p.body || p.summary || '').replace(/\n/g, '<br>') + '</p>'
        + (p.phone || p.extension ? '<p class="gp-muted">' + esc(p.phone || '') + (p.extension ? ' · ext ' + esc(p.extension) : '') + '</p>' : '')
        + (p.opens ? '<p class="gp-muted">' + esc(p.opens) + '</p>' : '')
        + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-close="1">' + esc(t('ok')) + '</button></div>');
    },
    tune: function (n) {
      var url = n.dataset.url;
      if (window.tizen && window.tizen.tvchannel) { toast(n.dataset.name); }
      modal(n.dataset.name, '<p class="gp-muted">' + esc(url) + '</p><video id="gp-video" src="' + esc(url) + '" autoplay style="width:100%;border-radius:12px" controls></video>'
        + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-close="1">' + esc(t('close')) + '</button></div>');
    },
    app: function (n) { window.open(n.dataset.url, '_blank'); },
    playVod: function (n) {
      var v = (S.vod.titles || []).filter(function (x) { return String(x.id) === n.dataset.id; })[0];
      if (!v) { return; }
      if (!v.free) {
        return modal(v.title, '<p>' + esc(t('paid')) + ' — <span class="gp-price">' + money(v.price) + '</span></p><p class="gp-muted">' + esc(v.synopsis || '') + '</p>'
          + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="vodConfirm" data-id="' + v.id + '">' + esc(t('play')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
      }
      ACT.vodConfirm({ dataset: { id: v.id } });
    },
    vodConfirm: function (n) {
      api('vod_play', Number(n.dataset.id), { pin: S.adultPin }).then(function (d) {
        if (!d) { return; }
        modal(d.title, '<video src="' + esc(d.stream_url) + '" autoplay controls style="width:100%;border-radius:12px"></video>'
          + (d.charged ? '<p class="gp-muted">' + money(d.price) + ' — ' + esc(t('paid')) + '</p>' : '')
          + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-close="1">' + esc(t('close')) + '</button></div>');
      }).catch(fail);
    },
    pin: function () {
      modal(t('pin'), '<div class="gp-field"><input class="gp-input" id="gp-pin" type="password" inputmode="numeric" data-gp-focus data-gp-enter="pinOk"></div>'
        + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="pinOk">' + esc(t('ok')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
    },
    pinOk: function () {
      S.adultPin = ($('gp-pin') && $('gp-pin').value) || '';
      closeModal();
      S.chan = null; S.vod = null;
      api('channels', 0, { pin: S.adultPin }).then(function (d) { S.chan = d; render(); }).catch(function (e) { S.adultPin = ''; fail(e); });
    },
    ctl: function (n) {
      api('room_control', 0, { code: n.dataset.code, action: n.dataset.action, value: n.dataset.value }).then(function (d) { if (d) { S.ctl.points = d.points; render(); } }).catch(fail);
    },
    castEnd: function () { api('cast', 0, { end: 1 }).then(function () { S.castInfo = null; back(); }).catch(fail); },
    express: function () {
      modal(t('express'), '<p>' + esc(t('rate')) + '</p><div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="expressGo">' + esc(t('ok')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
    },
    expressGo: function () { closeModal(); api('checkout', 0, { confirm: 1 }).then(function () { toast(t('thanks')); S.checkout = null; ACT.rate(); }).catch(fail); },
    lateco: function () { timeModal('late_checkout'); },
    rate: function () {
      var stars = function (name) { return '<div class="gp-stars" data-name="' + name + '">' + [1, 2, 3, 4, 5].map(function (i) { return '<span class="gp-star" data-gp-focus data-gp-act="star" data-name="' + name + '" data-v="' + i + '">★</span>'; }).join('') + '</div>'; };
      S.rating = { overall: 0, room: 0, service: 0, fnb: 0, cleanliness: 0 };
      modal(t('rate'), '<div class="gp-field"><label>' + esc(t('rate')) + '</label>' + stars('overall') + '</div>'
        + '<div class="gp-field"><label>' + esc(t('room')) + '</label>' + stars('room') + '</div>'
        + '<div class="gp-field"><label>' + esc(t('dining')) + '</label>' + stars('fnb') + '</div>'
        + '<div class="gp-field"><label>' + esc(t('comment')) + '</label><textarea class="gp-input" id="gp-fb" rows="2" data-gp-focus></textarea></div>'
        + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="rateGo">' + esc(t('submit')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
    },
    star: function (n) {
      S.rating[n.dataset.name] = Number(n.dataset.v);
      [].slice.call(document.querySelectorAll('.gp-star[data-name="' + n.dataset.name + '"]')).forEach(function (s) { s.className = 'gp-star' + (Number(s.dataset.v) <= Number(n.dataset.v) ? ' gp-set' : ''); });
    },
    rateGo: function () {
      var body = Object.assign({}, S.rating, { comment: ($('gp-fb') && $('gp-fb').value) || '', would_return: 1, nps: S.rating.overall ? S.rating.overall * 2 : null });
      closeModal();
      api('feedback', 0, body).then(function () { toast(t('thanks')); }).catch(fail);
    },
    reqSend: function (n) {
      var type = n.dataset.type;
      var when = $('gp-when') ? $('gp-when').value : '';
      var detail = $('gp-detail') ? $('gp-detail').value : '';
      closeModal();
      send(type, { scheduled_for: when, at: when, detail: detail });
    }
  };

  function send(type, extra) {
    var res = type === 'wakeup' ? 'wakeup' : 'request';
    api(res, 0, Object.assign({ type: type }, extra || {})).then(function (r) { toast(r ? (r.label || t('sent')) + ' ✓' : t('queued')); return refresh(); }).catch(fail);
  }
  function pushCart(item, mods) {
    var line = { id: item.id, name: item.name, price: item.price, qty: 1, mods: mods || [] };
    var same = S.cart.filter(function (c) { return c.id === line.id && JSON.stringify(c.mods) === JSON.stringify(line.mods); })[0];
    if (same) { same.qty++; } else { S.cart.push(line); }
    store('cart', S.cart); toast(t('added')); render();
  }
  function modsModal(item) {
    var html = (item.mods || []).map(function (g) {
      var opts = ((S.menu.modifiers || {})[g.id] || []).map(function (m) { return '<button class="gp-btn gp-ghost" data-gp-focus data-gp-act="modPick" data-g="' + g.id + '" data-m="' + m.id_pulse_pos_modifier + '">' + esc(m.name) + (Number(m.price) ? ' +' + money(m.price) : '') + '</button>'; }).join('');
      return '<div class="gp-field"><label>' + esc(g.name) + (Number(g.min) > 0 ? ' *' : '') + '</label><div class="gp-actions" style="margin:0">' + opts + '</div></div>';
    }).join('');
    S.modItem = item; S.modPicked = [];
    modal(item.name, html + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="modDone">' + esc(t('added')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
  }
  ACT.modPick = function (n) { S.modPicked.push({ id: Number(n.dataset.m), qty: 1 }); n.className = 'gp-btn'; };
  ACT.modDone = function () { var i = S.modItem; closeModal(); pushCart(i, S.modPicked); };
  function timeModal(type) {
    modal(labelFor(type), '<div class="gp-field"><label>HH:MM</label><input class="gp-input" id="gp-when" placeholder="07:30" data-gp-focus data-gp-enter="reqSend" data-type="' + type + '"></div>'
      + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="reqSend" data-type="' + type + '">' + esc(t('ok')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
  }
  function textModal(type) {
    modal(labelFor(type), '<div class="gp-field"><textarea class="gp-input" id="gp-detail" rows="3" data-gp-focus></textarea></div>'
      + '<div class="gp-actions"><button class="gp-btn" data-gp-focus data-gp-act="reqSend" data-type="' + type + '">' + esc(t('send')) + '</button><button class="gp-btn gp-ghost" data-gp-focus data-gp-close="1">' + esc(t('cancel')) + '</button></div>');
  }

  window.addEventListener('online', function () { setOnline(true); });
  window.addEventListener('offline', function () { setOnline(false); });
  function start() { if (S._started) { return; } S._started = 1; S.cart = store('cart') || []; boot(); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
  return { go: go, api: api, state: S, toast: toast, closeModal: closeModal };
})();
