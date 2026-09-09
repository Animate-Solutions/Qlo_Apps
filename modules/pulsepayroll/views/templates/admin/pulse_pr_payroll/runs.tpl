<div class="pulse-pr">
<div class="panel"><h3><i class="icon-money"></i> Payroll — {$dash.period} <small class="text-muted">{$dash.pack}</small>
{if !$dash.pack_verified}<span class="badge badge-warning">pack not verified</span>{/if}</h3>
{if $dash.warnings}<div class="alert alert-warning"><ul class="list-unstyled">{foreach $dash.warnings as $w}<li>&bull; {$w}</li>{/foreach}</ul></div>{/if}
<div class="row pr-tiles">
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{$dash.headcount.active|intval}</span><span class="pr-l">active staff</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{$dash.headcount.probation|intval}</span><span class="pr-l">on probation</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{$dash.headcount.casuals|intval}</span><span class="pr-l">casual / service</span></div></div>
  <div class="col-md-2"><div class="pr-tile {if $dash.loans_out > 0}warning{/if}"><span class="pr-n">{displayPrice price=$dash.loans_out}</span><span class="pr-l">loans outstanding</span></div></div>
  <div class="col-md-2"><div class="pr-tile {if $dash.arrears_out > 0}danger{/if}"><span class="pr-n">{displayPrice price=$dash.arrears_out}</span><span class="pr-l">arrears</span></div></div>
  <div class="col-md-2"><div class="pr-tile"><span class="pr-n">{if $dash.last_run}{displayPrice price=$dash.last_run.total_net}{else}&mdash;{/if}</span><span class="pr-l">last net payroll{if $dash.last_run} ({$dash.last_run.period}){/if}</span></div></div>
</div>
<div class="pr-env">
  Integrations: <span class="{if $dash.hr}on{else}off{/if}">Pulse HR</span>
  <span class="{if $dash.ta}on{else}off{/if}">Pulse Time</span>
  <span class="{if $dash.acc}on{else}off{/if}">Pulse Accounts</span>
  <span class="{if $dash.fd}on{else}off{/if}">Front Desk</span>
  {if !$dash.hr}<em class="text-muted">&mdash; Pulse HR is not installed, so the payroll roster on the Employees screen is the master record.</em>{/if}
  {if !$dash.acc}<em class="text-muted">&mdash; Pulse Accounts is not installed, so approved runs complete but post nothing to the general ledger.</em>{/if}
</div>
{if $dash.no_structure}<div class="alert alert-danger">{$dash.no_structure} active staff have no contractual package on their record. They will be paid nothing until a pay rate is set.</div>{/if}
{if $dash.remittances_overdue}<div class="alert alert-danger"><strong>Statutory remittances overdue:</strong>
{foreach $dash.remittances_overdue as $r}{$r.scheme|upper} {$r.period} &mdash; {displayPrice price=$r.amount_due} due {$r.due_date} to {$r.authority}. {/foreach}</div>{/if}
</div>

<div class="panel">
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#pr-runs">Runs</a></li><li><a data-toggle="tab" href="#pr-new">New run</a></li><li><a data-toggle="tab" href="#pr-remit">Statutory remittances</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="pr-runs">
<table class="table table-condensed"><thead><tr><th>Run</th><th>Period</th><th>Type</th><th>Pay date</th><th>Staff</th><th>Gross</th><th>PAYE</th><th>Deductions</th><th>Net</th><th>Employer cost</th><th>Status</th><th>Approved by</th><th></th></tr></thead><tbody>
{foreach $runs as $r}<tr class="{if $r.status=='posted'}success{elseif $r.status=='cancelled'}text-muted{elseif $r.errors}danger{/if}">
<td><a href="{$self_url}&id_run={$r.id_pulse_pr_run}">{$r.run_no}</a></td><td>{$r.period}</td><td>{$r.run_type|replace:'_':' '}</td><td>{$r.pay_date}</td>
<td>{$r.headcount}</td><td>{displayPrice price=$r.total_gross}</td><td>{displayPrice price=$r.total_paye}</td>
<td>{displayPrice price=$r.total_gross-$r.total_net}</td><td><strong>{displayPrice price=$r.total_net}</strong></td>
<td>{displayPrice price=$r.total_gross+$r.total_employer_cost}</td>
<td><span class="pr-status pr-{$r.status}">{$r.status}</span></td><td>{$r.approver}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&id_run={$r.id_pulse_pr_run}">Open</a></td></tr>
{foreachelse}<tr><td colspan="13"><em>No payroll runs yet. Create the first one on the next tab.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pr-new">
<form method="post" class="form-horizontal">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Period</label><div class="col-sm-6"><input name="period" class="form-control" value="{$period}" placeholder="2026-08"><span class="help-block">Month being paid, as YYYY-MM.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Run type</label><div class="col-sm-6"><select name="run_type" class="form-control">
<option value="regular">Regular monthly run</option><option value="supplementary">Supplementary (a correction or a missed payment)</option>
<option value="bonus">Bonus run</option><option value="final_settlement">Final settlement (leavers only)</option></select>
<span class="help-block">A period holds one regular run; anything after it must be supplementary.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Department</label><div class="col-sm-6"><select name="department" class="form-control"><option value="">Whole property</option>{foreach $departments as $d}<option value="{$d.department}">{$d.department} ({$d.n})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Country pack</label><div class="col-sm-6"><select name="country" class="form-control">{foreach $countries as $c}<option value="{$c.code}">{$c.name}{if !$c.verified} — unverified{/if}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay date</label><div class="col-sm-6"><input type="date" name="pay_date" class="form-control"><span class="help-block">Leave blank to use the configured pay day.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-6"><input name="note" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-6"><button name="createRun" class="btn btn-primary btn-lg">Create run</button></div></div>
</div><div class="col-md-6"><div class="alert alert-info">
<strong>What happens next.</strong> Creating a run does nothing but open it. You then <em>calculate</em> it — which rebuilds every payslip from scratch and can be repeated as often as you like — check the variance report, and only then <em>approve</em>. Nothing is emailed, no payment file is written and nothing reaches the general ledger before approval.
</div></div></div>
</form>
</div>

<div class="tab-pane" id="pr-remit">
<table class="table table-condensed"><thead><tr><th>Scheme</th><th>Period</th><th>Authority</th><th>Due</th><th>Amount</th><th>Paid</th><th>Status</th><th>Reference</th></tr></thead><tbody>
{foreach $dash.remittances_due as $r}<tr class="{if $r.due_date < $smarty.now|date_format:'%Y-%m-%d'}danger{/if}">
<td>{$r.scheme|upper}</td><td>{$r.period}</td><td>{$r.authority}</td><td>{$r.due_date}</td>
<td>{displayPrice price=$r.amount_due}</td><td>{displayPrice price=$r.amount_paid}</td><td>{$r.status}</td><td>{$r.reference}</td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing outstanding.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">Remittances are raised automatically the moment a run is approved, with the due date each authority actually works to. Record a payment on the Statutory screen.</p>
</div>

</div></div></div>
