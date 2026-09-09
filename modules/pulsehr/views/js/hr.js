/* Pulse HR back office — small helpers only; the screens are server-rendered Bootstrap 3. */
(function () {
  function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

  ready(function () {
    /* Fill a whole roster row (a person's week) from one shift, so a six-on-one-off week is two clicks. */
    var fills = document.querySelectorAll('[data-hr-fill]');
    for (var i = 0; i < fills.length; i++) {
      fills[i].addEventListener('change', function () {
        var row = this.getAttribute('data-hr-fill'), v = this.value;
        if (v === '') { return; }
        var cells = document.querySelectorAll('select[data-hr-row="' + row + '"]');
        for (var j = 0; j < cells.length; j++) { cells[j].value = v; }
        this.value = '';
      });
    }

    /* An employee row is a link; the buttons inside it are not. */
    var rows = document.querySelectorAll('tr[data-hr-href]');
    for (var k = 0; k < rows.length; k++) {
      rows[k].addEventListener('click', function (ev) {
        if (ev.target.closest && ev.target.closest('a,button,input,select,form,label')) { return; }
        window.location = this.getAttribute('data-hr-href');
      });
      rows[k].style.cursor = 'pointer';
    }

    /* Confirm anything that ends a career or a card. */
    var danger = document.querySelectorAll('[data-hr-confirm]');
    for (var m = 0; m < danger.length; m++) {
      danger[m].addEventListener('click', function (ev) { if (!window.confirm(this.getAttribute('data-hr-confirm'))) { ev.preventDefault(); } });
    }

    /* Day counter on the leave form, so nobody guesses. */
    var lf = document.getElementById('hr-leave-from'), lt = document.getElementById('hr-leave-to'), out = document.getElementById('hr-leave-days');
    function days() {
      if (!lf || !lt || !out || !lf.value || !lt.value) { return; }
      var a = new Date(lf.value), b = new Date(lt.value);
      if (isNaN(a) || isNaN(b) || b < a) { out.textContent = ''; return; }
      var n = 0;
      for (var d = new Date(a); d <= b; d.setDate(d.getDate() + 1)) { if (d.getDay() !== 0) { n++; } }
      out.textContent = n + ' working day(s), ' + (Math.round((b - a) / 86400000) + 1) + ' calendar';
    }
    if (lf) { lf.addEventListener('change', days); }
    if (lt) { lt.addEventListener('change', days); }
  });
})();
