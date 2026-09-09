/* Pulse Accounts — manual journal helper: keep the debit/credit totals live and block an unbalanced post. */
$(document).ready(function () {
    function money(v) { v = parseFloat(String(v).replace(/[^0-9.\-]/g, '')); return isNaN(v) ? 0 : v; }
    function recalc() {
        var d = 0, c = 0;
        $('#acc-journal-lines input.jl-debit').each(function () { d += money($(this).val()); });
        $('#acc-journal-lines input.jl-credit').each(function () { c += money($(this).val()); });
        var diff = Math.round((d - c) * 100) / 100;
        $('#jl-total-debit').text(d.toFixed(2));
        $('#jl-total-credit').text(c.toFixed(2));
        $('#jl-diff').text(diff.toFixed(2)).toggleClass('neg', Math.abs(diff) > 0.009);
        $('#jl-submit').prop('disabled', Math.abs(diff) > 0.009 || (d === 0 && c === 0));
    }
    $(document).on('input change', '#acc-journal-lines input.jl-debit, #acc-journal-lines input.jl-credit', function () {
        if (money($(this).val()) !== 0) { $(this).closest('tr').find($(this).hasClass('jl-debit') ? 'input.jl-credit' : 'input.jl-debit').val(''); }
        recalc();
    });
    $('#jl-add-row').on('click', function (e) {
        e.preventDefault();
        var $t = $('#acc-journal-lines tbody'), $r = $t.find('tr:last').clone();
        $r.find('input').val(''); $t.append($r); recalc();
    });
    if ($('#acc-journal-lines').length) { recalc(); }

    /* Allocation screen: never let the accountant allocate more than the receipt holds. */
    $(document).on('input', 'input.alloc-amount', function () {
        var cap = money($(this).data('max')), total = 0;
        $('input.alloc-amount').each(function () { total += money($(this).val()); });
        var left = Math.round((money($('#alloc-available').data('amount')) - total) * 100) / 100;
        $('#alloc-left').text(left.toFixed(2)).toggleClass('neg', left < -0.009);
        if (money($(this).val()) > cap) { $(this).val(cap.toFixed(2)); }
    });

    /* Tick-all helpers on the payment run and invoicing screens. */
    $(document).on('change', 'input.acc-check-all', function () {
        $($(this).data('target')).find('input[type=checkbox]').prop('checked', $(this).is(':checked'));
    });
});
