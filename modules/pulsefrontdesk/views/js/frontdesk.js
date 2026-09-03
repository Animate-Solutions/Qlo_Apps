/* Pulse Front Desk — room board & check-in/out actions (jQuery from PrestaShop BO) */
(function ($) {
  var $b = $('#pulse-board'); if (!$b.length) return;
  var AJAX = $b.data('ajax'), FOLIO = $b.data('folio'), sel = null, rooms = [];
  function post(action, data) { return $.post(AJAX, $.extend({ ajax: 1, action: action }, data), null, 'json'); }
  function money(v) { return Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

  /* ---------- board ---------- */
  function render() {
    var $g = $('#board-grid'); if (!$g.length) return; $g.empty();
    var floors = {}; rooms.forEach(function (r) { (floors[r.floor || '—'] = floors[r.floor || '—'] || []).push(r); });
    Object.keys(floors).sort().forEach(function (f) {
      var $f = $('<div class="floor">').append('<h4>Floor ' + esc(f) + '</h4>');
      floors[f].forEach(function (r) {
        var flags = '';
        if (r.id_htl_booking && r.booking_status == 1) flags += '<i class="icon-sign-in" title="Arriving"></i> ';
        if (r.booking_status == 2 && r.date_to == $b.data('date')) flags += '<i class="icon-sign-out" title="Departing"></i> ';
        if (r.vip_level > 0) flags += '<i class="icon-star" title="VIP"></i> ';
        if (r.open_tasks > 0) flags += '<i class="icon-wrench" title="' + r.open_tasks + ' open HK task(s)"></i>';
        var bal = r.balance != null && r.booking_status == 2 ? '<span class="bal ' + (r.balance > 0 ? 'due' : '') + '">' + money(r.balance) + '</span>' : '';
        var $r = $('<div class="room hk-' + r.hk_status + '" data-id="' + r.id_room + '">').html(
          '<div class="num">' + esc(r.room_num) + '</div><div class="type">' + esc(r.room_type) + '</div><div class="guest">' + (r.guest ? esc(r.guest) : '<em>' + esc(r.fo_status) + '</em>') + '</div><div class="flags">' + flags + '</div>' + bal);
        if (sel && sel.id_room == r.id_room) $r.addClass('sel');
        $f.append($r);
      });
      $g.append($f);
    });
  }
  function load() { post('Board', { id_hotel: $('#board-hotel').val() || '', date: $b.data('date') }).done(function (d) { rooms = d.rooms || []; render(); if (sel) openDrawer(rooms.filter(function (r) { return r.id_room == sel.id_room; })[0]); }); }
  if (!$b.data('nogrid')) { load(); setInterval(load, 60000); }
  $('#board-refresh').click(load); $('#board-hotel').change(load);

  /* ---------- drawer ---------- */
  function openDrawer(r) {
    if (!r) return; sel = r; $('.room').removeClass('sel'); $('.room[data-id=' + r.id_room + ']').addClass('sel');
    $('#rd-title').text('Room ' + r.room_num + ' · ' + r.room_type + ' · ' + r.hk_status.replace(/_/g, ' '));
    $('#rd-ooo').hide();
    var $g = $('#rd-guest').html('<p><i class="icon-spinner icon-spin"></i></p>');
    if (!r.id_htl_booking) { $g.html('<h4>No booking today</h4><p>Room is ' + esc(r.fo_status) + '.</p>'); }
    else post('BookingCard', { id_booking: r.id_htl_booking }).done(function (d) {
      if (!d.ok) return $g.html('<p class="text-danger">' + esc(d.error) + '</p>');
      var b = d.booking, p = d.profile, f = d.folio, h = '<h4>' + (p.vip_level > 0 ? '<i class="icon-star"></i> ' : '') + esc(b.guest) + (p.blacklisted ? ' <span class="label label-danger">BLACKLIST</span>' : '') + '</h4>';
      h += '<p>' + esc(b.date_from) + ' → ' + esc(b.date_to) + ' · ' + b.nights + ' night(s) · ' + b.adults + 'A ' + b.children + 'C · Order ' + esc(b.order_ref) + (b.company_name ? ' · ' + esc(b.company_name) : '') + '</p>';
      if (p.stays > 0) h += '<p><small>Returning guest: ' + p.stays + ' stay(s), ' + p.nights + ' nights. ' + (p.preferences && p.preferences.pillow ? 'Pillow: ' + esc(p.preferences.pillow) + '. ' : '') + (p.notes ? esc(p.notes) : '') + '</small></p>';
      if (b.id_status == 1) h += '<button class="btn btn-success act-checkin" data-booking="' + b.id + '" data-guest="' + esc(b.guest) + '">Check in</button> ';
      if (b.id_status == 2) {
        h += '<button class="btn btn-primary act-checkout" data-booking="' + b.id + '" data-guest="' + esc(b.guest) + '">Check out</button> <button class="btn btn-default act-move" data-booking="' + b.id + '">Move room</button> <button class="btn btn-default act-extend" data-booking="' + b.id + '" data-to="' + b.date_to + '">Extend/shorten</button> <button class="btn btn-default act-upsell" data-booking="' + b.id + '">Offers</button> ';
        if (f) { h += '<a class="btn btn-default" href="' + FOLIO + '&id_pulse_folio=' + f.id + '&viewpulse_folio">Folio ' + esc(f.no) + '</a><h4>Balance: <span class="' + (f.balance > 0 ? 'text-danger' : '') + '">' + money(f.balance) + '</span></h4><table class="table table-condensed folio-mini"><tbody>';
          f.lines.slice(-6).forEach(function (l) { h += '<tr><td>' + esc(l.date_add.substr(5, 11)) + '</td><td>' + esc(l.description) + '</td><td class="text-right">' + (l.is_payment == 1 ? '-' : '') + money(l.amount_tax_incl) + '</td></tr>'; });
          h += '</tbody></table><div class="form-inline"><input id="qp-desc" class="form-control input-sm" placeholder="Quick charge (e.g. Minibar)"> <input id="qp-amt" class="form-control input-sm" type="number" step="0.01" placeholder="Amount excl" style="width:100px"> <select id="qp-code" class="form-control input-sm"><option>MINI</option><option>RSVC</option><option>LNDY</option><option>MISC</option><option>DMG</option></select> <button class="btn btn-sm btn-default" id="qp-post" data-folio="' + f.id + '">Post</button></div>'; }
      }
      h += '<h4>Traces</h4><ul>'; (d.traces || []).forEach(function (t) { h += '<li>' + esc(t.due_at) + ' — ' + esc(t.text) + '</li>'; });
      h += '</ul><div class="form-inline"><select id="tr-type" class="form-control input-sm"><option value="trace">Trace</option><option value="wake_up">Wake-up</option><option value="message">Message</option></select> <input id="tr-due" type="datetime-local" class="form-control input-sm"> <input id="tr-text" class="form-control input-sm" placeholder="Text"> <button class="btn btn-sm btn-default" id="tr-add" data-booking="' + b.id + '" data-room="' + r.id_room + '">Add</button></div>';
      $g.html(h);
    });
    $('#room-drawer').show(); $('html,body').animate({ scrollTop: $('#room-drawer').offset().top - 60 }, 200);
  }
  $(document).on('click', '.room', function () { openDrawer(rooms.filter(function (r) { return r.id_room == $(this).data('id'); }, this)[0]); });
  $('#rd-close').click(function () { $('#room-drawer').hide(); sel = null; $('.room').removeClass('sel'); });
  $(document).on('click', '.hk-set', function () {
    var st = $(this).data('status');
    if ((st == 'out_of_order' || st == 'out_of_service') && !$('#rd-ooo').is(':visible')) { $('#rd-ooo').show(); return; }
    post('SetHk', { id_room: sel.id_room, status: st, reason: $('#rd-ooo-reason').val(), until: $('#rd-ooo-until').val() }).done(function (d) { d.ok ? load() : alert(d.error); });
  });
  $(document).on('click', '#rd-task-add', function () { post('AddTask', { id_room: sel.id_room, type: $('#rd-task-type').val(), assigned_to: $('#rd-task-who').val(), note: $('#rd-task-note').val() }).done(function () { $('#rd-task-note').val(''); load(); }); });
  $(document).on('click', '#qp-post', function () { post('PostCharge', { id_folio: $(this).data('folio'), code: $('#qp-code').val(), description: $('#qp-desc').val() || $('#qp-code').val(), qty: 1, unit_price: $('#qp-amt').val(), tax_rate: '' }).done(function (d) { d.ok ? load() : alert(d.error); }); });
  $(document).on('click', '#tr-add', function () { post('AddTrace', { type: $('#tr-type').val(), text: $('#tr-text').val(), due_at: $('#tr-due').val().replace('T', ' '), id_booking: $(this).data('booking'), id_room: $(this).data('room') }).done(load); });

  /* ---------- check-in ---------- */
  $(document).on('click', '.act-checkin', function () {
    var id = $(this).data('booking'); $('#ci-booking').val(id); $('#ci-guest').text($(this).data('guest')); $('#ci-error').hide(); $('#ci-idnum').val('');
    post('AvailableRooms', { id_booking: id }).done(function (d) { var $s = $('#ci-room').empty(); $s.append('<option value="">Keep assigned room</option>'); (d.rooms || []).forEach(function (r) { $s.append('<option value="' + r.id_room + '">' + esc(r.room_num) + ' (' + (r.hk_status || 'clean').replace(/_/g, ' ') + ')</option>'); });
      post('Upsells', { id_booking: id }).done(function (u) {
        if (u.suggested_room) { $s.find('option[value=' + u.suggested_room + ']').text(function (i, t) { return '★ ' + t + ' — suggested'; }); $s.val(u.suggested_room); }
        $('#ci-terms').text(u.terms || ''); $('#ci-preauth-note').text(u.preauth_available ? 'Live card authorisation via Pulse Payments' : 'Recorded as manual hold (Pulse Payments not installed)');
        var h = ''; (u.offers || []).forEach(function (o) { h += '<div class="offer"><label><input type="checkbox" class="ci-offer" value=\'' + JSON.stringify(o).replace(/'/g, '&#39;') + '\'> ' + esc(o.name) + '</label><b>' + money(o.total_excl) + ' <small>+tax</small></b></div>'; });
        $('#ci-upsells').html(h || '<em>No offers available</em>'); if ($('#ci-sig').data('clear')) $('#ci-sig').data('clear')();
      });
    });
    $('#ci-modal').modal('show');
  });
  $('#ci-modal').on('shown.bs.modal', function () { pulseInitSignature('#ci-sig'); });
  $('#ci-submit').click(function () {
    post('CheckIn', { id_booking: $('#ci-booking').val(), id_room: $('#ci-room').val(), id_type: $('#ci-idtype').val(), id_number: $('#ci-idnum').val(), issuing_country: $('#ci-country').val(), expiry: $('#ci-expiry').val(),
      deposit: $('#ci-deposit').val(), deposit_method: $('#ci-deposit-method').val(), preauth: $('#ci-preauth').val(), signature: $('#ci-sig-val').val(), signed_name: $('#ci-guest').text(),
      upsells: JSON.stringify($('.ci-offer:checked').map(function () { return JSON.parse($(this).val()); }).get()), early: $('#ci-early').is(':checked') ? 1 : 0, override_dirty: $('#ci-dirty').is(':checked') ? 1 : 0, override_blacklist: $('#ci-blacklist').is(':checked') ? 1 : 0 })
      .done(function (d) { if (!d.ok) return $('#ci-error').text(d.error).show(); $('#ci-modal').modal('hide'); showSuccessMessage('Checked in — folio ' + d.folio); $b.data('nogrid') ? location.reload() : load(); });
  });

  /* ---------- check-out ---------- */
  $(document).on('click', '.act-checkout', function () {
    var id = $(this).data('booking'); $('#co-booking').val(id); $('#co-guest').text($(this).data('guest')); $('#co-error').hide();
    post('BookingCard', { id_booking: id }).done(function (d) {
      var f = d.folio, h = f ? '<p>Folio <strong>' + esc(f.no) + '</strong> · balance <strong class="' + (f.balance > 0 ? 'text-danger' : '') + '">' + money(f.balance) + '</strong></p>' : '<p>No folio</p>';
      $('#co-folio').html(h); $('#co-method').val(f && f.balance > 0 ? 'CASH' : ''); $('#co-method').trigger('change');
      var $c = $('#co-company').empty(); (window.pulseCompanies || []).forEach(function (c) { $c.append('<option value="' + c.id_pulse_company + '">' + esc(c.name) + '</option>'); });
    });
    $('#co-modal').modal('show');
  });
  $('#co-method').change(function () { $('#co-company-row').toggle($(this).val() == 'CL'); $('#co-fx-row').toggle($(this).val() == 'FX'); });
  $('#co-submit').click(function () {
    post('CheckOut', { id_booking: $('#co-booking').val(), settle_method: $('#co-method').val(), id_company: $('#co-company').val(), late_fee: $('#co-late').val(), refund_method: $('#co-refund').val(), fx_iso: $('#co-fx-iso').val(), fx_amount: $('#co-fx-amount').val() })
      .done(function (d) { if (!d.ok) return $('#co-error').text(d.error).show(); $('#co-modal').modal('hide'); showSuccessMessage('Checked out — folio ' + d.folio + ' closed'); $b.data('nogrid') ? location.reload() : load(); });
  });

  /* ---------- extend / shorten ---------- */
  $(document).on('click', '.act-extend', function () { $('#ex-booking').val($(this).data('booking')); $('#ex-to').val($(this).data('to')); $('#ex-error').hide(); $('#ex-modal').modal('show'); });
  $('#ex-submit').click(function () { post('Extend', { id_booking: $('#ex-booking').val(), new_to: $('#ex-to').val() }).done(function (d) { if (!d.ok) return $('#ex-error').text(d.error).show(); $('#ex-modal').modal('hide'); showSuccessMessage('Stay now ' + d.nights + ' night(s)'); $b.data('nogrid') ? location.reload() : load(); }); });
  /* ---------- in-stay upsell ---------- */
  $(document).on('click', '.act-upsell', function () {
    var id = $(this).data('booking');
    post('Upsells', { id_booking: id }).done(function (u) { if (!u.offers || !u.offers.length) return alert('No offers available'); var pick = prompt(u.offers.map(function (o, i) { return (i + 1) + '. ' + o.name + ' — ' + money(o.total_excl) + ' +tax'; }).join('\n') + '\n\nEnter number to post:'); var o = u.offers[parseInt(pick) - 1]; if (!o) return; post('AcceptUpsell', { id_booking: id, offer: JSON.stringify(o) }).done(function (d) { d.ok ? load() : alert(d.error); }); });
  });
  /* ---------- room move ---------- */
  $(document).on('click', '.act-move', function () {
    var id = $(this).data('booking'); $('#mv-booking').val(id); $('#mv-error').hide();
    post('AvailableRooms', { id_booking: id }).done(function (d) { var $s = $('#mv-room').empty(); (d.rooms || []).forEach(function (r) { $s.append('<option value="' + r.id_room + '">' + esc(r.room_num) + ' (' + (r.hk_status || 'clean').replace(/_/g, ' ') + ')</option>'); }); if (!d.rooms.length) $s.append('<option value="">No rooms of this type available</option>'); });
    $('#mv-modal').modal('show');
  });
  $('#mv-submit').click(function () { post('RoomMove', { id_booking: $('#mv-booking').val(), to_room: $('#mv-room').val(), reason: $('#mv-reason').val() }).done(function (d) { if (!d.ok) return $('#mv-error').text(d.error).show(); $('#mv-modal').modal('hide'); $b.data('nogrid') ? location.reload() : load(); }); });
})(jQuery);
