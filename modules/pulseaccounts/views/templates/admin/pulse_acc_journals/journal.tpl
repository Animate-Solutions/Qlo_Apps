<div class="pulse-acc">
{if !$j}<div class="alert alert-danger">That journal does not exist. <a href="{$self_url}">Back to journals</a></div>{else}
<div class="panel"><h3><i class="icon-list-alt"></i> {$j.journal_no} — {$j.memo|escape}
<span class="badge" style="background:{if $j.status == 'posted'}#27ae60{elseif $j.status == 'draft'}#f39c12{else}#7f8c8d{/if}">{$j.status}</span></h3>
<table class="table table-condensed" style="max-width:760px"><tbody>
<tr><th>Business date</th><td>{$j.business_date}</td><th>Period</th><td>{$j.period}</td></tr>
<tr><th>Type / source</th><td>{$j.type} / {$j.source}</td><th>Source document</th><td>{$j.source_ref|escape|default:'—'}</td></tr>
<tr><th>Reference</th><td>{$j.reference|escape}</td><th>Entered by</th><td>{$j.who|escape} on {$j.date_add}</td></tr>
{if $j.reverses}<tr><th>Reverses</th><td colspan="3">journal #{$j.reverses}</td></tr>{/if}
{if $reversal}<tr class="warning"><th>Reversed by</th><td colspan="3"><a href="{$self_url}&amp;id_journal={$reversal.id_pulse_acc_journal}">{$reversal.journal_no}</a> — {$j.reverse_reason|escape}</td></tr>{/if}
</tbody></table>

<table class="table table-condensed"><thead><tr><th>#</th><th>Account</th><th>Name</th><th>Memo</th><th>Cost centre</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
{foreach $j.lines as $l}<tr><td>{$l.line_no}</td><td class="acc-code">{$l.account_code}</td><td>{$l.account_name|escape}</td><td>{$l.memo|escape}</td><td>{$l.cost_centre}</td>
<td class="num">{if $l.debit > 0}{displayPrice price=$l.debit}{/if}</td><td class="num">{if $l.credit > 0}{displayPrice price=$l.credit}{/if}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="5">Totals</td><td class="num">{displayPrice price=$j.total_debit}</td><td class="num">{displayPrice price=$j.total_credit}</td></tr>
</tbody></table>

{if $src && $src.row}<h4>Source document — {$src.entity}</h4>
<table class="table table-condensed" style="max-width:760px"><tbody>
{foreach $src.row as $k => $v}{if $v !== null && $v !== ''}<tr><th style="width:220px">{$k}</th><td>{$v|escape|truncate:200}</td></tr>{/if}{/foreach}
</tbody></table>{/if}

<div class="noprint">
{if $j.status == 'draft'}<form method="post" class="inline"><input type="hidden" name="id_journal_s" value="{$j.id_pulse_acc_journal}">
<button name="postDraft" class="btn btn-primary">Post this draft</button>
<button name="deleteDraft" class="btn btn-link" onclick="return confirm('Delete this draft?')">Delete draft</button></form>{/if}
{if $j.status == 'posted'}<form method="post" class="form-inline"><input type="hidden" name="id_journal_s" value="{$j.id_pulse_acc_journal}">
<input name="reason" class="form-control" placeholder="Reason for the reversal" required>
<input type="date" name="reverse_date" class="form-control" value="{$j.business_date}" title="Reverse into this date — use an open period if the original month is closed">
<button name="reverseJournal" class="btn btn-warning" onclick="return confirm('Post a contra journal reversing this one?')">Reverse</button></form>
<p class="help-block">A posted journal is never edited or deleted. Reversing writes an equal and opposite entry, so the audit trail keeps both.</p>{/if}
<a class="btn btn-default" href="{$self_url}">Back to journals</a>
</div>
</div>
{/if}</div>
