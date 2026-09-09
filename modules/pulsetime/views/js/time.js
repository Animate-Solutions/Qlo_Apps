/* Pulse Time & Attendance — small back-office helpers. Everything works without JavaScript; this only
   removes clicks. The Live Board refreshes itself because at 06:00 nobody wants to press F5. */
$(document).ready(function () {

    /* Live Board: refresh the "who is in" table in place every 60 s. */
    var $board = $('#ta-b-board');
    if ($board.length && typeof taBoardUrl !== 'undefined') {
        window.setInterval(function () {
            $.post(taBoardUrl, { ajax: 1, action: 'board', department: $('select[name=department]').val() || '' }, null, 'json')
                .done(function (r) {
                    if (!r || !r.ok) { return; }
                    $('.ta-tile.ta-in .n').text(r.counters.on_site);
                })
                .fail(function () { /* a flaky link must never break the screen */ });
        }, 60000);
    }

    /* Devices: choosing an adapter fills in the port, protocol and mode it normally uses. */
    var $adapter = $('#ta-adapter');
    if ($adapter.length && typeof taAdapterDefaults !== 'undefined') {
        $adapter.on('change', function () {
            var d = taAdapterDefaults[$(this).val()];
            if (!d) { return; }
            var $f = $(this).closest('form');
            $f.find('[name=port]').val(d.port);
            $f.find('[name=mode]').val(d.mode);
            $f.find('[name=protocol]').val(d.protocol);
            $f.find('[name=brand]').val(d.brand);
            if (!$f.find('[name=endpoint]').val()) { $f.find('[name=endpoint]').val(d.endpoint); }
        });
    }

    /* Exceptions: show only the correction fields the chosen fix actually uses. */
    var $how = $('#ta-how');
    if ($how.length) {
        var sync = function () {
            var v = $how.val();
            var $form = $how.closest('form');
            $form.find('[name=adj_date], [name=adj_time], [name=adj_direction]').closest('.form-group').toggle(v === 'add_punch');
            $form.find('[name=adj_minutes]').closest('.form-group').toggle(v === 'set_minutes' || v === 'add_overtime');
            $form.find('[name=adj_id_punch]').closest('.form-group').toggle(v === 'ignore_punch');
        };
        $how.on('change', sync);
        sync();
    }

    /* Anything destructive asks once. */
    $('button[name=clearLog]').on('click', function () {
        return window.confirm('This erases the attendance log on the device itself. Only do it after a successful pull. Continue?');
    });
});
