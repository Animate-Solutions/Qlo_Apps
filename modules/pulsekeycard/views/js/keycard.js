/* Pulse Key Card — desk helpers and the local encoder agent bridge (jQuery from the PrestaShop BO).
 *
 * Why a browser-side bridge at all: Onity HT/Advance and Hune drive the encoder from a service that only
 * listens on the front-desk PC (127.0.0.1). The web server usually cannot reach it — it may not even be on
 * the same network. So for adapters marked local_only the clerk's own browser calls
 * http://127.0.0.1:<agent port>/ and reports the result back to Pulse; for everything else (Salto, Dormakaba,
 * the simulator) Pulse calls the vendor from the server and the browser only asks for a status refresh.
 * Either way the key row, its audit entry and its retry job are written server-side — the browser is a
 * transport of convenience, never the source of truth.
 */
(function ($) {
  var $desk = $('#pulse-kc-desk'), $enc = $('#pulse-kc-encoders'), $root = $desk.length ? $desk : $enc;
  if (!$root.length) return;
  var AJAX = $root.data('ajax'), AGENT = parseInt($root.data('agent'), 10) === 1, PORT = parseInt($root.data('agent-port'), 10) || 7070;

  function post(action, data) { return $.post(AJAX, $.extend({ ajax: 1, action: action }, data), null, 'json'); }
  function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
  function note(html, cls) {
    var $b = $('#kc-encoder-state');
    if (!$b.length) { return; }
    $b.attr('class', 'alert alert-' + (cls || 'info')).html(html).show();
  }

  /* ---------- local encoder agent ---------- */

  /** Ask the agent on this machine. Resolves with {ok, ...} and never rejects — a dead agent is an answer. */
  function agent(path, body, timeout) {
    var d = $.Deferred();
    if (!AGENT) { return d.resolve({ ok: false, error: 'Local encoder agent is switched off in settings' }).promise(); }
    $.ajax({
      url: 'http://127.0.0.1:' + PORT + '/' + path, type: body ? 'POST' : 'GET', dataType: 'json',
      data: body ? JSON.stringify(body) : null, contentType: 'application/json', timeout: timeout || 6000
    }).done(function (r) { d.resolve($.extend({ ok: true }, r)); })
      .fail(function (x) { d.resolve({ ok: false, error: x.status === 0 ? 'No encoder agent answering on 127.0.0.1:' + PORT : 'Agent HTTP ' + x.status }); });
    return d.promise();
  }

  /** Report what the agent said so the encoder row goes green/red for everyone. */
  function report(id, ok, error) { return post('agentReport', { id_encoder: id, ok: ok ? 1 : 0, error: error || '' }); }

  function currentEncoder() {
    var $s = $('#kc-encoder');
    if (!$s.length) return { id: parseInt($root.data('encoder'), 10) || 0, local: false, ref: '' };
    var $o = $s.find('option:selected');
    return { id: parseInt($s.val(), 10) || 0, local: $o.data('local') == 1, ref: $o.data('ref') || '' };
  }

  /** Test: local-only encoders go through the agent, everything else through the server. */
  function testEncoder(id, local) {
    if (local) {
      note('<i class="icon-spinner icon-spin"></i> Asking the encoder agent on this PC…');
      return agent('status', null).done(function (r) {
        report(id, r.ok, r.error);
        if (r.ok) { note('Encoder ready on this PC' + (r.firmware ? ' — ' + esc(r.firmware) : ''), 'success'); }
        else { note('<strong>Encoder offline.</strong> ' + esc(r.error) + ' Issue a mechanical key and log it; Pulse will encode the card when the agent is back.', 'danger'); }
      });
    }
    note('<i class="icon-spinner icon-spin"></i> Testing the encoder from the server…');
    return post('testEncoder', { id_encoder: id }).done(function (r) {
      if (r && r.ok) { note('Encoder online' + (r.message ? ' — ' + esc(r.message) : ''), 'success'); }
      else { note('<strong>Encoder offline.</strong> ' + esc(r && r.error ? r.error : 'no answer') + ' Issue a mechanical key.', 'danger'); }
    }).fail(function () { note('Could not reach Pulse to test the encoder — check the network.', 'danger'); });
  }

  $('#kc-test').on('click', function (e) { e.preventDefault(); var c = currentEncoder(); testEncoder(c.id, c.local); });
  $enc.on('click', '.kc-agent-test', function (e) {
    e.preventDefault();
    var $b = $(this), id = $b.data('id');
    $b.prop('disabled', true).text('…');
    agent('status', null).done(function (r) {
      report(id, r.ok, r.error);
      $b.prop('disabled', false).text('Test from this PC');
      alert(r.ok ? 'Encoder agent answering on this PC' + (r.firmware ? ' (' + r.firmware + ')' : '') : 'No answer: ' + r.error);
    });
  });

  /* ---------- read the card sitting on the encoder ---------- */

  function showCard(card) {
    var h = '';
    if (!card || !card.ok) { h = '<div class="alert alert-warning">' + esc(card && card.raw ? card.raw : 'Nothing readable on the encoder') + '</div>'; }
    else {
      h += '<table class="table table-condensed">';
      h += '<tr><th>Card serial</th><td><code>' + esc(card.card_serial) + '</code></td></tr>';
      h += '<tr><th>Rooms</th><td>' + esc((card.room_nums || []).join(', ')) + '</td></tr>';
      h += '<tr><th>Valid</th><td>' + esc(card.valid_from) + ' → ' + esc(card.valid_to) + '</td></tr>';
      h += '<tr><th>Type</th><td>' + esc(card.type) + '</td></tr>';
      if (card.key) {
        h += '<tr><th>Pulse key</th><td>' + esc(card.key.key_no) + ' · ' + esc(card.key.status) + '</td></tr>';
        h += '<tr><th>Issued to</th><td>' + esc(card.key.guest_name) + '</td></tr>';
      } else { h += '<tr><th>Pulse key</th><td><em>not a card this property issued</em></td></tr>'; }
      h += '</table>';
    }
    $('#kc-card-body').html(h);
    $('#kc-card-modal').modal('show');
  }

  $('#kc-read').on('click', function (e) {
    e.preventDefault();
    var c = currentEncoder();
    if (c.local) {
      agent('read', { encoder: c.ref }).done(function (r) {
        report(c.id, r.ok, r.error);
        if (!r.ok) { return showCard({ ok: false, raw: r.error }); }
        showCard(r.card || r);
      });
      return;
    }
    post('readCard', { id_encoder: c.id }).done(function (r) {
      if (!r || !r.ok) { return showCard({ ok: false, raw: r && r.error ? r.error : 'Encoder did not answer' }); }
      showCard(r.card);
    });
  });

  /* ---------- keyboard-first desk ---------- */

  // Enter in the search box submits; Esc clears it; F2 jumps back to it from anywhere on the screen.
  $(document).on('keydown', function (e) {
    if (e.which === 113) { e.preventDefault(); $('#kc-q').focus().select(); }
  });
  $('#kc-q').on('keydown', function (e) { if (e.which === 27) { $(this).val('').focus(); } });

  // Guard the destructive buttons that carry no confirm of their own.
  $desk.on('submit', 'form', function () {
    var $btn = $(this).find('button[type=submit]:focus, button:focus').first();
    if ($btn.attr('name') === 'issueKey' && !$(this).find('input[name="rooms[]"]:checked').length && !$(this).find('input[name="doors[]"]:checked').length) {
      alert('Tick at least one room or one common door before cutting the key.');
      return false;
    }
    return true;
  });

  /* ---------- background status refresh ---------- */

  if ($desk.length) {
    setInterval(function () {
      post('dashboard').done(function (r) {
        if (!r || !r.ok) return;
        var k = r.kpi;
        $('.kpis .col-md-2').eq(0).find('span').text(k.issued_today);
        $('.kpis .col-md-2').eq(1).find('span').text(k.active);
        $('.kpis .col-md-2').eq(2).find('span').text(k.mobile);
        $('.kpis .col-md-2').eq(3).find('span').text(k.failed).parent().toggleClass('text-danger', k.failed > 0);
        $('.kpis .col-md-2').eq(4).find('span').text(k.encoders_offline).parent().toggleClass('text-danger', k.encoders_offline > 0);
        $('.kpis .col-md-2').eq(5).find('span').text(k.low_battery).parent().toggleClass('text-warning', k.low_battery > 0);
      });
    }, 60000);
  }
})(jQuery);
