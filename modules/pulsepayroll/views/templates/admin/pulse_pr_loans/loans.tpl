<div class="pulse-pr"><div class="panel"><h3><i class="icon-credit-card"></i> Loans &amp; advances</h3>
<p class="text-muted">A recovery is always capped at what the payslip can bear. If an instalment would push net pay below the protected floor, only the affordable part is taken and the rest is parked as arrears against the employee &mdash; net pay is never negative and a loan is never quietly forgiven.</p>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#ln-list">Loans ({$loans|count})</a></li><li><a data-toggle="tab" href="#ln-new">New application</a></li><li><a data-toggle="tab" href="#ln-arr">Arrears ({$arrears|count})</a></li><li><a data-toggle="tab" href="#ln-book">Loan book</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="ln-list">
<form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulsePrLoans"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<input name="q" class="form-control input-sm" placeholder="loan number, staff number or name" value="{$filters.q|escape:'html':'UTF-8'}">
<select name="status" class="form-control input-sm"><option value="">Any status</option>{foreach ['applied','approved','disbursed','repaying','settled','rejected','written_off'] as $s}<option value="{$s}" {if $filters.status==$s}selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select>
<button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Loan</th><th>Employee</th><th>Dept</th><th>Type</th><th>Principal</th><th>Interest</th><th>Repayable</th><th>Recovered</th><th>Balance</th><th>Instalment</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $loans as $l}<tr class="{if $l.status=='applied'}warning{elseif $l.status=='settled'}success{/if}">
<td><a href="{$self_url}&id_loan={$l.id_pulse_pr_loan}">{$l.loan_no}</a></td><td>{$l.staff_no} {$l.employee_name}</td><td>{$l.department}</td><td>{$l.type|replace:'_':' '}</td>
<td>{displayPrice price=$l.principal}</td><td>{$l.interest_pct|floatval}%</td><td>{displayPrice price=$l.total_repayable}</td>
<td>{displayPrice price=$l.recovered}</td><td><strong>{displayPrice price=$l.balance}</strong></td><td>{displayPrice price=$l.instalment_amount} &times;{$l.instalments}</td>
<td>{$l.status|replace:'_':' '}</td><td><a class="btn btn-xs btn-default" href="{$self_url}&id_loan={$l.id_pulse_pr_loan}">Open</a></td></tr>
{foreachelse}<tr><td colspan="12"><em>No loans.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ln-new">
<form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Employee</label><div class="col-sm-7"><select name="id_pulse_pr_employee" class="form-control" required>{foreach $employees as $e}<option value="{$e.id_pulse_pr_employee}">{$e.staff_no} — {$e.firstname} {$e.lastname} ({$e.department})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Type</label><div class="col-sm-7"><select name="type" class="form-control"><option value="loan">staff loan</option><option value="salary_advance">salary advance</option><option value="asset">asset purchase</option><option value="other">other</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Purpose</label><div class="col-sm-7"><input name="purpose" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Principal</label><div class="col-sm-7"><input name="principal" type="number" step="0.01" class="form-control" required></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Interest %</label><div class="col-sm-7"><input name="interest_pct" type="number" step="0.001" class="form-control" value="0"><span class="help-block">Flat on the principal. Zero for an interest-free staff loan or a salary advance.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Instalments</label><div class="col-sm-7"><input name="instalments" type="number" class="form-control" value="6"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">First recovery period</label><div class="col-sm-7"><input name="first_period" class="form-control" value="{$smarty.now|date_format:'%Y-%m'}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-7"><input name="note" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-7"><button name="applyLoan" class="btn btn-primary btn-lg">Record the application</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="ln-arr">
<table class="table table-condensed"><thead><tr><th>Raised</th><th>Employee</th><th>Dept</th><th>Description</th><th>Amount</th><th>Recovered</th><th>Balance</th><th></th></tr></thead><tbody>
{foreach $arrears as $a}<tr class="warning"><td>{$a.period_raised}</td><td>{$a.staff_no} {$a.employee_name}</td><td>{$a.department}</td><td>{$a.description}</td>
<td>{displayPrice price=$a.amount}</td><td>{displayPrice price=$a.recovered}</td><td><strong>{displayPrice price=$a.balance}</strong></td>
<td><form method="post" class="form-inline"><input type="hidden" name="id_arrears" value="{$a.id_pulse_pr_arrears}"><input name="reason" class="form-control input-sm" placeholder="reason" style="width:150px">
<button name="waiveArrears" class="btn btn-xs btn-link" onclick="return confirm('Waive this arrears balance?')">Waive</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em>No arrears — every recovery has been taken in full.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ln-book">
<table class="table table-condensed"><thead><tr><th>Loan</th><th>Employee</th><th>Dept</th><th>Type</th><th>Principal</th><th>Interest</th><th>Repayable</th><th>Recovered</th><th>Balance</th><th>Disbursed</th></tr></thead><tbody>
{assign var=tot value=0}
{foreach $book as $b}<tr><td>{$b.loan_no}</td><td>{$b.staff_no} {$b.employee_name}</td><td>{$b.department}</td><td>{$b.type|replace:'_':' '}</td>
<td>{displayPrice price=$b.principal}</td><td>{displayPrice price=$b.interest_amount}</td><td>{displayPrice price=$b.total_repayable}</td>
<td>{displayPrice price=$b.recovered}</td><td><strong>{displayPrice price=$b.balance}</strong></td><td>{$b.date_disbursed}</td></tr>{assign var=tot value=$tot+$b.balance}{/foreach}
<tr class="active"><td colspan="8"><strong>Total outstanding</strong></td><td><strong>{displayPrice price=$tot}</strong></td><td></td></tr>
</tbody></table>
</div>

</div></div></div>
