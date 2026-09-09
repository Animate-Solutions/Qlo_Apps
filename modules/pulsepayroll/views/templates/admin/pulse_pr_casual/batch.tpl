<div class="pulse-pr"><div class="panel">
<h3><i class="icon-clock-o"></i> {$b.batch_no} &mdash; {$b.week_start} to {$b.week_end}
<span class="pr-status pr-{$b.status} pull-right">{$b.status}</span></h3>
<a class="btn btn-xs btn-default" href="{$self_url}">Back to the weeks</a>
<a class="btn btn-xs btn-default" href="{$self_url}&id_batch={$b.id_pulse_pr_casual_batch}&export=1">Export the pay-out sheet (CSV)</a>
<button type="button" class="btn btn-xs btn-default" onclick="window.print()">Print the sheet</button>

<div class="row pr-tiles">
<div class="col-md-3"><div class="pr-tile"><span class="pr-n">{$b.headcount}</span><span class="pr-l">people</span></div></div>
<div class="col-md-3"><div class="pr-tile"><span class="pr-n">{displayPrice price=$b.total_gross}</span><span class="pr-l">gross</span></div></div>
<div class="col-md-3"><div class="pr-tile"><span class="pr-n">{displayPrice price=$b.total_tax}</span><span class="pr-l">deducted ({$tax_pct}%)</span></div></div>
<div class="col-md-3"><div class="pr-tile"><span class="pr-n">{displayPrice price=$b.total_net}</span><span class="pr-l">net to pay, {$b.pay_method}</span></div></div>
</div>

<form method="post" class="form-inline pr-actions"><input type="hidden" name="id_batch_a" value="{$b.id_pulse_pr_casual_batch}"><input type="hidden" name="id_batch" value="{$b.id_pulse_pr_casual_batch}">
{if $b.status=='draft'}<button name="pullBatch" class="btn btn-default">Pull the casual roster{if $ta} and their approved timesheets{/if}</button>
<button name="approveBatch" class="btn btn-success" onclick="return confirm('Approve this batch?')">Approve</button>{/if}
{if $b.status=='approved'}<button name="payBatch" class="btn btn-success">Mark paid</button>
<select name="split" class="form-control input-sm"><option value="bank">one file per bank</option><option value="single">one combined file</option></select>
<button name="batchBankFile" class="btn btn-default">Generate a payment file</button>{/if}
{if ($b.status=='approved' || $b.status=='paid') && !$b.id_acc_journal}<button name="postBatch" class="btn btn-default">{if $acc}Post to the general ledger{else}Post to GL (Accounts not installed){/if}</button>{/if}
{if $b.id_acc_journal}<span class="label label-success">Posted &mdash; journal #{$b.id_acc_journal}</span>{/if}
</form>
</div>

<div class="panel">
{if $b.status=='draft'}
<form method="post"><input type="hidden" name="id_batch_a" value="{$b.id_pulse_pr_casual_batch}"><input type="hidden" name="id_batch" value="{$b.id_pulse_pr_casual_batch}">
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Role</th><th>Basis</th><th>Days / hours</th><th>Rate</th><th>Gross</th><th>Net</th><th></th></tr></thead><tbody>
{foreach $b.lines as $l}<tr><td>{$l.staff_no}</td><td>{$l.name}</td><td>{$l.department}</td><td>{$l.role}</td><td>{$l.basis|replace:'_':' '}</td>
<td><input name="u[{$l.id_pulse_pr_casual_line}]" type="number" step="0.5" class="form-control input-sm" value="{$l.units|floatval}" style="width:90px"></td>
<td><input name="r[{$l.id_pulse_pr_casual_line}]" type="number" step="0.01" class="form-control input-sm" value="{$l.rate|floatval}" style="width:120px"></td>
<td>{displayPrice price=$l.gross}</td><td><strong>{displayPrice price=$l.net}</strong></td>
<td><button name="deleteLine" value="{$l.id_pulse_pr_casual_line}" class="btn btn-xs btn-link" onclick="return confirm('Remove this line?')">✕</button></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nobody in this week yet. Pull the roster, or add someone below.</em></td></tr>{/foreach}
</tbody></table>
<button name="bulkUnits" class="btn btn-primary">Save the days and rates</button></form>

<h4>Add someone who is not on the roster</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_batch_a" value="{$b.id_pulse_pr_casual_batch}"><input type="hidden" name="id_batch" value="{$b.id_pulse_pr_casual_batch}">
<input name="name" class="form-control input-sm" placeholder="full name" required>
<input name="phone" class="form-control input-sm" placeholder="phone" style="width:130px">
<input name="cdepartment" class="form-control input-sm" placeholder="department" list="cw-depts" style="width:130px">
<datalist id="cw-depts">{foreach $departments as $d}<option value="{$d.department}">{/foreach}</datalist>
<input name="role" class="form-control input-sm" placeholder="role" style="width:130px">
<select name="basis" class="form-control input-sm"><option value="daily">daily</option><option value="hourly">hourly</option><option value="per_shift">per shift</option></select>
<input name="units" type="number" step="0.5" class="form-control input-sm" placeholder="days" style="width:80px">
<input name="rate" type="number" step="0.01" class="form-control input-sm" value="{$day_rate}" style="width:110px">
<input name="bank_code" class="form-control input-sm" placeholder="bank code" style="width:100px">
<input name="account_no" class="form-control input-sm" placeholder="account" style="width:130px">
<button name="saveLine" class="btn btn-primary btn-sm">Add</button></form>
{else}
<table class="table table-condensed pr-print"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Role</th><th>Basis</th><th>Units</th><th>Rate</th><th>Gross</th><th>Deducted</th><th>Net</th><th>Signature</th></tr></thead><tbody>
{foreach $sheet as $l}<tr><td>{$l.staff_no}</td><td>{$l.name}</td><td>{$l.department}</td><td>{$l.role}</td><td>{$l.basis|replace:'_':' '}</td>
<td>{$l.units|floatval}</td><td>{displayPrice price=$l.rate}</td><td>{displayPrice price=$l.gross}</td><td>{displayPrice price=$l.tax+$l.other_deduction}</td>
<td><strong>{displayPrice price=$l.net}</strong></td><td class="pr-sign">&nbsp;</td></tr>{/foreach}
<tr class="active"><td colspan="7"><strong>TOTAL &mdash; {$b.headcount} people</strong></td><td><strong>{displayPrice price=$b.total_gross}</strong></td><td><strong>{displayPrice price=$b.total_gross-$b.total_net}</strong></td><td><strong>{displayPrice price=$b.total_net}</strong></td><td></td></tr>
</tbody></table>
{/if}
{if $files}<h4>Payment files</h4>
<table class="table table-condensed"><thead><tr><th>File</th><th>Bank</th><th>Records</th><th>Control total</th><th>Status</th></tr></thead><tbody>
{foreach $files as $f}<tr><td>{$f.file_no} <small>{$f.filename}</small></td><td>{$f.bank_name}</td><td>{$f.record_count}</td><td><strong>{displayPrice price=$f.control_total}</strong></td><td>{$f.status}</td></tr>{/foreach}
</tbody></table>{/if}
</div></div>
