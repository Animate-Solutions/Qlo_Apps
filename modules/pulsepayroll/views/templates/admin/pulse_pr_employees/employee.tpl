<div class="pulse-pr"><div class="panel">
<h3><i class="icon-user"></i> {$e.firstname} {$e.lastname} <small>{$e.staff_no} &mdash; {$e.department}{if $e.position}, {$e.position}{/if}</small>
<a class="btn btn-xs btn-default pull-right" href="{$self_url}">Back to the roster</a></h3>
<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#pe-detail">Details</a></li>
<li><a data-toggle="tab" href="#pe-struct">Pay structure</a></li>
<li><a data-toggle="tab" href="#pe-decl">Declarations &amp; consents</a></li>
<li><a data-toggle="tab" href="#pe-time">Timesheet</a></li>
<li><a data-toggle="tab" href="#pe-pay">Payslips &amp; YTD</a></li>
<li><a data-toggle="tab" href="#pe-loans">Loans</a></li>
<li><a data-toggle="tab" href="#pe-open">Opening balances</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="pe-detail">
<form method="post" class="form-horizontal"><input type="hidden" name="id_pulse_pr_employee" value="{$e.id_pulse_pr_employee}">
<input type="hidden" name="id_hr_employee" value="{$e.id_hr_employee|intval}"><input type="hidden" name="id_employee" value="{$e.id_employee|intval}">
<div class="row"><div class="col-md-6">
{foreach ['staff_no'=>'Staff number','firstname'=>'First name','lastname'=>'Last name','department'=>'Department','section'=>'Section','position'=>'Position','grade'=>'Grade','cost_centre'=>'Cost centre'] as $k => $lbl}
<div class="form-group"><label class="col-sm-4 control-label">{$lbl}</label><div class="col-sm-7"><input name="{$k}" class="form-control" value="{$e[$k]|escape:'html':'UTF-8'}"></div></div>
{/foreach}
<div class="form-group"><label class="col-sm-4 control-label">Employment type</label><div class="col-sm-7"><select name="employment_type" class="form-control">{foreach ['permanent','fixed_term','contract','casual','service','intern'] as $t}<option value="{$t}" {if $e.employment_type==$t}selected{/if}>{$t|replace:'_':' '}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay basis</label><div class="col-sm-7"><select name="pay_basis" class="form-control">{foreach ['monthly','daily','hourly','per_shift'] as $t}<option value="{$t}" {if $e.pay_basis==$t}selected{/if}>{$t|replace:'_':' '}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Contractual package</label><div class="col-sm-7"><input name="pay_rate" type="number" step="0.01" class="form-control" value="{$e.pay_rate|floatval}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Country pack</label><div class="col-sm-7"><select name="country" class="form-control">{foreach $countries as $c}<option value="{$c.code}" {if $e.country==$c.code}selected{/if}>{$c.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Hire / exit date</label><div class="col-sm-7"><input type="date" name="hire_date" class="form-control" value="{$e.hire_date}"><input type="date" name="exit_date" class="form-control" value="{$e.exit_date}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Status</label><div class="col-sm-7"><select name="status" class="form-control">{foreach ['active','probation','suspended','on_leave','exited'] as $t}<option value="{$t}" {if $e.status==$t}selected{/if}>{$t|replace:'_':' '}</option>{/foreach}</select></div></div>
</div><div class="col-md-6">
{foreach ['tin'=>'TIN','tax_state'=>'Tax authority','rsa_pin'=>'RSA PIN','pfa'=>'PFA','nhf_no'=>'NHF number','nsitf_no'=>'NSITF number','nin'=>'NIN','account_name'=>'Account name','account_no'=>'Account number','bank_code'=>'Bank code','email'=>'Email','phone'=>'Phone'] as $k => $lbl}
<div class="form-group"><label class="col-sm-4 control-label">{$lbl}</label><div class="col-sm-7"><input name="{$k}" class="form-control" value="{$e[$k]|escape:'html':'UTF-8'}"></div></div>
{/foreach}
<div class="form-group"><label class="col-sm-4 control-label">Bank</label><div class="col-sm-7"><select name="bank_name" class="form-control"><option value="">—</option>{foreach $banks as $b}<option value="{$b.name}" {if $e.bank_name==$b.name}selected{/if}>{$b.name} ({$b.nibss_code})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay method</label><div class="col-sm-7"><select name="pay_method" class="form-control">{foreach ['bank','cash','cheque'] as $t}<option value="{$t}" {if $e.pay_method==$t}selected{/if}>{$t}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Hold pay</label><div class="col-sm-7"><input type="hidden" name="on_hold" value="0"><label><input type="checkbox" name="on_hold" value="1" {if $e.on_hold}checked{/if}> Exclude from runs</label><input name="hold_reason" class="form-control" value="{$e.hold_reason|escape:'html':'UTF-8'}" placeholder="reason"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-7"><input name="note" class="form-control" value="{$e.note|escape:'html':'UTF-8'}"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-7"><button name="saveEmployee" class="btn btn-primary btn-lg">Save</button></div></div>
</div></div></form>
<hr>
<form method="post" class="form-inline">
<input type="hidden" name="id_pulse_pr_employee" value="{$e.id_pulse_pr_employee}"><input type="hidden" name="staff_no" value="{$e.staff_no|escape:'html':'UTF-8'}">
<input type="hidden" name="firstname" value="{$e.firstname|escape:'html':'UTF-8'}"><input type="hidden" name="lastname" value="{$e.lastname|escape:'html':'UTF-8'}">
<label>Payslip PIN</label> <input name="payslip_pin" class="form-control input-sm" placeholder="new PIN"> <button name="setPin" class="btn btn-default btn-sm">Set</button>
<span class="help-block">The PIN gates the tokenised payslip download. It is stored hashed and defaults to the last four characters of the staff number.</span></form>
</div>

<div class="tab-pane" id="pe-struct">
<p class="text-muted">Payroll reads the structure effective on the period being paid, never today's. A promotion is a new line with a new effective date &mdash; the old line stays so a back-dated recalculation still works.</p>
<h4>In force for {$period}</h4>
<table class="table table-condensed"><thead><tr><th>Element</th><th>Type</th><th>Calculation</th><th>Percent</th><th>Amount</th><th>Units</th><th>From</th></tr></thead><tbody>
{foreach $structure as $s}<tr><td>{$s.name} <small class="text-muted">{$s.element_code}</small></td><td>{$s.type}</td><td>{$s.calc}{if $s.percent_of} of {$s.percent_of}{/if}</td>
<td>{if $s.percent}{$s.percent|floatval}%{/if}</td><td>{if $s.amount}{displayPrice price=$s.amount}{/if}</td><td>{if $s.units}{$s.units|floatval}{/if}</td><td>{$s.effective_from}</td></tr>
{foreachelse}<tr><td colspan="7"><em>No employee-specific structure — the grade default for "{$e.grade|default:'DEFAULT'}" applies.</em></td></tr>{/foreach}
</tbody></table>
<h4>All lines on this employee</h4>
<table class="table table-condensed"><thead><tr><th>Element</th><th>Percent</th><th>Amount</th><th>Units</th><th>From</th><th>To</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $structure_rows as $s}<tr><td>{$s.element_code}</td><td>{$s.percent|floatval}</td><td>{$s.amount|floatval}</td><td>{$s.units|floatval}</td><td>{$s.effective_from}</td><td>{$s.effective_to|default:'—'}</td><td><small>{$s.note}</small></td>
<td><form method="post" class="inline"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}"><input type="hidden" name="id_structure" value="{$s.id_pulse_pr_employee_element}"><button name="deleteStructure" class="btn btn-xs btn-link" onclick="return confirm('Remove this line? Prefer setting an effective-to date so the history survives.')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em>None.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}">
<select name="element_code" class="form-control input-sm">{foreach $elements as $el}<option value="{$el.code}">{$el.code} — {$el.name}</option>{/foreach}</select>
<input name="percent" type="number" step="0.0001" class="form-control input-sm" placeholder="% of package" style="width:120px">
<input name="amount" type="number" step="0.01" class="form-control input-sm" placeholder="fixed amount" style="width:130px">
<input name="units" type="number" step="0.001" class="form-control input-sm" placeholder="units" style="width:90px">
<input type="date" name="effective_from" class="form-control input-sm" value="{$period}-01">
<input type="date" name="effective_to" class="form-control input-sm">
<input name="note" class="form-control input-sm" placeholder="note">
<button name="saveStructure" class="btn btn-primary btn-sm">Add line</button></form>
</div>

<div class="tab-pane" id="pe-decl">
<div class="alert alert-info"><strong>Why this screen matters.</strong> Rent relief only reduces tax where the rent is declared <em>and</em> the evidence is marked verified &mdash; an unevidenced claim gets nothing. NHF is voluntary since the 2025 Act: nothing is deducted without a dated consent recorded here, and the consent date is printed on the payslip.</div>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Annual value</th><th>Evidence</th><th>Verified</th><th>Consent</th><th>From</th><th>To</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $declarations as $d}<tr class="{if $d.date_to && $d.date_to < $smarty.now|date_format:'%Y-%m-%d'}text-muted{elseif $d.annual_value > 0 && !$d.evidence_verified}warning{/if}">
<td><strong>{$d.code}</strong></td><td>{if $d.annual_value}{displayPrice price=$d.annual_value}{/if}</td><td><small>{$d.evidence_ref}</small></td>
<td>{if $d.evidence_verified}<span class="label label-success">verified</span>{else}<span class="label label-warning">not verified</span>{/if}</td>
<td>{if $d.consented}<span class="label label-success">consented {$d.consent_date}</span> <small>{$d.consent_channel}</small>{else}—{/if}</td>
<td>{$d.date_from}</td><td>{$d.date_to|default:'—'}</td><td><small>{$d.note}</small></td>
<td>{if !$d.date_to}<form method="post" class="inline"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}"><input type="hidden" name="id_declaration" value="{$d.id_pulse_pr_declaration}"><input type="hidden" name="date_to" value="{$smarty.now|date_format:'%Y-%m-%d'}"><button name="endDeclaration" class="btn btn-xs btn-link" onclick="return confirm('End this declaration today? The history is kept.')">End</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="9"><em>No declarations. No rent relief, no NHF, no life-assurance or mortgage relief will be applied.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-horizontal"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Type</label><div class="col-sm-7"><select name="code" class="form-control">
<option value="RENT">RENT — annual rent paid (20% relief, capped at 500,000)</option>
<option value="NHF_CONSENT">NHF_CONSENT — employee consents to the 2.5% NHF deduction</option>
<option value="NHIS_CONSENT">NHIS_CONSENT — employee opts into the health scheme</option>
<option value="LIFE">LIFE — life assurance premium</option>
<option value="MORTGAGE">MORTGAGE — interest on an owner-occupied home</option>
<option value="PENSION_VOL">PENSION_VOL — voluntary additional pension</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Annual value</label><div class="col-sm-7"><input name="annual_value" type="number" step="0.01" class="form-control" placeholder="rent or premium per year"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Effective from</label><div class="col-sm-7"><input type="date" name="date_from" class="form-control" value="{$smarty.now|date_format:'%Y'}-01-01"></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Evidence reference</label><div class="col-sm-7"><input name="evidence_ref" class="form-control" placeholder="tenancy agreement, policy number, receipt"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Evidence</label><div class="col-sm-7"><label><input type="checkbox" name="evidence_verified" value="1"> I have seen the document and it supports the amount</label></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Consent</label><div class="col-sm-7"><label><input type="checkbox" name="consented" value="1"> The employee has consented, in writing</label>
<input type="date" name="consent_date" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}">
<input name="consent_channel" class="form-control" placeholder="signed form / email / portal" value="signed form"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-7"><input name="dnote" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-7"><button name="saveDeclaration" class="btn btn-primary">Record declaration</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="pe-time">
{if $ta}<div class="alert alert-info">Pulse Time is installed. An approved timesheet there is used first; the entry below is the fallback for a period Time has not signed off.</div>
{else}<div class="alert alert-warning">Pulse Time is not installed, so overtime, night shifts and unpaid days are keyed in here.</div>{/if}
<form method="post" class="form-horizontal"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Period</label><div class="col-sm-7"><input name="ts_period" class="form-control" value="{$period}"></div></div>
{foreach ['days_worked'=>'Days worked','hours_worked'=>'Hours worked','shifts'=>'Shifts','ot_hours'=>'Overtime hours'] as $k => $lbl}
<div class="form-group"><label class="col-sm-4 control-label">{$lbl}</label><div class="col-sm-7"><input name="{$k}" type="number" step="0.001" class="form-control" value="{if $timesheet}{$timesheet[$k]|floatval}{else}0{/if}"></div></div>
{/foreach}
</div><div class="col-md-6">
{foreach ['night_shifts'=>'Night shifts','unpaid_days'=>'Unpaid days (leave without pay)'] as $k => $lbl}
<div class="form-group"><label class="col-sm-4 control-label">{$lbl}</label><div class="col-sm-7"><input name="{$k}" type="number" step="0.001" class="form-control" value="{if $timesheet}{$timesheet[$k]|floatval}{else}0{/if}"></div></div>
{/foreach}
<div class="form-group"><label class="col-sm-4 control-label">Approved</label><div class="col-sm-7"><label><input type="checkbox" name="approved" value="1" {if $timesheet && $timesheet.approved}checked{/if}> A supervisor has signed this off</label><span class="help-block">Payroll only ever pays from an approved timesheet.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-7"><input name="tsnote" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-7"><button name="saveTimesheet" class="btn btn-primary">Save timesheet</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="pe-pay">
<h4>Year to date {$smarty.now|date_format:'%Y'}</h4>
<table class="table table-condensed"><tbody><tr>
<td>Periods paid <strong>{$ytd.periods}</strong></td><td>Gross <strong>{displayPrice price=$ytd.gross}</strong></td><td>Taxable <strong>{displayPrice price=$ytd.taxable}</strong></td>
<td>PAYE <strong>{displayPrice price=$ytd.paye}</strong></td><td>Pension (ee) <strong>{displayPrice price=$ytd.pension_ee}</strong></td><td>NHF <strong>{displayPrice price=$ytd.nhf}</strong></td><td>Net <strong>{displayPrice price=$ytd.net}</strong></td>
</tr></tbody></table>
<h4>Payslips</h4>
<table class="table table-condensed"><thead><tr><th>Period</th><th>Run</th><th>Pay date</th><th>Gross</th><th>Deductions</th><th>Net</th><th></th></tr></thead><tbody>
{foreach $payslips as $p}<tr><td>{$p.period}</td><td>{$p.run_no}</td><td>{$p.pay_date}</td><td>{displayPrice price=$p.gross}</td><td>{displayPrice price=$p.total_deductions}</td><td><strong>{displayPrice price=$p.net_pay}</strong></td>
<td><a class="btn btn-xs btn-default" href="{$link_payroll}&id_payslip={$p.id_pulse_pr_payslip}">View</a></td></tr>
{foreachelse}<tr><td colspan="7"><em>No payslips yet.</em></td></tr>{/foreach}
</tbody></table>
{if $tronc}<h4>Service charge</h4><table class="table table-condensed"><thead><tr><th>Period</th><th>Pool</th><th>Basis</th><th>Share</th><th>Amount</th><th>Status</th></tr></thead><tbody>
{foreach $tronc as $t}<tr><td>{$t.period}</td><td>{$t.pool_no}</td><td>{$t.basis}</td><td>{$t.share_pct}%</td><td>{displayPrice price=$t.amount}</td><td>{$t.status}</td></tr>{/foreach}</tbody></table>{/if}
</div>

<div class="tab-pane" id="pe-loans">
<p>Outstanding: loans <strong>{displayPrice price=$balances.loan}</strong>, arrears <strong>{displayPrice price=$balances.arrears}</strong>.</p>
<table class="table table-condensed"><thead><tr><th>Loan</th><th>Type</th><th>Principal</th><th>Repayable</th><th>Recovered</th><th>Balance</th><th>Instalment</th><th>Status</th></tr></thead><tbody>
{foreach $loans as $l}<tr><td>{$l.loan_no}</td><td>{$l.type|replace:'_':' '}</td><td>{displayPrice price=$l.principal}</td><td>{displayPrice price=$l.total_repayable}</td>
<td>{displayPrice price=$l.recovered}</td><td><strong>{displayPrice price=$l.balance}</strong></td><td>{displayPrice price=$l.instalment_amount}</td><td>{$l.status|replace:'_':' '}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No loans.</em></td></tr>{/foreach}
</tbody></table>
{if $arrears}<h4>Arrears</h4><table class="table table-condensed"><thead><tr><th>Raised</th><th>Description</th><th>Amount</th><th>Recovered</th><th>Balance</th></tr></thead><tbody>
{foreach $arrears as $a}<tr class="warning"><td>{$a.period_raised}</td><td>{$a.description}</td><td>{displayPrice price=$a.amount}</td><td>{displayPrice price=$a.recovered}</td><td><strong>{displayPrice price=$a.balance}</strong></td></tr>{/foreach}</tbody></table>{/if}
</div>

<div class="tab-pane" id="pe-open">
<div class="alert alert-info">Only needed when the property moves to Pulse part-way through a tax year: enter what the old system had already paid, so the annualised PAYE picks up where it left off instead of starting the year again.</div>
<table class="table table-condensed"><thead><tr><th>Tax year</th><th>Periods</th><th>Gross</th><th>Taxable</th><th>PAYE</th><th>Pension (ee)</th><th>NHF</th><th>Net</th><th>Note</th></tr></thead><tbody>
{foreach $opening as $o}<tr><td>{$o.tax_year}</td><td>{$o.periods}</td><td>{displayPrice price=$o.gross}</td><td>{displayPrice price=$o.taxable}</td><td>{displayPrice price=$o.paye}</td>
<td>{displayPrice price=$o.pension_ee}</td><td>{displayPrice price=$o.nhf}</td><td>{displayPrice price=$o.net}</td><td><small>{$o.note}</small></td></tr>
{foreachelse}<tr><td colspan="9"><em>None.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_pr" value="{$e.id_pulse_pr_employee}">
<input name="tax_year" type="number" class="form-control input-sm" value="{$smarty.now|date_format:'%Y'}" style="width:90px">
<input name="o_periods" type="number" class="form-control input-sm" placeholder="periods" style="width:90px">
<input name="o_gross" type="number" step="0.01" class="form-control input-sm" placeholder="gross" style="width:120px">
<input name="o_taxable" type="number" step="0.01" class="form-control input-sm" placeholder="taxable" style="width:120px">
<input name="o_paye" type="number" step="0.01" class="form-control input-sm" placeholder="PAYE" style="width:110px">
<input name="o_pension_ee" type="number" step="0.01" class="form-control input-sm" placeholder="pension ee" style="width:110px">
<input name="o_pension_er" type="number" step="0.01" class="form-control input-sm" placeholder="pension er" style="width:110px">
<input name="o_nhf" type="number" step="0.01" class="form-control input-sm" placeholder="NHF" style="width:100px">
<input name="o_net" type="number" step="0.01" class="form-control input-sm" placeholder="net" style="width:110px">
<input name="o_note" class="form-control input-sm" placeholder="note">
<button name="saveOpening" class="btn btn-primary btn-sm">Save</button></form>
</div>

</div></div></div>
