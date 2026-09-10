/* Pulse Payments back office helpers: prefill a payment link from the chosen folio, keep amounts honest. */
$(document).ready(function () {
    $('#pp-folio').on('change', function () {
        var o = this.options[this.selectedIndex];
        if (!o || !o.value) { return; }
        $('#pp-booking').val($(o).data('booking') || '');
        $('#pp-guest').val($(o).data('guest') || '');
        var b = parseFloat($(o).data('balance') || 0);
        if (b > 0) { $('#pp-amount').val(b.toFixed(2)); }
    });
    $('.pp-copy').on('focus', function () { this.select(); });
    $('form').on('submit', function () {
        var a = $(this).find('input[name="amount"]');
        if (a.length && a.val() !== '' && parseFloat(a.val()) <= 0) { alert('Amount must be greater than zero.'); return false; }
        return true;
    });
});
