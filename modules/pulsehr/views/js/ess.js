/* Pulse HR — staff portal. Vanilla JS, no build step, no framework: it has to start on a five-year-old
   Android browser over a 3G link in Port Harcourt. The token lives in sessionStorage, so closing the tab on a
   shared phone signs you out; nothing personal is ever written to localStorage. */
var HR = (function () {
  var B = window.HR_BOOT || {};
  var S = { token: null, exp: 0, me: null, view: 'home', sections: B.sections || {}, busy: false, geo: null, revealUntil: 0 };

  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var money = function (v) { return '₦' + Number(v || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var d2 = function (s) { if (!s) { return ''; } var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); };
  var t2 = function (s) { if (!s) { return ''; } var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); };

  function keep(v) { try { if (v === undefined) { return sessionStorage.getItem('hr_tok'); } if (v === null) { sessionStorage.removeItem('hr_tok'); } else { sessionStorage.setItem('hr_tok', v); } } catch (e) { return null; } return v; }

  function toast(msg, kind) {
    var el = $('hr-toast'); if (!el) { return; }
    el.textContent = msg; el.className = 'hr-toast' + (kind ? ' hr-' + kind : '');
    setTimeout(function () { el.className = 'hr-toast hr-hidden'; }, 4200);
  }

  /* ---------- api ---------- */
  function api(resource, body, id) {
    return new Promise(function (resolve, reject) {
      var x = new XMLHttpRequest();
      var url = B.api + (B.api.indexOf('?') > -1 ? '&' : '?') + 'resource=' + encodeURIComponent(resource) + (id ? '&id=' + encodeURIComponent(id) : '');
      x.open(body ? 'POST' : 'GET', url, true);
      x.setRequestHeader('Content-Type', 'application/json');
      if (S.token) { x.setRequestHeader('X-Pulse-Ess', S.token); }
      x.timeout = 20000;
      x.onload = function () {
        var r = null;
        try { r = JSON.parse(x.responseText); } catch (e) { return reject(new Error('The server sent something we could not read')); }
        if (r && r.ok) { return resolve(r.data); }
        var msg = (r && r.error) ? r.error : 'Something went wrong';
        if (x.status === 401 || x.status === 403) { signOut(msg); }
        reject(new Error(msg));
      };
      x.ontimeout = function () { reject(new Error('The network is slow — try again')); };
      x.onerror = function () { reject(new Error('No connection. Check your data and try again.')); };
      x.send(body ? JSON.stringify(body) : null);
    });
  }

  /* ---------- shell ---------- */
  function signOut(why) {
    if (S.token) { try { api('logout', {}); } catch (e) {} }
    S.token = null; S.me = null; S.revealUntil = 0; keep(null);
    render();
    if (why) { toast(why); }
  }

  function render() {
    var app = $('hr-app');
    if (!B.enabled) { app.innerHTML = screenWrap('<div class="hr-card hr-narrow"><h2>Portal closed</h2><p>The staff portal is closed at the moment. Please see HR.</p></div>'); return; }
    if (!S.token || !S.me) { return renderLogin(); }
    var v = S.view;
    var head = '<div class="hr-top"><div><div class="hr-who">' + esc(S.me.name) + '</div><div class="hr-sub">' + esc(S.me.staff_no) + ' · ' + esc(S.me.department || '') + '</div></div><button id="hr-out">Sign out</button></div>';
    var body = '<div class="hr-body" id="hr-view">' + (v === 'home' ? viewHome() : v === 'roster' ? viewRoster() : v === 'leave' ? viewLeave() : v === 'pay' ? viewPay() : viewMe()) + '</div>';
    var nav = '<div class="hr-nav">' + tab('home', '⏱', 'Clock') + tab('roster', '☷', 'Roster') + tab('leave', '✈', 'Leave') + tab('pay', '₦', 'Payslips') + tab('me', '☺', 'Me') + '</div>';
    app.innerHTML = head + body + nav;
    $('hr-out').onclick = function () { signOut('Signed out'); };
    var btns = app.querySelectorAll('.hr-nav button');
    for (var i = 0; i < btns.length; i++) { btns[i].onclick = (function (name) { return function () { S.view = name; render(); load(); }; })(btns[i].getAttribute('data-v')); }
    wire();
  }
  function tab(name, ico, label) { return '<button data-v="' + name + '" class="' + (S.view === name ? 'on' : '') + '"><span class="hr-ico">' + ico + '</span>' + label + '</button>'; }
  function screenWrap(inner) { return '<div class="hr-screen hr-center">' + inner + '</div>'; }

  /* ---------- login ---------- */
  function renderLogin() {
    $('hr-app').innerHTML = screenWrap(
      '<form class="hr-card hr-narrow" id="hr-login">' +
      '<h1>' + esc(B.hotel || 'Staff portal') + '</h1><p class="hr-muted">Sign in with your staff number and PIN.</p>' +
      '<label for="hr-sn">Staff number</label><input id="hr-sn" name="staff_no" autocomplete="username" autocapitalize="characters" spellcheck="false" required>' +
      '<label for="hr-pin">PIN</label><input id="hr-pin" name="pin" type="password" inputmode="numeric" autocomplete="current-password" required>' +
      '<button class="hr-btn" type="submit">Sign in</button>' +
      '<p class="hr-small hr-muted" style="margin-top:12px">Forgotten your PIN? HR can set a new one. Never share it — your payslip is behind it.</p>' +
      '<div id="hr-login-msg"></div></form>');
    $('hr-login').onsubmit = function (ev) {
      ev.preventDefault();
      if (S.busy) { return; }
      S.busy = true;
      var msg = $('hr-login-msg'); msg.innerHTML = '';
      api('login', { staff_no: $('hr-sn').value, pin: $('hr-pin').value }).then(function (d) {
        S.busy = false; S.token = d.token; S.exp = Date.now() + (d.ttl || 900) * 1000; S.sections = d.sections || S.sections; keep(d.token);
        S.view = 'home'; boot();
      }).catch(function (e) { S.busy = false; msg.innerHTML = '<div class="hr-err" style="margin-top:12px">' + esc(e.message) + '</div>'; $('hr-pin').value = ''; });
    };
  }

  /* ---------- home / clock ---------- */
  function viewHome() {
    var m = S.me || {}, dir = S.next || 'in';
    var s = S.sections.clock && S.sections.clock.on;
    var h = '<div class="hr-card"><div class="hr-clock" id="hr-now">--:--</div><p class="hr-muted" style="text-align:center;margin:0">' + esc(new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })) + '</p></div>';
    if (s) {
      h += '<div class="hr-card"><button class="hr-btn hr-big" id="hr-punch">Clock ' + (dir === 'in' ? 'IN' : 'OUT') + '</button>' +
        '<p class="hr-small hr-muted" id="hr-geo-note" style="margin-top:10px">Your phone will share its location so the hotel can confirm you clocked on site.</p>' +
        '<details style="margin-top:8px"><summary class="hr-small">Entrance QR code (if your location will not work)</summary><input id="hr-qr" placeholder="Code posted at the staff entrance" autocapitalize="none" spellcheck="false"></details>' +
        '<div id="hr-punch-msg"></div></div>';
    } else if (S.sections.clock) {
      h += '<div class="hr-card"><div class="hr-warn">' + esc(S.sections.clock.why || 'Mobile clocking is not available') + '</div></div>';
    }
    h += '<div class="hr-card"><h3>Next shift</h3>' + (S.shift ? shiftRow(S.shift) : '<p class="hr-muted">Nothing published yet.</p>') + '</div>';
    if (S.punches && S.punches.length) {
      h += '<div class="hr-card"><h3>Your recent clockings</h3><table class="hr-t"><tbody>';
      for (var i = 0; i < Math.min(6, S.punches.length); i++) { var p = S.punches[i];
        h += '<tr><td>' + d2(p.punched_at) + ' ' + t2(p.punched_at) + '</td><td>' + esc(p.direction.toUpperCase()) + '</td><td>' + esc(p.source) + '</td><td>' + pill(p.status) + '</td></tr>'; }
      h += '</tbody></table></div>';
    }
    if (S.cases && S.cases.length) {
      var open = [];
      for (var j = 0; j < S.cases.length; j++) { if (!S.cases[j].acknowledged_at) { open.push(S.cases[j]); } }
      if (open.length) { h += '<div class="hr-card"><h3>Needs your acknowledgement</h3>' + open.map(caseRow).join('') + '</div>'; }
    }
    return h;
  }
  function pill(st) { var k = st === 'accepted' ? 'ok' : (st === 'rejected' ? 'bad' : 'warn'); return '<span class="hr-pill ' + k + '">' + esc(st) + '</span>'; }
  function shiftRow(s) {
    if (s.is_off === '1' || s.is_off === 1) { return '<div class="hr-shift"><span class="d">' + d2(s.roster_date) + '</span><span class="off">Off</span></div>'; }
    return '<div class="hr-shift"><span class="d">' + d2(s.roster_date) + '</span><span>' + esc(s.shift_name || '') + '</span><span>' + String(s.start_time || '').substr(0, 5) + '–' + String(s.end_time || '').substr(0, 5) + (Number(s.night) ? ' ☾' : '') + '</span></div>';
  }
  function caseRow(c) {
    return '<div class="hr-row"><div><strong>' + esc(c.subject) + '</strong><div class="hr-small hr-muted">' + esc(String(c.type).replace(/_/g, ' ')) + ' · ' + d2(c.issued_on) + '</div></div>' +
      '<button class="hr-btn hr-ghost hr-ack" data-id="' + c.id_pulse_hr_case + '" style="width:auto;margin:0;padding:8px 12px">I have seen this</button></div>';
  }

  function punch() {
    if (S.busy) { return; }
    S.busy = true;
    var btn = $('hr-punch'); btn.disabled = true; btn.textContent = 'Getting your location…';
    var qr = $('hr-qr') ? $('hr-qr').value : '';
    var send = function (pos) {
      var body = { qr: qr, device: navigator.platform || '' };
      if (pos && pos.coords) { body.lat = pos.coords.latitude; body.lng = pos.coords.longitude; body.accuracy = pos.coords.accuracy; }
      api('clock', body).then(function (d) {
        S.busy = false; btn.disabled = false;
        $('hr-punch-msg').innerHTML = '<div class="' + (d.status === 'rejected' ? 'hr-err' : (d.status === 'flagged' ? 'hr-warn' : 'hr-ok')) + '" style="margin-top:10px">' + esc(d.message) +
          (d.distance_m != null ? '<br><span class="hr-small">about ' + Math.round(d.distance_m) + ' m from the hotel' + (d.accuracy_m ? ', GPS accurate to ' + Math.round(d.accuracy_m) + ' m' : '') + '</span>' : '') + '</div>';
        load();
      }).catch(function (e) { S.busy = false; btn.disabled = false; btn.textContent = 'Clock ' + ((S.next || 'in') === 'in' ? 'IN' : 'OUT'); $('hr-punch-msg').innerHTML = '<div class="hr-err" style="margin-top:10px">' + esc(e.message) + '</div>'; });
    };
    if (!navigator.geolocation) { return send(null); }
    navigator.geolocation.getCurrentPosition(send, function () { send(null); }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
  }

  /* ---------- roster ---------- */
  function viewRoster() {
    if (!S.roster) { return '<div class="hr-card"><p class="hr-muted">Loading your shifts…</p></div>'; }
    if (!S.roster.length) { return '<div class="hr-card"><h3>Your shifts</h3><p class="hr-muted">Nothing has been published for the next two weeks. Your supervisor publishes the roster from the office.</p></div>'; }
    var h = '<div class="hr-card"><h3>Your shifts</h3>';
    for (var i = 0; i < S.roster.length; i++) {
      var s = S.roster[i];
      h += '<div class="hr-shift"><span class="d">' + new Date(s.roster_date + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) + '</span>' +
        (Number(s.is_off) ? '<span class="off">Off</span>' : '<span>' + esc(s.shift_name || '') + '</span><span>' + String(s.start_time || '').substr(0, 5) + '–' + String(s.end_time || '').substr(0, 5) + '</span>') +
        (Number(s.is_off) ? '' : '<button class="hr-btn hr-ghost hr-swap" data-id="' + s.id_pulse_hr_roster + '" style="width:auto;margin:0;padding:6px 10px;font-size:13px">Ask a swap</button>') + '</div>';
    }
    return h + '</div>';
  }

  /* ---------- leave ---------- */
  function viewLeave() {
    var h = '<div class="hr-card"><h3>Your leave</h3>';
    if (!S.leave) { h += '<p class="hr-muted">Loading…</p>'; }
    else {
      for (var i = 0; i < S.leave.balances.length; i++) {
        var b = S.leave.balances[i];
        var ent = Number(b.entitlement) || 0, av = Number(b.available) || 0;
        h += '<div class="hr-kv"><span>' + esc(b.name) + '</span><span><strong>' + av + '</strong> day' + (av === 1 ? '' : 's') + ' left' + (ent ? ' <span class="hr-muted hr-small">of ' + ent + '</span>' : '') + '</span></div>' +
          (ent ? '<div class="hr-bar"><span style="width:' + Math.max(0, Math.min(100, Math.round(av / ent * 100))) + '%"></span></div>' : '');
      }
    }
    h += '</div>';
    h += '<form class="hr-card" id="hr-leave-form"><h3>Ask for leave</h3>' +
      '<label>Type</label><select name="id_leave_type" id="hr-lt">' + (S.leaveTypes || []).map(function (t) { return '<option value="' + t.id_pulse_hr_leave_type + '">' + esc(t.name) + '</option>'; }).join('') + '</select>' +
      '<label>From</label><input type="date" name="date_from" id="hr-lf" required>' +
      '<label>To</label><input type="date" name="date_to" id="hr-lto" required>' +
      '<label>Reason</label><textarea name="reason" id="hr-lr" rows="2"></textarea>' +
      '<label>Phone while you are away</label><input name="contact_phone" id="hr-lp" inputmode="tel">' +
      '<button class="hr-btn" type="submit">Send to my manager</button><div id="hr-leave-msg"></div></form>';
    if (S.leave && S.leave.requests && S.leave.requests.length) {
      h += '<div class="hr-card"><h3>Your requests</h3><table class="hr-t"><tbody>';
      for (var j = 0; j < S.leave.requests.length; j++) { var r = S.leave.requests[j];
        h += '<tr><td>' + esc(r.type_name) + '<div class="hr-small hr-muted">' + d2(r.date_from) + ' – ' + d2(r.date_to) + ' · ' + r.days + ' day(s)</div></td>' +
          '<td style="text-align:right">' + pillLeave(r.status) + (r.status === 'pending' ? '<br><button class="hr-btn hr-ghost hr-withdraw" data-id="' + r.id_pulse_hr_leave_request + '" style="width:auto;margin-top:6px;padding:6px 10px;font-size:13px">Withdraw</button>' : '') + '</td></tr>'; }
      h += '</tbody></table></div>';
    }
    return h;
  }
  function pillLeave(st) { var k = (st === 'approved' || st === 'taken') ? 'ok' : (st === 'rejected' || st === 'cancelled' ? 'bad' : 'warn'); return '<span class="hr-pill ' + k + '">' + esc(st) + '</span>'; }

  /* ---------- payslips ---------- */
  function viewPay() {
    var sec = S.sections.payslips || { on: 0, why: '' };
    if (!sec.on) { return '<div class="hr-card"><h3>Payslips</h3><div class="hr-warn">' + esc(sec.why || 'Payslips are not available here') + '</div></div>'; }
    if (!S.pay || S.pay.locked) {
      return '<form class="hr-card" id="hr-reveal"><h3>Payslips</h3><div class="hr-locked"><p class="hr-muted">Your pay is private. Enter your PIN again to see it, and it will hide itself after a few minutes.</p>' +
        '<input type="password" inputmode="numeric" id="hr-rpin" placeholder="PIN" autocomplete="current-password"><button class="hr-btn" type="submit">Show my payslips</button></div><div id="hr-reveal-msg"></div></form>';
    }
    if (!S.pay.available) { return '<div class="hr-card"><h3>Payslips</h3><div class="hr-warn">' + esc(S.pay.why || 'Not available') + '</div></div>'; }
    if (!S.pay.rows || !S.pay.rows.length) { return '<div class="hr-card"><h3>Payslips</h3><p class="hr-muted">Nothing published yet.</p></div>'; }
    var h = '<div class="hr-card"><h3>Payslips</h3><p class="hr-small hr-muted">Hides again at ' + new Date(S.revealUntil).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) + '. Do not show this screen to anyone.</p><table class="hr-t"><tbody>';
    for (var i = 0; i < S.pay.rows.length; i++) {
      var p = S.pay.rows[i];
      var period = p.period || p.period_label || p.pay_period || p.month || '';
      var net = p.net_pay !== undefined ? p.net_pay : (p.net !== undefined ? p.net : null);
      h += '<tr><td>' + esc(period) + '</td><td style="text-align:right">' + (net === null ? '<span class="hr-muted">see payroll</span>' : money(net)) + '</td></tr>';
    }
    return h + '</tbody></table></div>';
  }

  /* ---------- me ---------- */
  function viewMe() {
    var m = S.me || {};
    var h = '<div class="hr-card"><h3>Your details</h3>' +
      kv('Staff number', m.staff_no) + kv('Department', m.department) + kv('Section', m.section) + kv('Position', m.position) + kv('Grade', m.grade) +
      kv('Manager', m.manager) + kv('Status', m.status) + kv('Joined', d2(m.hire_date)) + kv('Contract', m.contract_type ? String(m.contract_type).replace(/_/g, ' ') + (m.contract_to ? ' to ' + d2(m.contract_to) : '') : '') +
      kv('Bank', (m.bank_name || '') + ' ' + (m.account_masked || '')) + kv('RSA PIN', m.rsa_pin) + kv('PFA', m.pfa) +
      kv('NHF deduction', Number(m.nhf_consent) ? 'You consented on ' + d2(m.nhf_consent_date) : 'Not consented — nothing is deducted') + '</div>';
    h += '<form class="hr-card" id="hr-change"><h3>Ask HR to change a detail</h3>' +
      '<label>Detail</label><select id="hr-cf"><option value="phone">Phone</option><option value="phone_alt">Second phone</option><option value="email">Email</option>' +
      '<option value="address">Address</option><option value="city">Town</option><option value="marital">Marital status</option>' +
      '<option value="nok_name">Next of kin name</option><option value="nok_relationship">Next of kin relationship</option><option value="nok_phone">Next of kin phone</option>' +
      '<option value="bank_name">Bank</option><option value="account_no">Account number</option><option value="account_name">Account name</option></select>' +
      '<label>New value</label><input id="hr-cv" required><button class="hr-btn hr-alt" type="submit">Send to HR</button><div id="hr-change-msg"></div></form>';
    if (S.docs && S.docs.length) {
      h += '<div class="hr-card"><h3>Your documents</h3><table class="hr-t"><tbody>';
      for (var i = 0; i < S.docs.length; i++) { var d = S.docs[i];
        h += '<tr><td>' + esc(d.name) + '</td><td style="text-align:right">' + (d.expires_on ? d2(d.expires_on) + ' ' + docPill(d.status) : '<span class="hr-muted">no expiry</span>') + '</td></tr>'; }
      h += '</tbody></table></div>';
    }
    if (S.cases && S.cases.length) {
      h += '<div class="hr-card"><h3>Your record</h3>' + S.cases.map(function (c) {
        return '<div class="hr-row"><div><strong>' + esc(c.subject) + '</strong><div class="hr-small hr-muted">' + esc(String(c.type).replace(/_/g, ' ')) + ' · ' + d2(c.issued_on) + '</div></div>' +
          (c.acknowledged_at ? '<span class="hr-pill">seen</span>' : '<button class="hr-btn hr-ghost hr-ack" data-id="' + c.id_pulse_hr_case + '" style="width:auto;margin:0;padding:8px 12px">I have seen this</button>') + '</div>';
      }).join('') + '</div>';
    }
    return h;
  }
  function kv(k, v) { return v ? '<div class="hr-kv"><span class="hr-muted">' + esc(k) + '</span><span>' + esc(v) + '</span></div>' : ''; }
  function docPill(st) { return '<span class="hr-pill ' + (st === 'valid' ? 'ok' : (st === 'expired' ? 'bad' : 'warn')) + '">' + esc(st) + '</span>'; }

  /* ---------- wiring ---------- */
  function wire() {
    if ($('hr-punch')) { $('hr-punch').onclick = punch; }
    if ($('hr-leave-form')) {
      $('hr-leave-form').onsubmit = function (ev) {
        ev.preventDefault();
        api('leave_request', { id_leave_type: $('hr-lt').value, date_from: $('hr-lf').value, date_to: $('hr-lto').value, reason: $('hr-lr').value, contact_phone: $('hr-lp').value })
          .then(function (d) { $('hr-leave-msg').innerHTML = '<div class="hr-ok" style="margin-top:10px">Sent — ' + esc(d.request_no) + ', ' + d.days + ' day(s). Your manager will see it.</div>'; load(); })
          .catch(function (e) { $('hr-leave-msg').innerHTML = '<div class="hr-err" style="margin-top:10px">' + esc(e.message) + '</div>'; });
      };
    }
    if ($('hr-reveal')) {
      $('hr-reveal').onsubmit = function (ev) {
        ev.preventDefault();
        api('reveal', { pin: $('hr-rpin').value }).then(function (d) {
          S.revealUntil = Date.now() + (d.seconds || 300) * 1000;
          api('payslips', null).then(function (p) { S.pay = p; render(); setTimeout(function () { if (Date.now() >= S.revealUntil) { S.pay = { locked: 1 }; if (S.view === 'pay') { render(); } } }, (d.seconds || 300) * 1000 + 500); });
        }).catch(function (e) { $('hr-reveal-msg').innerHTML = '<div class="hr-err" style="margin-top:10px">' + esc(e.message) + '</div>'; });
      };
    }
    if ($('hr-change')) {
      $('hr-change').onsubmit = function (ev) {
        ev.preventDefault();
        api('update_request', { field: $('hr-cf').value, value: $('hr-cv').value })
          .then(function () { $('hr-change-msg').innerHTML = '<div class="hr-ok" style="margin-top:10px">Sent to HR. They will confirm it.</div>'; $('hr-cv').value = ''; })
          .catch(function (e) { $('hr-change-msg').innerHTML = '<div class="hr-err" style="margin-top:10px">' + esc(e.message) + '</div>'; });
      };
    }
    each('.hr-ack', function (b) { b.onclick = function () { api('acknowledge', { id_case: b.getAttribute('data-id') }).then(function () { toast('Thank you — recorded'); load(); }).catch(function (e) { toast(e.message); }); }; });
    each('.hr-withdraw', function (b) { b.onclick = function () { if (!confirm('Withdraw this request?')) { return; } api('leave_request', { cancel: 1, id_request: b.getAttribute('data-id') }).then(function () { toast('Withdrawn'); load(); }).catch(function (e) { toast(e.message); }); }; });
    each('.hr-swap', function (b) { b.onclick = function () { var who = prompt('Staff number of the colleague you are asking to take it:'); if (!who) { return; } swapTo(b.getAttribute('data-id'), who); }; });
  }
  function each(sel, fn) { var n = document.querySelectorAll(sel); for (var i = 0; i < n.length; i++) { fn(n[i]); } }
  function swapTo(idRoster, staffNo) {
    // the colleague is named by staff number; the server resolves it, and a supervisor still has to approve
    api('swap', { id_roster: idRoster, staff_no: String(staffNo).trim(), reason: 'Requested from the staff portal' })
      .then(function () { toast('Swap requested — your supervisor will decide'); load(); }).catch(function (e) { toast(e.message); });
  }

  /* ---------- data ---------- */
  function load() {
    if (!S.token) { return; }
    api('me', null).then(function (d) { S.me = d.employee; S.sections = d.sections; S.next = d.next_direction; paint(); }).catch(function () {});
    api('roster', null).then(function (d) { S.roster = d.shifts || []; S.shift = S.roster.length ? S.roster[0] : null; paint(); }).catch(function () {});
    api('leave_balance', null).then(function (d) { S.leave = d; paint(); }).catch(function () {});
    api('leave_types', null).then(function (d) { S.leaveTypes = d; paint(); }).catch(function () {});
    api('punches', null).then(function (d) { S.punches = d; paint(); }).catch(function () {});
    api('documents', null).then(function (d) { S.docs = d; paint(); }).catch(function () {});
    api('cases', null).then(function (d) { S.cases = d; paint(); }).catch(function () {});
  }
  var painting = null;
  function paint() { clearTimeout(painting); painting = setTimeout(render, 60); }

  function clockTick() { var el = $('hr-now'); if (el) { el.textContent = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); } }

  function boot() {
    render();
    load();
    setInterval(clockTick, 10000); clockTick();
    setInterval(function () { if (S.token && S.exp && Date.now() > S.exp) { signOut('Your session timed out'); } }, 30000);
  }

  return { start: function () {
    S.token = keep();
    if (!S.token) { return render(); }
    S.exp = Date.now() + (B.ttl || 1800) * 1000;
    api('me', null).then(function (d) { S.me = d.employee; S.sections = d.sections; S.next = d.next_direction; boot(); }).catch(function () { S.token = null; keep(null); render(); });
  } };
})();
HR.start();
