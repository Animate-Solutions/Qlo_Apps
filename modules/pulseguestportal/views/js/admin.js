/* Pulse Guest Portal back office: small helpers only — the screens are plain server-rendered forms so they
   keep working on the desk PC at 2 a.m. with a browser nobody has updated since 2019. */
(function () {
  function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function () {
    /* confirm anything destructive */
    [].slice.call(document.querySelectorAll('[data-gp-confirm]')).forEach(function (b) {
      b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-gp-confirm'))) { e.preventDefault(); } });
    });
    /* live colour preview on the settings screen */
    [].slice.call(document.querySelectorAll('.gp-colour')).forEach(function (i) {
      var sw = document.createElement('span'); sw.className = 'gp-swatch'; sw.style.background = i.value; i.parentNode.appendChild(sw);
      i.addEventListener('input', function () { sw.style.background = i.value; });
    });
    /* the messages inbox scrolls to the newest line */
    var chat = document.querySelector('.gp-chat'); if (chat) { chat.scrollTop = chat.scrollHeight; }
    /* devices screen: refresh the online column every 60s without losing the page you are on */
    var board = document.getElementById('gp-device-board');
    if (board && board.getAttribute('data-refresh') === '1') { setTimeout(function () { window.location.reload(); }, 60000); }
  });
})();
