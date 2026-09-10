<div class="pulse-pr">
<div class="panel"><h3><i class="icon-money"></i> {$run.run_no} &mdash; {$run.period} <small>{$run.run_type|replace:'_':' '}{if $run.department}, {$run.department}{/if}</small>
<span class="pr-status pr-{$run.status} pull-right">{$run.status}</span></h3>
<div class="row pr-tiles">
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{$run.headcount}</span><span class="pr-l">payslips</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$run.total_gross}</span><span class="pr-l">gross</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$run.total_paye}</span><span class="pr-l">PAYE</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$run.total_pension_ee+$run.total_pension_er}</span><span class="pr-l">pension (both sides)</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$run.total_net}</span><span class="pr-l">net to pay</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$run.total_gross+$run.total_employer_cost}</span><span class="pr-l">total employer cost</span></div></div>
</div>
<p class="text-muted pr-hash">Result hash <code>{$run.result_hash}</code> &mdash; a recalculation of an unchanged period reproduces exactly this. Calculated in {$run.calc_ms}ms{if $run.date_calculated} on {$run.date_calculated}{/if}.</p>

{if $errors_list}<div class="alert alert-danger"><strong>{$errors_list|count} employee(s) could not be calculated and are not in this run:</strong>
<ul>{foreach $errors_list as $e}<li>{$e}</li>{/foreach}</ul></div>{/if}
{if !$verify.ok}<div class="alert alert-danger"><strong>This run does not reconcile and cannot be approved:</strong><ul>{foreach $verify.problems as $p}<li>{$p}</li>{/foreach}</ul></div>
{elseif $run.status=='calculated'}<div class="alert alert-success">Reconciled: the payslips add up to the run header, gross less deductions equals net on every line, and nobody has negative net pay.</div>{/if}

<form method="post" class="form-inline pr-actions"><input type="hidden" name="id_run_a" value="{$run.id_pulse_pr_run}">
{if $run.status=='draft' || $run.status=='calculated'}<button name="calcRun" class="btn btn-primary">{if $run.status=='draft'}Calculate{else}Recalculate{/if}</button>{/if}
{if $run.status=='calculated' && $verify.ok && !$run.errors}<button name="approveRun" class="btn btn-success" onclick="return confirm('Approve this run? The figures are frozen after this.')">Approve</button>{/if}
{if $run.status=='approved'}
  <input name="reason" class="form-control input-sm" placeholder="reason to reopen">
  <button name="reopenRun" class="btn btn-warning btn-sm">Reopen</button>
  <button name="payRun" class="btn btn-success" onclick="return confirm('Mark this run as paid?')">Mark paid</button>
{/if}
{if ($run.status=='approved' || $run.status=='paid') && !$run.id_acc_journal}<button name="postRun" class="btn btn-default">{if $acc}Post to the general ledger{else}Post to GL (Accounts not installed){/if}</button>{/if}
{if $run.id_acc_journal}<span class="label label-success">Posted &mdash; journal #{$run.id_acc_journal}</span>{/if}
{if $run.status=='draft' || $run.status=='calculated'}<button name="cancelRun" class="btn btn-link btn-sm" onclick="return confirm('Cancel this run?')">Cancel run</button>{/if}
</form>
</div>

<div class="panel">
<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#pr-slips">Payslips ({$payslips|count})</a></li>
<li><a data-toggle="tab" href="#pr-var">Variance</a></li>
<li><a data-toggle="tab" href="#pr-dept">By department</a></li>
<li><a data-toggle="tab" href="#pr-bank">Payment</a></li>
<li><a data-toggle="tab" href="#pr-gl">General ledger</a></li>
<li><a data-toggle="tab" href="#pr-audit">Audit trail</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="pr-slips">
{if $run.status=='approved' || $run.status=='paid' || $run.status=='posted'}
<form method="post" class="form-inline pull-right"><input type="hidden" name="id_run_a" value="{$run.id_pulse_pr_run}"><button name="emailRun" class="btn btn-default btn-sm">Email the outstanding payslips</button></form>
{/if}
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Days</th><th>Basic</th><th>Gross</th><th>PAYE</th><th>Pension</th><th>NHF</th><th>Loans</th><th>Deductions</th><th>Net</th><th>Emailed</th><th></th></tr></thead><tbody>
{foreach $payslips as $p}<tr class="{if $p.net_pay <= 0}danger{elseif $p.arrears_added > 0}warning{/if}">
<td>{$p.staff_no}</td><td><a href="{$self_url}&id_payslip={$p.id_pulse_pr_payslip}">{$p.employee_name}</a></td><td>{$p.department}</td>
<td>{$p.days_paid|floatval}{if $p.proration < 1}<small class="text-muted">/{$p.days_in_period|floatval}</small>{/if}</td>
<td>{displayPrice price=$p.basic}</td><td>{displayPrice price=$p.gross}</td><td>{displayPrice price=$p.paye}</td>
<td>{displayPrice price=$p.pension_ee}</td><td>{if $p.nhf > 0}{displayPrice price=$p.nhf}<small class="text-muted" title="consent {$p.nhf_consent_date}">✓</small>{else}<span class="text-muted">—</span>{/if}</td>
<td>{if $p.loan_recovered > 0}{displayPrice price=$p.loan_recovered}{if $p.arrears_added > 0}<br><small class="text-danger">{displayPrice price=$p.arrears_added} to arrears</small>{/if}{else}—{/if}</td>
<td>{displayPrice price=$p.total_deductions}</td><td><strong>{displayPrice price=$p.net_pay}</strong></td>
<td>{if $p.emailed_at}<small>{$p.emailed_at|date_format:"%d/%m %H:%M"}</small>{else}<span class="text-muted">—</span>{/if}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&id_payslip={$p.id_pulse_pr_payslip}">View</a></td></tr>
{foreachelse}<tr><td colspan="14"><em>Nothing calculated yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pr-var">
<p class="text-muted">Anything moving more than {$variance_pct}% against last period is flagged. This is the single most useful control in payroll: check every flag before approving.</p>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Last period gross</th><th>This period gross</th><th>Change</th><th>%</th><th>Flag</th></tr></thead><tbody>
{foreach $variance as $v}<tr class="{if $v.flag=='variance'}warning{elseif $v.flag=='dropped'}danger{elseif $v.flag=='new'}info{/if}">
<td>{$v.staff_no}</td><td>{$v.employee_name}</td><td>{$v.department}</td>
<td>{displayPrice price=$v.prev_gross}</td><td>{displayPrice price=$v.gross}</td>
<td>{displayPrice price=$v.delta}</td><td>{$v.pct}%</td>
<td>{if $v.flag=='new'}new starter{elseif $v.flag=='dropped'}paid last period, not this one{elseif $v.flag=='stopped'}zero this period{elseif $v.flag}<strong>check</strong>{else}<span class="text-muted">—</span>{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No comparison available — there is no prior period.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pr-dept">
<table class="table table-condensed"><thead><tr><th>Department</th><th>Staff</th><th>Gross</th><th>PAYE</th><th>Pension (ee)</th><th>Pension (er)</th><th>NSITF</th><th>ITF</th><th>Deductions</th><th>Net</th><th>Total cost</th></tr></thead><tbody>
{foreach $by_department as $d}<tr><td>{$d.department}</td><td>{$d.headcount}</td><td>{displayPrice price=$d.gross}</td><td>{displayPrice price=$d.paye}</td>
<td>{displayPrice price=$d.pension_ee}</td><td>{displayPrice price=$d.pension_er}</td><td>{displayPrice price=$d.nsitf}</td><td>{displayPrice price=$d.itf}</td>
<td>{displayPrice price=$d.deductions}</td><td>{displayPrice price=$d.net}</td><td><strong>{displayPrice price=$d.total_cost}</strong></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pr-bank">
{if $run.status=='draft' || $run.status=='calculated'}
<div class="alert alert-warning">A payment file can only be written from an approved run. Approve first.</div>
{else}
<form method="post" class="form-inline"><input type="hidden" name="id_run_a" value="{$run.id_pulse_pr_run}">
<select name="split" class="form-control input-sm"><option value="bank">One file per beneficiary bank</option><option value="single">One combined file</option></select>
<select name="template" class="form-control input-sm"><option value="nibss">NIBSS-style bulk upload</option><option value="generic">Generic (my bank's own columns)</option></select>
<button name="bankFile" class="btn btn-primary btn-sm">Generate payment file</button></form>
{/if}
<h4>Where the money goes</h4>
<table class="table table-condensed"><thead><tr><th>Bank</th><th>Code</th><th>Beneficiaries</th><th>Amount</th></tr></thead><tbody>
{foreach $bank.by_bank as $b}<tr><td>{$b.bank_name}</td><td>{$b.bank_code}</td><td>{$b.n}</td><td>{displayPrice price=$b.total}</td></tr>{/foreach}
{if $bank.cash.n > 0}<tr class="warning"><td>Cash / cheque</td><td>—</td><td>{$bank.cash.n}</td><td>{displayPrice price=$bank.cash.total}</td></tr>{/if}
<tr class="active"><td colspan="3"><strong>Total net</strong></td><td><strong>{displayPrice price=$bank.total}</strong></td></tr>
</tbody></table>
{if $cash_rows}<h4>Paid in cash — the cashier's list</h4>
<table class="table table-condensed"><tbody>{foreach $cash_rows as $c}<tr><td>{$c.staff_no}</td><td>{$c.employee_name}</td><td>{$c.department}</td><td>{$c.pay_method}</td><td>{displayPrice price=$c.net_pay}</td></tr>{/foreach}</tbody></table>{/if}
<h4>Files generated</h4>
<table class="table table-condensed"><thead><tr><th>File</th><th>Bank</th><th>Layout</th><th>Records</th><th>Control total</th><th>Checksum</th><th>Status</th><th>By</th><th></th></tr></thead><tbody>
{foreach $bank.files as $f}<tr class="{if $f.status=='void'}text-muted{/if}"><td>{$f.file_no}<br><small>{$f.filename}</small></td><td>{$f.bank_name}</td><td>{$f.template}</td>
<td>{$f.record_count}</td><td><strong>{displayPrice price=$f.control_total}</strong></td><td><small><code>{$f.checksum|truncate:12:""}</code></small></td><td>{$f.status}</td><td>{$f.who}</td>
<td>{if $f.status!='void'}<a class="btn btn-xs btn-primary" href="{$self_url}&id_run={$run.id_pulse_pr_run}&download_file={$f.id_pulse_pr_bank_file}">Download</a>
<form method="post" class="inline"><input type="hidden" name="id_file" value="{$f.id_pulse_pr_bank_file}"><button name="voidFile" class="btn btn-xs btn-link" onclick="return confirm('Void this file? Do this only if it has not been sent to the bank.')">Void</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="9"><em>No payment file has been generated yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pr-gl">
{if !$acc}<div class="alert alert-info">Pulse Accounts is not installed. The run completes normally; it simply posts nothing to a general ledger.</div>{/if}
<p class="text-muted">This is exactly what will be posted. Debits must equal credits before anything reaches the ledger; if they do not, the run refuses to post rather than writing a broken journal.</p>
<table class="table table-condensed"><thead><tr><th>Debit — departmental payroll cost</th><th class="text-right">Amount</th></tr></thead><tbody>
{assign var=dr value=0}
{foreach $gl.departments as $dept => $amt}<tr><td>{$dept}</td><td class="text-right">{displayPrice price=$amt}</td></tr>{assign var=dr value=$dr+$amt}{/foreach}
<tr class="active"><td><strong>Total debits</strong></td><td class="text-right"><strong>{displayPrice price=$dr}</strong></td></tr>
</tbody></table>
<table class="table table-condensed"><thead><tr><th>Credit — liabilities and net pay</th><th class="text-right">Amount</th></tr></thead><tbody>
{assign var=cr value=0}
<tr><td>PAYE payable (2155)</td><td class="text-right">{displayPrice price=$gl.deductions.paye}</td></tr>{assign var=cr value=$cr+$gl.deductions.paye}
<tr><td>Pension payable, both sides (2150)</td><td class="text-right">{displayPrice price=$gl.deductions.pension}</td></tr>{assign var=cr value=$cr+$gl.deductions.pension}
<tr><td>NSITF / ITF / NHF / NHIS payable (2160)</td><td class="text-right">{displayPrice price=$gl.deductions.nsitf}</td></tr>{assign var=cr value=$cr+$gl.deductions.nsitf}
<tr><td>Other deductions — loans, union, cooperative (2130)</td><td class="text-right">{displayPrice price=$gl.deductions.other}</td></tr>{assign var=cr value=$cr+$gl.deductions.other}
<tr><td>Salaries and wages payable (2140)</td><td class="text-right">{displayPrice price=$gl.net}</td></tr>{assign var=cr value=$cr+$gl.net}
<tr class="active"><td><strong>Total credits</strong></td><td class="text-right"><strong>{displayPrice price=$cr}</strong></td></tr>
<tr class="{if $dr-$cr > 0.009 || $cr-$dr > 0.009}danger{else}success{/if}"><td><strong>Difference</strong></td><td class="text-right"><strong>{displayPrice price=$dr-$cr}</strong></td></tr>
</tbody></table>
</div>

<div class="tab-pane" id="pr-audit">
<table class="table table-condensed"><thead><tr><th>When</th><th>Event</th><th>Who</th><th>From</th><th>Detail</th></tr></thead><tbody>
{foreach $audit as $a}<tr><td>{$a.date_add}</td><td>{$a.event}</td><td>{$a.who}</td><td>{$a.ip}</td><td><small>{$a.detail|truncate:180:"…"}</small></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
