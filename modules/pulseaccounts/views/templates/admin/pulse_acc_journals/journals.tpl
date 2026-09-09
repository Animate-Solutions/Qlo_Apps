<div class="pulse-acc"><div class="panel"><h3><i class="icon-list-alt"></i> Journals</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#j-list">Browse</a></li><li><a data-toggle="tab" href="#j-new">Manual journal</a></li><li><a data-toggle="tab" href="#j-payroll">Payroll journal</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="j-list">
<form method="get" class="form-inline noprint" style="margin-bottom:8px">
<input type="hidden" name="controller" value="AdminPulseAccJournals"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<input type="date" name="from" value="{$f.from}" class="form-control"> <input type="date" name="to" value="{$f.to}" class="form-control">
<select name="source" class="form-control"><option value="">every source</option>{foreach $sources as $s}<option value="{$s}" {if $f.source == $s}selected{/if}>{$s}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">any status</option>{foreach ['posted','draft','reversed','void'] as $s}<option value="{$s}" {if $f.status == $s}selected{/if}>{$s}</option>{/foreach}</select>
<input name="q" value="{$f.q|escape}" class="form-control" placeholder="number, memo, reference">
<button class="btn btn-default">Search</button> <a class="btn btn-default" href="{$self_url}&amp;from={$f.from}&amp;to={$f.to}&amp;source={$f.source|escape:'url'}&amp;status={$f.status|escape:'url'}&amp;q={$f.q|escape:'url'}&amp;export=1">Export CSV</a>
</form>
<table class="table table-condensed"><thead><tr><th>Journal</th><th>Date</th><th>Period</th><th>Type</th><th>Source</th><th>Reference</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th><th>Status</th><th>By</th></tr></thead><tbody>
{foreach $journals as $j}<tr class="{if $j.status == 'draft'}warning{elseif $j.status == 'reversed'}muted{/if}">
<td><a href="{$self_url}&amp;id_journal={$j.id_pulse_acc_journal}">{$j.journal_no}</a></td><td>{$j.business_date}</td><td>{$j.period}</td><td>{$j.type}</td>
<td>{$j.source}{if $j.source_ref}<br><small class="muted">{$j.source_ref|escape}</small>{/if}</td><td>{$j.reference|escape}</td><td>{$j.memo|escape}</td>
<td class="num">{displayPrice price=$j.total_debit}</td><td class="num">{displayPrice price=$j.total_credit}</td><td>{$j.status}</td><td class="muted">{$j.who|escape}</td></tr>
{foreachelse}<tr><td colspan="11"><em>No journals in that range</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="j-new">
<form method="post" class="form-horizontal">
<div class="row"><div class="col-md-5">
<div class="form-group"><label class="col-sm-4 control-label">Date</label><div class="col-sm-8"><input type="date" name="business_date" value="{$business_date}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Type</label><div class="col-sm-8"><select name="type" class="form-control"><option value="general">General</option><option value="adjustment">Adjustment</option><option value="receipt">Receipt</option><option value="payment">Payment</option><option value="fx">Foreign exchange</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Reference</label><div class="col-sm-8"><input name="reference" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Memo</label><div class="col-sm-8"><input name="memo" class="form-control" required></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><label><input type="checkbox" name="as_draft" value="1"> Save as a draft (post it later)</label></div></div>
</div>
<div class="col-md-7">
<table class="table table-condensed" id="acc-journal-lines"><thead><tr><th>Account</th><th>Cost centre</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
{section name=r loop=4}<tr>
<td><select name="account[]" class="form-control input-sm"><option value="">—</option>{foreach $accounts as $a}<option value="{$a.code}">{$a.code} {$a.name|escape}</option>{/foreach}</select></td>
<td><select name="cost_centre[]" class="form-control input-sm"><option value="">—</option>{foreach $departments as $d}<option value="{$d.key_value}">{$d.key_value}</option>{/foreach}</select></td>
<td><input name="line_memo[]" class="form-control input-sm"></td>
<td><input name="debit[]" class="form-control input-sm num jl-debit" type="number" step="0.01"></td>
<td><input name="credit[]" class="form-control input-sm num jl-credit" type="number" step="0.01"></td>
</tr>{/section}</tbody>
<tfoot><tr class="pl-total"><td colspan="3"><a href="#" id="jl-add-row" class="btn btn-xs btn-default">Add a line</a></td><td class="num" id="jl-total-debit">0.00</td><td class="num" id="jl-total-credit">0.00</td></tr>
<tr><td colspan="3" class="muted">Out by</td><td colspan="2" class="num" id="jl-diff">0.00</td></tr></tfoot></table>
<button name="saveManual" id="jl-submit" class="btn btn-primary">Post journal</button>
<p class="help-block">A journal that does not balance to the cent is refused. Posted journals are never edited or deleted — reverse them.</p>
</div></div>
</form>
</div>

<div class="tab-pane" id="j-payroll">
<form method="post" class="form-horizontal" style="max-width:700px">
<p class="help-block">Key the month's payroll summary. Gross by department goes to the departmental payroll accounts (USALI keeps payroll with the department it serves); deductions go to their liabilities and the balance to net pay.</p>
<div class="form-group"><label class="col-sm-4 control-label">Period</label><div class="col-sm-4"><input name="payroll_period" class="form-control" value="{$smarty.now|date_format:'%Y-%m'}" pattern="\d{literal}{4}{/literal}-\d{literal}{2}{/literal}"></div></div>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="num">Gross pay</th></tr></thead><tbody>
{foreach ['rooms','housekeeping','fnb','laundry','maintenance','sales','security','admin'] as $dept}
<tr><td>{$dept}</td><td><input name="payroll[{$dept}]" type="number" step="0.01" class="form-control input-sm num"></td></tr>{/foreach}
</tbody></table>
<table class="table table-condensed" style="max-width:420px"><tbody>
<tr><td>PAYE</td><td><input name="paye" type="number" step="0.01" class="form-control input-sm num"></td></tr>
<tr><td>Pension (PenCom)</td><td><input name="pension" type="number" step="0.01" class="form-control input-sm num"></td></tr>
<tr><td>NSITF / ITF / NHF</td><td><input name="nsitf" type="number" step="0.01" class="form-control input-sm num"></td></tr>
<tr><td>Other deductions</td><td><input name="ded_other" type="number" step="0.01" class="form-control input-sm num"></td></tr>
</tbody></table>
<button name="postPayroll" class="btn btn-primary">Post payroll journal</button>
</form>
</div>

</div></div></div>
