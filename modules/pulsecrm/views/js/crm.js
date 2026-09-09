/* Pulse CRM back-office helpers: the segment rule builder, the preference picker and a guard on long sends. */
$(document).ready(function () {
    // Preference picker: only offer the codes that belong to the chosen category.
    var $cat = $('#crm-pref-cat'), $code = $('#crm-pref-code');
    if ($cat.length && $code.length) {
        var all = $code.find('option').clone();
        var filter = function () {
            var c = $cat.val();
            $code.empty().append('<option value="">—</option>');
            all.each(function () { if ($(this).data('cat') === c) { $code.append($(this).clone()); } });
        };
        $cat.on('change', filter);
        filter();
    }

    // Segment rule builder: clone the last row, wipe it, append.
    $('#crm-add-rule').on('click', function () {
        var $last = $('#crm-rules tr.crm-rule:last');
        var $row = $last.clone(true);
        $row.find('input').val('');
        $row.find('select').each(function () { this.selectedIndex = 0; });
        $('#crm-rules tbody').append($row);
    });
    $(document).on('click', '.crm-rule-del', function () {
        if ($('#crm-rules tr.crm-rule').length > 1) { $(this).closest('tr').remove(); }
        else { $(this).closest('tr').find('input').val(''); }
    });

    // A campaign send can take a while on a slow link — say so once, and stop a double submit.
    $('button[name="sendCampaign"], button[name="queueCampaign"]').on('click', function () {
        var $b = $(this);
        if ($b.data('gone')) { return false; }
        $b.data('gone', true).addClass('disabled').text($b.text() + '…');
        setTimeout(function () { $b.removeData('gone').removeClass('disabled'); }, 30000);
    });

    // Closing a recovery case without a root cause is the commonest mistake; catch it in the browser too.
    $('button[name="updateCase"]').on('click', function () {
        var $f = $(this).closest('form');
        if ($f.find('select[name="status"]').val() === 'closed'
            && (!$.trim($f.find('input[name="root_cause"]').val()) || !$.trim($f.find('textarea[name="closing_note"]').val()))) {
            alert('A case cannot be closed without a root cause and a closing note.');
            return false;
        }
    });
});
