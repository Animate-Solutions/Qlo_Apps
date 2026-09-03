/* Minimal signature pad: <canvas class="sig-pad" data-target="#inputId"> */
(function ($) {
  function init(c) {
    var ctx = c.getContext('2d'), drawing = false, target = $(c).data('target'), ratio = window.devicePixelRatio || 1;
    c.width = c.offsetWidth * ratio; c.height = c.offsetHeight * ratio; ctx.scale(ratio, ratio); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
    function pos(e) { var r = c.getBoundingClientRect(), p = e.touches ? e.touches[0] : e; return [p.clientX - r.left, p.clientY - r.top]; }
    function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); e.preventDefault(); }
    function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); e.preventDefault(); }
    function end() { if (!drawing) return; drawing = false; $(target).val(c.toDataURL('image/png')); }
    c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    c.addEventListener('touchstart', start, { passive: false }); c.addEventListener('touchmove', move, { passive: false }); c.addEventListener('touchend', end);
    $(c).data('clear', function () { ctx.clearRect(0, 0, c.width, c.height); $(target).val(''); });
  }
  $(function () { $('canvas.sig-pad').each(function () { init(this); }); });
  $(document).on('click', '.sig-clear', function () { var f = $($(this).data('pad')).data('clear'); if (f) f(); });
  window.pulseInitSignature = function (sel) { $(sel).each(function () { if (!$(this).data('clear')) init(this); }); };
})(jQuery);
