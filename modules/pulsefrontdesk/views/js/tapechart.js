/* Pulse Tape Chart — rooms × dates with drag/drop and resize */
(function ($) {
  var $c = $('#tape-chart'); if (!$c.length) return;
  var AJAX = $c.data('ajax'), WALKIN = $c.data('walkin'), FOLIO = $c.data('folio'), data = null, drag = null, pendingMove = null;
  var CW = 34; // cell width px
  function post(a, d) { return $.post(AJAX, $.extend({ ajax: 1, action: a }, d), null, 'json'); }
  function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
  function addDays(d, n) { var x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return x.toISOString().substr(0, 10); }
  function dayIdx(d) { return Math.round((new Date(d + 'T00:00:00') - new Date(data.start + 'T00:00:00')) / 86400000); }
  function load() {
    post('Data', { start: $('#tc-start').val(), days: $('#tc-days').val() }).done(function (d) { data = d; render(); });
  }
  function render() {
    var $h = $('#tc-table thead').empty(), $b = $('#tc-table tbody').empty(), tr = '<tr><th class="tc-room">Room</th>';
    for (var i = 0; i < data.days; i++) { var d = addDays(data.start, i), dt = new Date(d + 'T00:00:00'); tr += '<th class="tc-d ' + (dt.getDay() == 0 || dt.getDay() == 6 ? 'we' : '') + (d == data.business_date ? ' today' : '') + '" style="width:' + CW + 'px">' + ['S', 'M', 'T', 'W', 'T', 'F', 'S'][dt.getDay()] + '<br>' + dt.getDate() + '</th>'; }
    $h.append(tr + '</tr>');
    var ooo = {}; data.ooo.forEach(function (o) { ooo[o.id_room] = o; });
    var lastType = null;
    data.rooms.forEach(function (r) {
      if (r.room_type !== lastType) { lastType = r.room_type; var blk = data.blocks.filter(function (b) { return b.id_product == r.id_product; }); var $g = $('<tr class="tc-group"><td colspan="' + (data.days + 1) + '">' + esc(r.room_type) + (blk.length ? ' &nbsp; <small>blocks: ' + blk.map(function (b) { return esc(b.code) + ' ' + b.picked_up + '/' + b.blocked + ' (' + b.date_from + '→' + b.date_to + ')'; }).join(' · ') + '</small>' : '') + '</td></tr>'); $b.append($g); }
      var $tr = $('<tr data-room="' + r.id_room + '" data-type="' + r.id_product + '">'), $td = $('<td class="tc-room"><b>' + esc(r.room_num) + '</b> <small>F' + esc(r.floor) + '</small></td>');
      $tr.append($td);
      for (var i = 0; i < data.days; i++) { var d = addDays(data.start, i); $tr.append('<td class="tc-cell ' + (ooo[r.id_room] ? 'ooo' : '') + (d == data.business_date ? ' today' : '') + '" data-date="' + d + '"></td>'); }
      $b.append($tr);
    });
    // stays as absolutely-positioned bars inside the first cell of the row
    data.stays.forEach(function (s) {
      var $tr = $b.find('tr[data-room=' + s.id_room + ']'); if (!$tr.length) return;
      var a = Math.max(0, dayIdx(s.date_from)), z = Math.min(data.days, dayIdx(s.date_to)); if (z <= a) return;
      var cls = s.id_status == 2 ? 'inhouse' : (s.id_status == 3 ? 'departed' : 'reserved'); if (s.id_pulse_group_block) cls += ' group'; if (s.day_use == 1) cls += ' dayuse';
      var $bar = $('<div class="tc-bar ' + cls + '" draggable="' + (s.id_status == 3 ? 'false' : 'true') + '" data-id="' + s.id + '" data-from="' + s.date_from + '" data-to="' + s.date_to + '" data-type="' + s.id_product + '" data-status="' + s.id_status + '" title="' + esc(s.guest) + ' ' + s.date_from + ' → ' + s.date_to + '">' + (s.vip_level > 0 ? '★ ' : '') + esc(s.guest) + (s.balance > 0 ? ' <i>' + Number(s.balance).toFixed(0) + '</i>' : '') + '<span class="tc-handle"></span></div>');
      $bar.css({ left: (a * CW + 2) + 'px', width: ((z - a) * CW - 6) + 'px' });
      $tr.find('td.tc-cell').eq(a).css('position', 'relative').append($bar);
    });
  }
  // --- interactions
  $(document).on('dragstart', '.tc-bar', function (e) { drag = { id: $(this).data('id'), from: $(this).data('from'), to: $(this).data('to'), type: $(this).data('type'), status: $(this).data('status'), offset: Math.floor(e.originalEvent.offsetX / CW) }; e.originalEvent.dataTransfer.setData('text', drag.id); });
  $(document).on('dragover', '.tc-cell', function (e) { e.preventDefault(); $(this).addClass('over'); }).on('dragleave', '.tc-cell', function () { $(this).removeClass('over'); });
  $(document).on('drop', '.tc-cell', function (e) {
    e.preventDefault(); $('.tc-cell').removeClass('over'); if (!drag) return;
    var toRoom = $(this).closest('tr').data('room'), toType = $(this).closest('tr').data('type'), newFrom = addDays($(this).data('date'), -drag.offset);
    if (drag.status == 2) newFrom = drag.from; // in-house: keep arrival
    pendingMove = { id_booking: drag.id, to_room: toRoom, new_from: newFrom, diff: 0 };
    if (toType != drag.type) { $('#tc-diff').modal('show'); } else { doMove(); }
    drag = null;
  });
  $('#tc-diff-ok').click(function () { pendingMove.diff = $('#tc-diff-amt').val(); $('#tc-diff').modal('hide'); doMove(); });
  function doMove() { post('Move', pendingMove).done(function (d) { if (!d.ok) { showErrorMessage(d.error); } load(); }); }
  // resize via handle
  var rs = null;
  $(document).on('mousedown', '.tc-handle', function (e) { e.preventDefault(); e.stopPropagation(); var $bar = $(this).parent(); rs = { $bar: $bar, id: $bar.data('id'), startX: e.pageX, w0: $bar.width(), from: $bar.data('from') }; $bar.attr('draggable', 'false'); });
  $(document).on('mousemove', function (e) { if (!rs) return; rs.$bar.width(Math.max(CW - 6, rs.w0 + e.pageX - rs.startX)); });
  $(document).on('mouseup', function () {
    if (!rs) return; var nights = Math.max(1, Math.round((rs.$bar.width() + 6) / CW)); var newTo = addDays(rs.from, nights); var id = rs.id; rs.$bar.attr('draggable', 'true'); rs = null;
    post('Resize', { id_booking: id, new_to: newTo }).done(function (d) { if (!d.ok) showErrorMessage(d.error); load(); });
  });
  $(document).on('click', '.tc-bar', function (e) { if ($(e.target).hasClass('tc-handle')) return; var id = $(this).data('id'); window.open(FOLIO.replace('AdminPulseFolio', 'AdminPulseArrivals') + '&date=' + $(this).data('from') + '#b' + id, '_blank'); });
  $(document).on('click', '.tc-cell', function (e) { if ($(e.target).closest('.tc-bar').length || $(this).hasClass('ooo')) return; var $tr = $(this).closest('tr'); location.href = WALKIN + '&id_product=' + $tr.data('type') + '&id_room=' + $tr.data('room') + '&from=' + $(this).data('date') + '&to=' + addDays($(this).data('date'), 1); });
  $('#tc-start,#tc-days').change(load); $('#tc-prev').click(function () { $('#tc-start').val(addDays($('#tc-start').val(), -7)); load(); }); $('#tc-next').click(function () { $('#tc-start').val(addDays($('#tc-start').val(), 7)); load(); }); $('#tc-today').click(function () { $('#tc-start').val($c.data('start')); load(); });
  load();
})(jQuery);
