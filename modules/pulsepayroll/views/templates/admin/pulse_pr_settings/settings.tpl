<div class="pulse-pr"><div class="panel"><h3><i class="icon-cogs"></i> Payroll settings</h3>
<div class="pr-env">Integrations detected:
<span class="{if $env.hr}on{else}off{/if}">Pulse HR</span><span class="{if $env.ta}on{else}off{/if}">Pulse Time</span>
<span class="{if $env.acc}on{else}off{/if}">Pulse Accounts</span><span class="{if $env.fd}on{else}off{/if}">Front Desk</span>
<span class="{if $env.pos}on{else}off{/if}">Pulse POS</span><span class="{if $env.comms}on{else}off{/if}">Pulse Comms</span>
</div>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#se-main">Settings</a></li><li><a data-toggle="tab" href="#se-banks">Banks &amp; payment files</a></li><li><a data-toggle="tab" href="#se-check">Self-check</a></li><li><a data-toggle="tab" href="#se-cron">Scheduled work</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="se-main">
<form method="post" class="form-horizontal"><div class="row">
<div class="col-md-6">
<h4>Period and proration</h4>
<div class="form-group"><label class="col-sm-5 control-label">Default country pack</label><div class="col-sm-6"><select name="cfg[COUNTRY]" class="form-control">{foreach $countries as $c}<option value="{$c.code}" {if $cfg.COUNTRY==$c.code}selected{/if}>{$c.name}{if !$c.verified} — unverified{/if}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Currency</label><div class="col-sm-6"><input name="cfg[CURRENCY]" class="form-control" value="{$cfg.CURRENCY}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Pay day</label><div class="col-sm-6"><input name="cfg[PAY_DAY]" type="number" min="1" max="31" class="form-control" value="{$cfg.PAY_DAY}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Proration basis</label><div class="col-sm-6"><select name="cfg[PRORATION]" class="form-control">
<option value="calendar" {if $cfg.PRORATION=='calendar'}selected{/if}>calendar days in the month</option>
<option value="working" {if $cfg.PRORATION=='working'}selected{/if}>working days</option>
<option value="thirtieths" {if $cfg.PRORATION=='thirtieths'}selected{/if}>thirtieths</option></select></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Pay periods a year</label><div class="col-sm-6"><input name="cfg[PERIODS_PER_YEAR]" type="number" class="form-control" value="{$cfg.PERIODS_PER_YEAR}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Working days a month</label><div class="col-sm-6"><input name="cfg[WORKING_DAYS]" type="number" class="form-control" value="{$cfg.WORKING_DAYS}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Standard hours a month</label><div class="col-sm-6"><input name="cfg[MONTH_HOURS]" type="number" class="form-control" value="{$cfg.MONTH_HOURS}"></div></div>

<h4>Controls</h4>
<div class="form-group"><label class="col-sm-5 control-label">Protected net floor</label><div class="col-sm-6"><div class="input-group"><input name="cfg[MIN_NET_PCT]" type="number" step="0.01" class="form-control" value="{$cfg.MIN_NET_PCT}"><span class="input-group-addon">% of gross</span></div>
<span class="help-block">A recovery never takes net pay below this. Zero means simply "never negative".</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Variance flag</label><div class="col-sm-6"><div class="input-group"><input name="cfg[VARIANCE_PCT]" type="number" step="0.01" class="form-control" value="{$cfg.VARIANCE_PCT}"><span class="input-group-addon">%</span></div></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Refund an over-deduction on a final settlement</label><div class="col-sm-6"><select name="cfg[LEAVER_REFUND]" class="form-control">
<option value="0" {if !$cfg.LEAVER_REFUND}selected{/if}>No — clamp at zero and report it for the employee to reclaim</option>
<option value="1" {if $cfg.LEAVER_REFUND}selected{/if}>Yes — refund it through net pay</option></select>
<span class="help-block">The conservative default reports the over-deduction on the payslip; the employee reclaims from the tax authority.</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Money rounds to</label><div class="col-sm-6"><input name="cfg[ROUND_DP]" type="number" class="form-control" value="{$cfg.ROUND_DP}"> decimal places</div></div>
<div class="form-group"><label class="col-sm-5 control-label">Post to the general ledger</label><div class="col-sm-6"><select name="cfg[POST_GL]" class="form-control"><option value="1" {if $cfg.POST_GL}selected{/if}>Yes, when Pulse Accounts is installed</option><option value="0" {if !$cfg.POST_GL}selected{/if}>No</option></select></div></div>
</div>

<div class="col-md-6">
<h4>Employer position</h4>
<div class="form-group"><label class="col-sm-5 control-label">Total employees</label><div class="col-sm-6"><input name="cfg[EMPLOYER_STAFF_COUNT]" type="number" class="form-control" value="{$cfg.EMPLOYER_STAFF_COUNT}">
<span class="help-block">The ITF liability arises at five or more employees. Pension is due from three.</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Annual turnover</label><div class="col-sm-6"><input name="cfg[EMPLOYER_TURNOVER]" type="number" step="0.01" class="form-control" value="{$cfg.EMPLOYER_TURNOVER}">
<span class="help-block">ITF also arises at N50m turnover regardless of headcount.</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Tax authority</label><div class="col-sm-6"><input name="cfg[TAX_STATE]" class="form-control" value="{$cfg.TAX_STATE}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Default PFA</label><div class="col-sm-6"><input name="cfg[PFA_DEFAULT]" class="form-control" value="{$cfg.PFA_DEFAULT}"></div></div>

<h4>Overtime and shift</h4>
{foreach ['OT_MULTIPLIER'=>'Overtime multiplier','OT_REST_MULTIPLIER'=>'Rest-day multiplier','OT_HOLIDAY_MULTIPLIER'=>'Public-holiday multiplier','NIGHT_ALLOWANCE'=>'Night-shift allowance per shift'] as $k => $lbl}
<div class="form-group"><label class="col-sm-5 control-label">{$lbl}</label><div class="col-sm-6"><input name="cfg[{$k}]" type="number" step="0.01" class="form-control" value="{$cfg[$k]}"></div></div>
{/foreach}

<h4>Service charge and casuals</h4>
<div class="form-group"><label class="col-sm-5 control-label">Service charge rate</label><div class="col-sm-6"><div class="input-group"><input name="cfg[TRONC_PCT]" type="number" step="0.001" class="form-control" value="{$cfg.TRONC_PCT}"><span class="input-group-addon">%</span></div></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Administration share</label><div class="col-sm-6"><div class="input-group"><input name="cfg[TRONC_ADMIN_PCT]" type="number" step="0.001" class="form-control" value="{$cfg.TRONC_ADMIN_PCT}"><span class="input-group-addon">%</span></div></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Management cap</label><div class="col-sm-6"><div class="input-group"><input name="cfg[TRONC_MGMT_CAP_PCT]" type="number" step="0.001" class="form-control" value="{$cfg.TRONC_MGMT_CAP_PCT}"><span class="input-group-addon">%</span></div></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Distribute by</label><div class="col-sm-6"><select name="cfg[TRONC_BASIS]" class="form-control"><option value="points" {if $cfg.TRONC_BASIS=='points'}selected{/if}>points</option><option value="hours" {if $cfg.TRONC_BASIS=='hours'}selected{/if}>hours</option><option value="equal" {if $cfg.TRONC_BASIS=='equal'}selected{/if}>equal</option></select></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Casual deduction rate</label><div class="col-sm-6"><div class="input-group"><input name="cfg[CASUAL_TAX_PCT]" type="number" step="0.001" class="form-control" value="{$cfg.CASUAL_TAX_PCT}"><span class="input-group-addon">%</span></div></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Default casual day rate</label><div class="col-sm-6"><input name="cfg[CASUAL_DAY_RATE]" type="number" step="0.01" class="form-control" value="{$cfg.CASUAL_DAY_RATE}"></div></div>

<h4>Payslips</h4>
<div class="form-group"><label class="col-sm-5 control-label">Email payslips</label><div class="col-sm-6"><select name="cfg[PAYSLIP_EMAIL]" class="form-control"><option value="1" {if $cfg.PAYSLIP_EMAIL}selected{/if}>Yes, after approval</option><option value="0" {if !$cfg.PAYSLIP_EMAIL}selected{/if}>No</option></select></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Download link lifetime</label><div class="col-sm-6"><input name="cfg[PAYSLIP_TOKEN_DAYS]" type="number" class="form-control" value="{$cfg.PAYSLIP_TOKEN_DAYS}"> days</div></div>
<div class="form-group"><div class="col-sm-offset-5 col-sm-6"><button name="saveSettings" class="btn btn-primary btn-lg">Save settings</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="se-banks">
<h4>The payment file layout</h4>
<p class="text-muted">The NIBSS-style layout emits these columns, in this order: <code>{foreach $nibss_columns as $c}{$c}{if !$c@last}, {/if}{/foreach}</code>, followed by a control row carrying the record count, the control total and the value date. If your bank wants its own columns, choose the generic layout and list them below in your bank's order.</p>
<form method="post" class="form-inline"><input name="bank_columns" class="form-control" style="width:70%" value="{$bank_columns|escape:'html':'UTF-8'}" placeholder="account_number,account_name,beneficiary_bank_code,amount,narration">
<button name="saveBankColumns" class="btn btn-default">Save the column map</button></form>
<p class="text-muted"><small>Available names: account_number, account_name, beneficiary_bank_code, beneficiary_bank, amount, narration, staff_number, email, phone, department, value_date, currency.</small></p>
<h4>Bank register</h4>
<table class="table table-condensed"><thead><tr><th>Bank</th><th>NIBSS code</th><th>Sort code</th><th>SWIFT</th><th>Country</th><th>Layout</th><th>Active</th></tr></thead><tbody>
{foreach $banks as $b}<tr class="{if !$b.active}text-muted{/if}"><td>{$b.name}</td><td>{$b.nibss_code}</td><td>{$b.sort_code}</td><td>{$b.swift}</td><td>{$b.country}</td><td>{$b.template}</td><td>{if $b.active}✓{/if}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline">
<input name="bname" class="form-control input-sm" placeholder="bank name" required>
<input name="nibss_code" class="form-control input-sm" placeholder="NIBSS code" style="width:110px">
<input name="sort_code" class="form-control input-sm" placeholder="sort code" style="width:110px">
<input name="swift" class="form-control input-sm" placeholder="SWIFT" style="width:110px">
<input name="bcountry" class="form-control input-sm" value="NG" style="width:70px">
<select name="btemplate" class="form-control input-sm"><option value="nibss">nibss</option><option value="generic">generic</option></select>
<input name="bsort" type="number" class="form-control input-sm" placeholder="sort" style="width:80px">
<input type="hidden" name="bactive" value="1">
<button name="saveBank" class="btn btn-primary btn-sm">Save bank</button></form>
</div>

<div class="tab-pane" id="se-check">
<div class="alert alert-info">The self-check builds a handful of throw-away employees on the shipped Nigerian structure, runs them through the <em>real</em> calculator, asserts the answers against figures an accountant can reproduce by hand from the published bands, and deletes everything it created. Run it after any rate change.</div>
<form method="post"><button name="runSelfCheck" class="btn btn-primary btn-lg">Run the self-check</button></form>
{if $self_check}
<p class="{if $self_check.ok}text-success{else}text-danger{/if}"><strong>{if $self_check.ok}All {$self_check.passed} assertions passed{else}{$self_check.failed} of {$self_check.passed+$self_check.failed} assertions FAILED{/if}</strong> &mdash; run at {$self_check.run_at}</p>
<table class="table table-condensed"><thead><tr><th>Case</th><th>What is being checked</th><th class="text-right">Expected</th><th class="text-right">Actual</th><th></th></tr></thead><tbody>
{foreach $self_check.cases as $c}<tr class="{if !$c.pass}danger{/if}"><td>{$c.case}</td><td>{$c.metric}</td><td class="text-right">{$c.expected}</td><td class="text-right">{$c.actual}</td><td>{if $c.pass}<span class="text-success">pass</span>{else}<strong class="text-danger">FAIL</strong>{/if}</td></tr>{/foreach}
</tbody></table>
{/if}
</div>

<div class="tab-pane" id="se-cron">
<p>Run this hourly. It emails the payslips queued by an approved run, keeps the ITF accrual and the statutory remittance rows current, refreshes loan balances and flags anything past its remittance due date. Nothing here approves, pays or posts on its own.</p>
<pre>{$cron_url|escape:'html':'UTF-8'}&amp;task=all</pre>
<pre>php modules/pulsepayroll/cron/payroll.php {$cfg.CRON_TOKEN} all</pre>
<p>Tasks: <code>payslips</code> · <code>accrue</code> · <code>loans</code> · <code>remind</code> · <code>all</code> · <code>selfcheck</code> (exits non-zero on a failure, so a monitoring job can watch it).</p>
<form method="post"><button name="newToken" class="btn btn-default btn-sm" onclick="return confirm('Generate a new token? The old URL stops working.')">Generate a new cron token</button></form>
</div>

</div></div></div>
