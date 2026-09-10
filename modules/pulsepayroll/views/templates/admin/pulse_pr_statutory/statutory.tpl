<div class="pulse-pr"><div class="panel"><h3><i class="icon-legal"></i> Statutory &mdash; {$row.name|default:$country}
{if $pack_verified}<span class="label label-success">verified pack</span>{else}<span class="label label-warning">starting point — verify locally before use</span>{/if}</h3>
<p class="text-muted">{$pack_label}. Rates, bands, reliefs and contributions are data with effective dates. A rate change is an edit here, never a code release &mdash; and the superseded rows keep their effective-to date so a back-dated recalculation of an old period still produces the old answer.</p>
{if $warnings_list}<div class="alert alert-warning"><ul class="list-unstyled">{foreach $warnings_list as $w}<li>&bull; {$w}</li>{/foreach}</ul></div>{/if}
<form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulsePrStatutory"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<label>Country</label> <select name="country" class="form-control input-sm">{foreach $countries as $c}<option value="{$c.code}" {if $country==$c.code}selected{/if}>{$c.name} ({$c.code})</option>{/foreach}</select>
<label>In force on</label> <input type="date" name="as_at" class="form-control input-sm" value="{$as_at}">
<button class="btn btn-default btn-sm">Show</button></form>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#st-bands">Tax bands</a></li>
<li><a data-toggle="tab" href="#st-reliefs">Reliefs</a></li>
<li><a data-toggle="tab" href="#st-contrib">Contributions</a></li>
<li><a data-toggle="tab" href="#st-country">Country</a></li>
<li><a data-toggle="tab" href="#st-remit">Remittances</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="st-bands">
<h4>In force on {$as_at}</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>From</th><th>To</th><th>Rate</th><th>Basis</th><th>Note</th></tr></thead><tbody>
{foreach $bands_now as $b}<tr><td>{$b.seq}</td><td>{displayPrice price=$b.band_from}</td><td>{if $b.band_to === null}<em>and above</em>{else}{displayPrice price=$b.band_to}{/if}</td>
<td><strong>{$b.rate_pct|floatval}%</strong></td><td>{$b.basis}</td><td><small>{$b.note}</small></td></tr>
{foreachelse}<tr class="danger"><td colspan="6"><strong>No bands are in force on this date.</strong> Nothing will be taxed until you add them.</td></tr>{/foreach}
</tbody></table>

<h4>Make a rate change</h4>
<form method="post" class="form-inline"><input type="hidden" name="country" value="{$country}">
<label>Close the current set and copy it forward, effective</label> <input type="date" name="new_from" class="form-control input-sm">
<button name="supersede" class="btn btn-primary btn-sm" onclick="return confirm('Close the current bands the day before, and copy them forward as the starting point for the new set?')">Supersede</button>
<span class="help-block">This is the safe way. The old rows keep their effective-to date, so last year still calculates as last year. Then edit the new rows below.</span></form>

<h4>Every band ever set for {$country}</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Regime</th><th>From</th><th>To</th><th>Rate</th><th>Basis</th><th>Effective from</th><th>Effective to</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $bands_all as $b}<tr class="{if $b.effective_to && $b.effective_to < $as_at}text-muted{/if}">
<td>{$b.seq}</td><td>{$b.regime}</td><td>{$b.band_from|floatval}</td><td>{if $b.band_to === null}∞{else}{$b.band_to|floatval}{/if}</td><td>{$b.rate_pct|floatval}%</td><td>{$b.basis}</td>
<td>{$b.effective_from}</td><td>{$b.effective_to|default:'—'}</td><td><small>{$b.note}</small></td>
<td><form method="post" class="inline"><input type="hidden" name="country" value="{$country}"><input type="hidden" name="rate_table" value="pulse_pr_tax_band"><input type="hidden" name="rate_id" value="{$b.id_pulse_pr_tax_band}">
<button name="deleteRate" class="btn btn-xs btn-link" onclick="return confirm('Delete this band? Prefer setting an effective-to date instead.')">✕</button></form></td></tr>{/foreach}
</tbody></table>

<h4>Add or amend a band</h4>
<form method="post" class="form-inline"><input type="hidden" name="country" value="{$country}">
<input name="id_pulse_pr_tax_band" type="number" class="form-control input-sm" placeholder="id (blank = new)" style="width:130px">
<input name="regime" class="form-control input-sm" value="paye" style="width:90px">
<input name="seq" type="number" class="form-control input-sm" placeholder="#" style="width:70px">
<input name="band_from" type="number" step="0.01" class="form-control input-sm" placeholder="from" style="width:130px">
<input name="band_to" type="number" step="0.01" class="form-control input-sm" placeholder="to (blank = ∞)" style="width:140px">
<input name="rate_pct" type="number" step="0.001" class="form-control input-sm" placeholder="rate %" style="width:100px">
<select name="basis" class="form-control input-sm"><option value="annual">annual</option><option value="monthly">monthly</option></select>
<input type="date" name="effective_from" class="form-control input-sm">
<input type="date" name="effective_to" class="form-control input-sm">
<input name="note" class="form-control input-sm" placeholder="note">
<button name="saveBand" class="btn btn-primary btn-sm">Save band</button></form>
</div>

<div class="tab-pane" id="st-reliefs">
<h4>In force on {$as_at}</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>%</th><th>Fixed</th><th>Cap</th><th>Base</th><th>Basis</th><th>Evidence</th><th>Declaration</th><th>Conditions</th></tr></thead><tbody>
{foreach $reliefs_now as $r}<tr><td><strong>{$r.code}</strong></td><td>{$r.name}</td><td>{$r.type|replace:'_':' '}</td><td>{$r.value_pct|floatval}</td><td>{$r.value_fixed|floatval}</td>
<td>{if $r.cap === null}—{else}{$r.cap|floatval}{/if}</td><td>{$r.base}</td><td>{$r.basis}</td>
<td>{if $r.requires_evidence}<span class="label label-warning">required</span>{else}—{/if}</td><td>{$r.declaration_code|default:'—'}</td><td><small>{$r.conditions}</small></td></tr>{/foreach}
</tbody></table>
<h4>Every relief ever set</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Type</th><th>%</th><th>Fixed</th><th>Cap</th><th>Base</th><th>Evidence</th><th>From</th><th>To</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $reliefs_all as $r}<tr class="{if $r.effective_to && $r.effective_to < $as_at}text-muted{/if}">
<td>{$r.code}</td><td>{$r.type|replace:'_':' '}</td><td>{$r.value_pct|floatval}</td><td>{$r.value_fixed|floatval}</td><td>{if $r.cap === null}—{else}{$r.cap|floatval}{/if}</td>
<td>{$r.base}</td><td>{if $r.requires_evidence}yes{else}no{/if}</td><td>{$r.effective_from}</td><td>{$r.effective_to|default:'—'}</td><td>{if $r.active}✓{/if}</td>
<td><form method="post" class="inline"><input type="hidden" name="country" value="{$country}"><input type="hidden" name="rate_table" value="pulse_pr_relief"><input type="hidden" name="rate_id" value="{$r.id_pulse_pr_relief}"><button name="deleteRate" class="btn btn-xs btn-link" onclick="return confirm('Delete?')">✕</button></form></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="country" value="{$country}">
<input name="id_pulse_pr_relief" type="number" class="form-control input-sm" placeholder="id" style="width:80px">
<input name="code" class="form-control input-sm" placeholder="code" style="width:110px">
<input name="name" class="form-control input-sm" placeholder="name">
<select name="type" class="form-control input-sm"><option value="capped_percent">capped percent</option><option value="percent_of">percent of</option><option value="fixed">fixed</option><option value="greater_of">greater of</option></select>
<input name="value_pct" type="number" step="0.001" class="form-control input-sm" placeholder="%" style="width:80px">
<input name="value_fixed" type="number" step="0.01" class="form-control input-sm" placeholder="fixed" style="width:110px">
<input name="cap" type="number" step="0.01" class="form-control input-sm" placeholder="cap" style="width:110px">
<select name="base" class="form-control input-sm"><option value="DECLARED">DECLARED</option>{foreach $bases as $b}<option value="{$b}">{$b}</option>{/foreach}<option value="CONTRIB:PENSION">CONTRIB:PENSION</option><option value="CONTRIB:NHF">CONTRIB:NHF</option></select>
<select name="basis" class="form-control input-sm"><option value="annual">annual</option><option value="monthly">monthly</option></select>
<label><input type="checkbox" name="requires_evidence" value="1"> evidence</label>
<input name="declaration_code" class="form-control input-sm" placeholder="declaration code" style="width:150px">
<input type="date" name="effective_from" class="form-control input-sm">
<input type="date" name="effective_to" class="form-control input-sm">
<input type="hidden" name="active" value="1">
<button name="saveRelief" class="btn btn-primary btn-sm">Save relief</button></form>
</div>

<div class="tab-pane" id="st-contrib">
<h4>In force on {$as_at}</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Employee</th><th>Employer</th><th>Base</th><th>Ceiling</th><th>Mode</th><th>Consent</th><th>Pre-tax</th><th>Employer test</th><th>Remit</th><th>GL</th></tr></thead><tbody>
{foreach $contribs_now as $c}<tr class="{if $c.mode=='opt_in'}warning{/if}">
<td><strong>{$c.code}</strong></td><td>{$c.name}</td><td>{$c.employee_pct|floatval}%</td><td>{$c.employer_pct|floatval}%</td><td>{$c.base}</td>
<td>{if $c.ceiling === null}—{else}{$c.ceiling|floatval}{/if}</td><td>{$c.mode|replace:'_':'-'}</td><td>{$c.consent_code|default:'—'}</td>
<td>{if $c.pre_tax}✓{/if}</td><td>{if $c.employer_min_staff}{$c.employer_min_staff}+ staff{/if}{if $c.employer_min_turnover} or {$c.employer_min_turnover|floatval} turnover{/if}</td>
<td><small>{if $c.remit_within_days}within {$c.remit_within_days} days. {/if}{$c.remit_rule}</small></td><td>{$c.gl_liability}</td></tr>{/foreach}
</tbody></table>
<div class="alert alert-info">An <strong>opt-in</strong> scheme deducts nothing until a dated employee consent is recorded on the employee's Declarations tab. This is how NHF is modelled since it became voluntary: deducting without consent would be outside the law, so the module simply will not do it.</div>
<h4>Every contribution ever set</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Ee %</th><th>Er %</th><th>Base</th><th>Mode</th><th>From</th><th>To</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $contribs_all as $c}<tr class="{if $c.effective_to && $c.effective_to < $as_at}text-muted{/if}">
<td>{$c.code}</td><td>{$c.employee_pct|floatval}</td><td>{$c.employer_pct|floatval}</td><td>{$c.base}</td><td>{$c.mode}</td><td>{$c.effective_from}</td><td>{$c.effective_to|default:'—'}</td><td>{if $c.active}✓{/if}</td>
<td><form method="post" class="inline"><input type="hidden" name="country" value="{$country}"><input type="hidden" name="rate_table" value="pulse_pr_contribution"><input type="hidden" name="rate_id" value="{$c.id_pulse_pr_contribution}"><button name="deleteRate" class="btn btn-xs btn-link" onclick="return confirm('Delete?')">✕</button></form></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="country" value="{$country}">
<input name="id_pulse_pr_contribution" type="number" class="form-control input-sm" placeholder="id" style="width:80px">
<input name="code" class="form-control input-sm" placeholder="code" style="width:110px">
<input name="name" class="form-control input-sm" placeholder="name">
<input name="employee_pct" type="number" step="0.001" class="form-control input-sm" placeholder="ee %" style="width:90px">
<input name="employer_pct" type="number" step="0.001" class="form-control input-sm" placeholder="er %" style="width:90px">
<select name="base" class="form-control input-sm">{foreach $bases as $b}<option value="{$b}">{$b}</option>{/foreach}</select>
<input name="ceiling" type="number" step="0.01" class="form-control input-sm" placeholder="ceiling" style="width:110px">
<select name="frequency" class="form-control input-sm"><option value="monthly">monthly</option><option value="annual">annual</option></select>
<select name="mode" class="form-control input-sm"><option value="mandatory">mandatory</option><option value="opt_in">opt-in (needs consent)</option><option value="opt_out">opt-out</option><option value="disabled">disabled</option></select>
<input name="consent_code" class="form-control input-sm" placeholder="consent code" style="width:150px">
<label><input type="checkbox" name="pre_tax" value="1" checked> pre-tax</label>
<input name="gl_liability" class="form-control input-sm" placeholder="GL" style="width:80px">
<input type="date" name="effective_from" class="form-control input-sm">
<input type="date" name="effective_to" class="form-control input-sm">
<input type="hidden" name="active" value="1">
<button name="saveContribution" class="btn btn-primary btn-sm">Save contribution</button></form>
</div>

<div class="tab-pane" id="st-country">
<form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Code</label><div class="col-sm-6"><input name="code" class="form-control" value="{$row.code}" maxlength="2"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Name</label><div class="col-sm-6"><input name="name" class="form-control" value="{$row.name|escape:'html':'UTF-8'}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Currency</label><div class="col-sm-6"><input name="currency" class="form-control" value="{$row.currency}" maxlength="3"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Tax year starts</label><div class="col-sm-6"><input name="tax_year_start" class="form-control" value="{$row.tax_year_start}" placeholder="MM-DD"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pack class</label><div class="col-sm-6"><select name="statutory_class" class="form-control">{foreach $classes as $k => $v}<option value="{$k}" {if $row.statutory_class==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">PAYE basis</label><div class="col-sm-6"><select name="paye_basis" class="form-control"><option value="annual" {if $row.paye_basis=='annual'}selected{/if}>annual bands, annualised and de-annualised</option><option value="monthly" {if $row.paye_basis=='monthly'}selected{/if}>monthly bands applied to the period</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">PAYE mode</label><div class="col-sm-6"><select name="paye_mode" class="form-control"><option value="cumulative" {if $row.paye_mode=='cumulative'}selected{/if}>cumulative (no drift over the year)</option><option value="non_cumulative" {if $row.paye_mode=='non_cumulative'}selected{/if}>non-cumulative</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Rounding</label><div class="col-sm-6"><select name="rounding" class="form-control">{foreach ['round','floor','ceil'] as $t}<option value="{$t}" {if $row.rounding==$t}selected{/if}>{$t}</option>{/foreach}</select>
<input name="rounding_dp" type="number" class="form-control" value="{$row.rounding_dp}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Verified</label><div class="col-sm-6"><label><input type="checkbox" name="verified" value="1" {if $row.verified}checked{/if}> This pack has been checked against local law and may be used for live payroll</label></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Active</label><div class="col-sm-6"><input type="checkbox" name="active" value="1" {if $row.active}checked{/if}></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-6"><textarea name="note" class="form-control" rows="3">{$row.note|escape:'html':'UTF-8'}</textarea></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-6"><button name="saveCountry" class="btn btn-primary btn-lg">Save country</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="st-remit">
<table class="table table-condensed"><thead><tr><th>Scheme</th><th>Period</th><th>Authority</th><th>Due</th><th>Amount</th><th>Paid</th><th>Status</th><th>Reference</th><th>Record a payment</th></tr></thead><tbody>
{foreach $remittances as $r}<tr class="{if $r.status=='overdue'}danger{elseif $r.status=='paid'}success{/if}">
<td>{$r.scheme|upper}</td><td>{$r.period}</td><td>{$r.authority}</td><td>{$r.due_date}</td><td>{displayPrice price=$r.amount_due}</td><td>{displayPrice price=$r.amount_paid}</td><td>{$r.status}</td><td>{$r.reference}</td>
<td>{if $r.status!='paid'}<form method="post" class="form-inline"><input type="hidden" name="country" value="{$country}"><input type="hidden" name="id_remittance" value="{$r.id_pulse_pr_remittance}">
<input name="amount_paid" type="number" step="0.01" class="form-control input-sm" value="{($r.amount_due-$r.amount_paid)|floatval}" style="width:120px">
<input type="date" name="date_paid" class="form-control input-sm" value="{$smarty.now|date_format:'%Y-%m-%d'}">
<input name="reference" class="form-control input-sm" placeholder="receipt ref" style="width:130px">
<button name="payRemittance" class="btn btn-xs btn-default">Record</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="9"><em>Nothing yet — remittances appear as soon as a run is approved.</em></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
