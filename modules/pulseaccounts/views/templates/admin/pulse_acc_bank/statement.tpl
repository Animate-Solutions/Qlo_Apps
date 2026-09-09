<div class="pulse-acc">
{if !$rec}<div class="alert alert-danger">That statement does not exist. <a href="{$self_url}">Back to banking</a></div>{else}
<div class="panel"><h3><i class="icon-exchange"></i> {$rec.bank_account.name|escape} — {$rec.statement.filename|escape}
<span class="badge" style="background:{if $rec.statement.rows_unmatched}#f39c12{else}#27ae60{/if}">{$rec.statement.status}</span></h3>

<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Ledger balance</span><span class="v">{displayPrice price=$rec.gl_balance}</span><small class="muted">to {$rec.statement.period_to}</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Statement balance</span><span class="v">{if $rec.statement_balance === null}—{else}{displayPrice price=$rec.statement_balance}{/if}</span></div></div>
<div class="col-md-3"><div class="tile {if $rec.difference !== null && ($rec.difference > 0.01 || $rec.difference < -0.01)}warn{else}ok{/if}"><span class="k">Difference</span><span class="v">{if $rec.difference === null}—{else}{displayPrice price=$rec.difference}{/if}</span>
<small class="muted">unpresented {displayPrice price=$rec.unpresented} · not yet on the statement {displayPrice price=$rec.undeposited}</small></div></div>
<div class="col-md-3"><div class="tile {if $rec.statement.rows_unmatched}bad{else}ok{/if}"><span class="k">Rows</span><span class="v">{$rec.statement.rows_matched}/{$rec.statement.rows_total}</span><small class="muted">{$rec.statement.rows_unmatched} unmatched</small></div></div>
</div>

<form method="post" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="id_statement_s" value="{$rec.statement.id_pulse_acc_bank_statement}">
<button name="rematch" class="btn btn-default">Run the matcher again</button>
<a class="btn btn-default" href="{$self_url}&amp;id_statement={$rec.statement.id_pulse_acc_bank_statement}">all rows</a>
<a class="btn btn-default" href="{$self_url}&amp;id_statement={$rec.statement.id_pulse_acc_bank_statement}&amp;match_state=unmatched">unmatched only</a>
<button name="deleteStatement" class="btn btn-link" onclick="return confirm('Delete this statement and its lines? Journals created from it stay.')">Delete statement</button>
</form>

<table class="table table-condensed"><thead><tr><th>Date</th><th>Narration</th><th>Reference</th><th class="num">In</th><th class="num">Out</th><th class="num">Balance</th><th>Match</th><th class="noprint">Action</th></tr></thead><tbody>
{foreach $lines as $l}<tr class="{if $l.match_state == 'unmatched'}warning{elseif $l.match_state == 'ignored'}muted{/if}">
<td>{$l.txn_date}</td><td>{$l.description|escape|truncate:60}</td><td>{$l.reference|escape}</td>
<td class="num">{if $l.money_in > 0}{displayPrice price=$l.money_in}{/if}</td><td class="num">{if $l.money_out > 0}{displayPrice price=$l.money_out}{/if}</td>
<td class="num">{if $l.balance !== null}{displayPrice price=$l.balance}{/if}</td>
<td>{if $l.journal_no}<strong>{$l.journal_no}</strong> <small class="muted">{$l.match_state}</small><br><small>{$l.journal_memo|escape|truncate:50}</small>
{else}<span class="muted">{$l.match_state}</span>{if $l.match_note}<br><small class="muted">{$l.match_note|escape}</small>{/if}{/if}</td>
<td class="noprint">
{if $l.match_state == 'unmatched'}
<form method="post" class="form-inline"><input type="hidden" name="id_line" value="{$l.id_pulse_acc_bank_line}">
<input name="id_journal" type="number" class="form-control input-sm" style="width:80px" placeholder="jrnl id">
<button name="matchLine" class="btn btn-xs btn-default">Match</button>
<select name="account_code" class="input-sm">{foreach $accounts as $a}<option value="{$a.code}">{$a.code} {$a.name|escape|truncate:26}</option>{/foreach}</select>
<input name="memo" class="form-control input-sm" style="width:120px" placeholder="memo">
<button name="createFromLine" class="btn btn-xs btn-primary">Create journal</button>
<button name="ignoreLine" class="btn btn-xs btn-link" onclick="this.form.note.value='Ignored by the accountant';return true;">Ignore</button>
<input type="hidden" name="note" value="">
</form>
{else}<form method="post" class="inline"><input type="hidden" name="id_line" value="{$l.id_pulse_acc_bank_line}"><button name="unmatchLine" class="btn btn-xs btn-link">unmatch</button></form>{/if}
</td></tr>
{foreachelse}<tr><td colspan="8"><em>No lines</em></td></tr>{/foreach}
</tbody></table>

{if $rec.outstanding}<h4>In our ledger but not on this statement</h4>
<p class="help-block">Cheques not yet presented, transfers the bank has not shown, and takings banked after the statement was cut. These are the reconciling items between the two balances above.</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Journal</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
{foreach $rec.outstanding as $o}<tr><td>{$o.business_date}</td><td>{$o.journal_no}</td><td>{$o.memo|escape|truncate:70}</td>
<td class="num">{if $o.debit > 0}{displayPrice price=$o.debit}{/if}</td><td class="num">{if $o.credit > 0}{displayPrice price=$o.credit}{/if}</td></tr>{/foreach}
</tbody></table>{/if}

<a class="btn btn-default noprint" href="{$self_url}">Back to banking</a>
</div>
{/if}</div>
