/* Pulse Channel — ARI grid helpers: mark edited cells, fill a row across, warn before leaving unsaved work. */
$(document).ready(function () {
    var dirty = false;
    $('.pulse-ch').on('change keyup', '.ch-rate, .ch-los', function () { $(this).closest('.ch-cell').addClass('ch-dirty'); dirty = true; });
    $('.pulse-ch').on('change', '.ch-badges input[type=checkbox]', function () { $(this).closest('.ch-cell').addClass('ch-dirty'); dirty = true; });
    /* shift-click a rate box to copy it into every later cell of the same row — the fastest way to price a season */
    $('.pulse-ch').on('click', '.ch-rate', function (e) {
        if (!e.shiftKey) { return; }
        var v = $(this).val(), row = $(this).closest('tr'), seen = false;
        row.find('.ch-rate').each(function () {
            if (this === e.target) { seen = true; return; }
            if (seen) { $(this).val(v).closest('.ch-cell').addClass('ch-dirty'); }
        });
        dirty = true;
    });
    $('.pulse-ch form').on('submit', function () { dirty = false; });
    $(window).on('beforeunload', function () { if (dirty) { return 'There are unsaved ARI changes on this screen.'; } });
});
