/* Pulse Payroll — small back-office conveniences. Nothing here changes a number; the server does all the arithmetic. */
$(document).ready(function () {
    // picking a bank fills its NIBSS code, so nobody types a payment file's institution code by hand
    $(document).on('change', '#pr-bank-pick', function () {
        var code = $(this).find('option:selected').data('code');
        if (code) { $('#pr-bank-code').val(code); }
    });

    // the tab a screen opens on survives a save, so approving a run does not throw you back to the first tab
    var hash = window.location.hash;
    if (hash && $('.nav-tabs a[href="' + hash + '"]').length) { $('.nav-tabs a[href="' + hash + '"]').tab('show'); }
    $(document).on('shown.bs.tab', '.pulse-pr .nav-tabs a[data-toggle="tab"]', function (e) {
        if (window.history && window.history.replaceState) { window.history.replaceState(null, '', e.target.hash); }
    });

    // a destructive action always asks, even where the markup forgot
    $(document).on('click', '.pulse-pr button[name="cancelRun"], .pulse-pr button[name="voidFile"]', function () {
        return $(this).attr('onclick') ? true : window.confirm('Are you sure?');
    });

    // running total under the casual pay grid, so a supervisor sees the cash they need before they approve
    function casualTotal() {
        var rows = $('.pulse-pr input[name^="u["]');
        if (!rows.length) { return; }
        var total = 0;
        rows.each(function () {
            var id = $(this).attr('name').replace('u[', '').replace(']', '');
            var rate = parseFloat($('input[name="r[' + id + ']"]').val()) || 0;
            total += (parseFloat($(this).val()) || 0) * rate;
        });
        if (!$('#pr-casual-total').length) { $('.pulse-pr button[name="bulkUnits"]').after(' <span id="pr-casual-total" class="text-muted"></span>'); }
        $('#pr-casual-total').text('Gross for the week: ' + total.toFixed(2));
    }
    $(document).on('input', '.pulse-pr input[name^="u["], .pulse-pr input[name^="r["]', casualTotal);
    casualTotal();
});
