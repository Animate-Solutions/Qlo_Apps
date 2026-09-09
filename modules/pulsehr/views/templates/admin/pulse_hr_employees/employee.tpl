<div class="pulse-hr"><div class="panel">
<h3>{$e.full_name} <small class="muted">{$e.staff_no}</small>
<span class="label label-{if $e.status=='active'}success{elseif $e.status=='exited'}default{elseif $e.status=='suspended'}danger{else}warning{/if}">{$e.status|replace:'_':' '}</span>
{if $live_warnings>0}<span class="label label-danger">{$live_warnings} live warning(s)</span>{/if}
<a class="btn btn-default btn-sm pull-right" href="{$self_url}">Back to the list</a>
<a class="btn btn-default btn-sm pull-right" href="javascript:window.print()" style="margin-right:6px"><i class="icon-print"></i> Print file</a></h3>
<p>{$e.dept_name}{if $e.section_name} / {$e.section_name}{/if} &middot; {$e.position_title} &middot; {$e.grade_name}
{if $e.manager_name} &middot; reports to {$e.manager_name}{/if} &middot; joined {$e.hire_date}
{if $e.status=='exited'} &middot; <strong>left {$e.exit_date}</strong> ({$e.exit_type|replace:'_':' '}){/if}</p>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#t-personal">Personal</a></li>
<li><a data-toggle="tab" href="#t-contract">Contracts ({$contracts|count})</a></li>
<li><a data-toggle="tab" href="#t-docs">Documents ({$documents|count})</a></li>
<li><a data-toggle="tab" href="#t-leave">Leave</a></li>
<li><a data-toggle="tab" href="#t-roster">Roster &amp; clockings</a></li>
<li><a data-toggle="tab" href="#t-life">Onboarding &amp; exit</a></li>
<li><a data-toggle="tab" href="#t-perf">Record</a></li>
<li><a data-toggle="tab" href="#t-access">Access &amp; portal</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="t-personal"><form method="post"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<div class="row">
<div class="col-md-4"><h4>Identity</h4>
<label>First name</label><input name="firstname" class="form-control" value="{$e.firstname|escape:'html':'UTF-8'}">
<label>Surname</label><input name="lastname" class="form-control" value="{$e.lastname|escape:'html':'UTF-8'}">
<label>Other names</label><input name="othernames" class="form-control" value="{$e.othernames|escape:'html':'UTF-8'}">
<label>Gender</label><select name="gender" class="form-control">{foreach ['m'=>'Male','f'=>'Female','x'=>'Other'] as $k=>$v}<option value="{$k}"{if $e.gender==$k} selected{/if}>{$v}</option>{/foreach}</select>
<label>Date of birth</label><input type="date" name="dob" class="form-control" value="{$e.dob}">
<label>Marital status</label><select name="marital" class="form-control">{foreach ['single','married','divorced','widowed','other'] as $m}<option value="{$m}"{if $e.marital==$m} selected{/if}>{$m}</option>{/foreach}</select>
<label>Nationality</label><input name="nationality" class="form-control" value="{$e.nationality|escape:'html':'UTF-8'}">
<label>State of origin</label><input name="state_of_origin" class="form-control" value="{$e.state_of_origin|escape:'html':'UTF-8'}">
<label>LGA</label><input name="lga" class="form-control" value="{$e.lga|escape:'html':'UTF-8'}">
</div>
<div class="col-md-4"><h4>Contact and next of kin</h4>
<label>Phone</label><input name="phone" class="form-control" value="{$e.phone|escape:'html':'UTF-8'}">
<label>Second phone</label><input name="phone_alt" class="form-control" value="{$e.phone_alt|escape:'html':'UTF-8'}">
<label>Email</label><input name="email" class="form-control" value="{$e.email|escape:'html':'UTF-8'}">
<label>Address</label><input name="address" class="form-control" value="{$e.address|escape:'html':'UTF-8'}">
<label>Town</label><input name="city" class="form-control" value="{$e.city|escape:'html':'UTF-8'}">
<label>Next of kin</label><input name="nok_name" class="form-control" value="{$e.nok_name|escape:'html':'UTF-8'}">
<label>Relationship</label><input name="nok_relationship" class="form-control" value="{$e.nok_relationship|escape:'html':'UTF-8'}">
<label>Their phone</label><input name="nok_phone" class="form-control" value="{$e.nok_phone|escape:'html':'UTF-8'}">
<label>Their address</label><input name="nok_address" class="form-control" value="{$e.nok_address|escape:'html':'UTF-8'}">
</div>
<div class="col-md-4"><h4>Statutory and bank</h4>
<label>NIN</label><input name="national_id" class="form-control" value="{$e.national_id|escape:'html':'UTF-8'}">
<label>TIN</label><input name="tin" class="form-control" value="{$e.tin|escape:'html':'UTF-8'}">
<label>RSA PIN</label><input name="rsa_pin" class="form-control" value="{$e.rsa_pin|escape:'html':'UTF-8'}">
<label>PFA</label><input name="pfa" class="form-control" value="{$e.pfa|escape:'html':'UTF-8'}">
<label>NHF number</label><input name="nhf_no" class="form-control" value="{$e.nhf_no|escape:'html':'UTF-8'}">
<div class="alert alert-info" style="margin-top:8px">
<label><input type="checkbox" name="nhf_consent" value="1"{if $e.nhf_consent} checked{/if}> <strong>NHF: this employee has consented in writing to the 2.5% deduction</strong></label>
<p class="help-block" style="margin:4px 0 0">NHF is voluntary for private-sector staff. Leave this unticked and nothing is deducted. The date is stamped when you tick it{if $e.nhf_consent_date} — consented {$e.nhf_consent_date}{/if}.</p>
<input name="nhf_consent_note" class="form-control input-sm" placeholder="Where the signed consent is filed" value="{$e.nhf_consent_note|escape:'html':'UTF-8'}">
</div>
<label>Bank</label><input name="bank_name" class="form-control" value="{$e.bank_name|escape:'html':'UTF-8'}">
<label>Bank / sort code</label><input name="bank_code" class="form-control" value="{$e.bank_code|escape:'html':'UTF-8'}">
<label>Account number (NUBAN)</label><input name="account_no" class="form-control" value="{$e.account_no|escape:'html':'UTF-8'}">
<label>Account name</label><input name="account_name" class="form-control" value="{$e.account_name|escape:'html':'UTF-8'}">
</div></div>
<button name="saveEmployee" class="btn btn-primary" style="margin-top:12px">Save personal details</button>
</form></div>

<div class="tab-pane" id="t-contract">
<div class="alert alert-info"><strong>Contracts are versioned.</strong> Payroll reads the version in force on the period it is paying, not the newest one — so a promotion dated the 15th does not rewrite what the 1st to the 14th was paid on. Adding a version closes the one before it the day before the new one starts.</div>
<div class="row"><div class="col-md-7">
<h4>History</h4>
<div class="timeline">
{foreach $contracts as $c}<div class="v {$c.status}">
<strong>{$c.effective_from}</strong> → {if $c.effective_to}{$c.effective_to}{else}<em>in force</em>{/if}
<span class="label label-{if $c.status=='active'}success{elseif $c.status=='superseded'}default{else}warning{/if}">{$c.status}</span>
<span class="label label-info">{$c.reason|replace:'_':' '}</span>
<div>{$c.contract_no} &middot; {$c.type|replace:'_':' '} &middot; {$c.position_title} &middot; {$c.grade_code}
&middot; <strong>{displayPrice price=$c.pay_rate}</strong> {$c.pay_basis|replace:'_':' '}{if $c.currency!='NGN'} {$c.currency}{/if}
{if $c.night_shift} &middot; <span class="label label-default">nights</span>{/if}
{if $c.end_date} &middot; ends {$c.end_date}{/if}</div>
{if $c.note}<div class="muted">{$c.note}</div>{/if}
{if $c@first && $contracts|count>1}<form method="post" class="inline"><input type="hidden" name="id_contract" value="{$c.id_pulse_hr_contract}"><button name="removeContract" class="btn btn-xs btn-link" data-hr-confirm="Remove the newest contract version and reopen the one before it?">remove this version</button></form>{/if}
</div>{foreachelse}<p class="muted">No contract on file. Add the first version on the right.</p>{/foreach}
</div>
</div>
<div class="col-md-5"><h4>New version</h4>
<form method="post"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<label>Reason</label><select name="reason" class="form-control">{foreach ['hire'=>'First contract','confirmation'=>'Confirmation','promotion'=>'Promotion','salary_review'=>'Salary review','transfer'=>'Transfer','renewal'=>'Renewal','demotion'=>'Demotion','correction'=>'Correction'] as $k=>$v}<option value="{$k}">{$v}</option>{/foreach}</select>
<label>Effective from</label><input type="date" name="effective_from" class="form-control" value="{$smarty.now|date_format:'%Y-%m-01'}" required>
<label>Type</label><select name="type" class="form-control">{foreach ['permanent','fixed_term','contract','casual','service','intern'] as $t}<option value="{$t}"{if $contract_now && $contract_now.type==$t} selected{/if}>{$t|replace:'_':' '}</option>{/foreach}</select>
<label>Position</label><select name="id_pulse_hr_position" class="form-control"><option value="">—</option>{foreach $positions as $p}<option value="{$p.id_pulse_hr_position}"{if $contract_now && $contract_now.id_pulse_hr_position==$p.id_pulse_hr_position} selected{/if}>{$p.title} ({$p.dept_code})</option>{/foreach}</select>
<label>Department</label><select name="id_pulse_hr_department" class="form-control">{foreach $departments as $d}<option value="{$d.id_pulse_hr_department}"{if $e.id_pulse_hr_department==$d.id_pulse_hr_department} selected{/if}>{$d.name}</option>{/foreach}</select>
<label>Section</label><select name="id_pulse_hr_section" class="form-control"><option value="">—</option>{foreach $sections as $s}<option value="{$s.id_pulse_hr_section}"{if $e.id_pulse_hr_section==$s.id_pulse_hr_section} selected{/if}>{$s.dept_code} / {$s.name}</option>{/foreach}</select>
<label>Grade</label><select name="id_pulse_hr_grade" class="form-control"><option value="">—</option>{foreach $grades as $g}<option value="{$g.id_pulse_hr_grade}"{if $e.id_pulse_hr_grade==$g.id_pulse_hr_grade} selected{/if}>{$g.code} {$g.name} ({displayPrice price=$g.salary_min}–{displayPrice price=$g.salary_max})</option>{/foreach}</select>
<label>Reports to</label><select name="id_manager" class="form-control"><option value="">—</option>{foreach $managers as $m}{if $m.id_pulse_hr_employee!=$e.id_pulse_hr_employee}<option value="{$m.id_pulse_hr_employee}"{if $e.id_manager==$m.id_pulse_hr_employee} selected{/if}>{$m.full_name}</option>{/if}{/foreach}</select>
<div class="row"><div class="col-xs-6"><label>Pay basis</label><select name="pay_basis" class="form-control">{foreach ['monthly','daily','hourly','per_shift'] as $b}<option value="{$b}"{if $contract_now && $contract_now.pay_basis==$b} selected{/if}>{$b|replace:'_':' '}</option>{/foreach}</select></div>
<div class="col-xs-6"><label>Rate (₦)</label><input name="pay_rate" type="number" step="0.01" class="form-control" value="{if $contract_now}{$contract_now.pay_rate}{/if}"></div></div>
<div class="row"><div class="col-xs-6"><label>Hours / week</label><input name="hours_per_week" type="number" step="0.5" class="form-control" value="{if $contract_now}{$contract_now.hours_per_week}{else}48{/if}"></div>
<div class="col-xs-6"><label>Days / week</label><input name="days_per_week" type="number" step="0.5" class="form-control" value="{if $contract_now}{$contract_now.days_per_week}{else}6{/if}"></div></div>
<div class="row"><div class="col-xs-6"><label>Notice (days)</label><input name="notice_days" type="number" class="form-control" value="{if $contract_now}{$contract_now.notice_days}{else}30{/if}"></div>
<div class="col-xs-6"><label>Probation (months)</label><input name="probation_months" type="number" class="form-control" value="0"></div></div>
<label>Contract ends</label><input type="date" name="end_date" class="form-control" value="{if $contract_now}{$contract_now.end_date}{/if}">
<label>Working pattern</label><input name="working_pattern" class="form-control" placeholder="6 on 1 off, early/late rotation" value="{if $contract_now}{$contract_now.working_pattern|escape:'html':'UTF-8'}{/if}">
<label><input type="checkbox" name="night_shift" value="1"{if $contract_now && $contract_now.night_shift} checked{/if}> works nights</label>
<label>Note</label><input name="note" class="form-control">
<button name="saveContract" class="btn btn-primary" style="margin-top:8px">Add contract version</button>
</form></div></div></div>

<div class="tab-pane" id="t-docs">
<table class="table table-condensed"><thead><tr><th>Document</th><th>Number</th><th>Issued</th><th>Expires</th><th>Status</th><th>Verified</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $documents as $d}<tr class="{if $d.status=='expired'}danger{elseif $d.status=='expiring'}warning{/if}">
<td>{$d.name} <span class="muted">{$d.type|replace:'_':' '}</span></td><td>{$d.number}</td><td>{$d.issued_on}</td><td>{$d.expires_on}</td>
<td><span class="label label-{if $d.status=='valid'}success{elseif $d.status=='expired'}danger{elseif $d.status=='revoked'}default{else}warning{/if}">{$d.status}</span></td>
<td>{if $d.verified}<i class="icon-check"></i>{/if}</td>
<td class="hr-actions"><form method="post" class="inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}"><input type="hidden" name="id_document" value="{$d.id_pulse_hr_document}"><button name="deleteDocument" class="btn btn-xs btn-link" data-hr-confirm="Delete this document record?">delete</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">Nothing on file. A member of staff with no medical or food handler certificate should not be on the floor.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<select name="doc_type" class="form-control">{foreach $doc_types as $k=>$v}<option value="{$k}">{$v}</option>{/foreach}</select>
<input name="doc_name" class="form-control" placeholder="Name on the document">
<input name="doc_number" class="form-control" placeholder="Number" style="width:130px">
<input name="doc_issuer" class="form-control" placeholder="Issued by" style="width:150px">
<input type="date" name="issued_on" class="form-control"> <input type="date" name="expires_on" class="form-control">
<input name="remind_days" type="number" class="form-control" placeholder="remind d" style="width:90px" value="30">
<label class="checkbox-inline"><input type="checkbox" name="verified" value="1"> seen the original</label>
<button name="saveDocument" class="btn btn-default">Add document</button></form></div>

<div class="tab-pane" id="t-leave">
<div class="row"><div class="col-md-6"><h4>Balances {$year}</h4>
<table class="table table-condensed"><thead><tr><th>Type</th><th class="text-right">Entitled</th><th class="text-right">Carried</th><th class="text-right">Accrued</th><th class="text-right">Taken</th><th class="text-right">Pending</th><th class="text-right">Available</th></tr></thead><tbody>
{foreach $balances as $b}<tr><td><span class="swatch" style="background:{$b.colour}"></span>{$b.name}</td><td class="text-right">{$b.entitlement}</td><td class="text-right">{$b.carried}</td><td class="text-right">{$b.accrued}</td><td class="text-right">{$b.taken}</td><td class="text-right">{$b.pending}</td><td class="text-right"><strong>{$b.available}</strong></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}"><input type="hidden" name="year" value="{$year}">
<select name="id_leave_type" class="form-control">{foreach $leave_types as $t}<option value="{$t.id_pulse_hr_leave_type}">{$t.name}</option>{/foreach}</select>
<input name="adj_days" type="number" step="0.5" class="form-control" placeholder="± days" style="width:100px">
<input name="adj_reason" class="form-control" placeholder="Why" required>
<button name="adjustLeave" class="btn btn-default">Adjust</button></form></div>
<div class="col-md-6"><h4>Requests</h4>
<table class="table table-condensed"><tbody>
{foreach $leave as $l}<tr><td>{$l.type_name}</td><td>{$l.date_from} – {$l.date_to}</td><td>{$l.days} d</td><td><span class="label label-{if $l.status=='approved' || $l.status=='taken'}success{elseif $l.status=='pending'}warning{else}default{/if}">{$l.status}</span></td><td class="muted">{$l.source}</td></tr>
{foreachelse}<tr><td><em class="muted">No leave taken.</em></td></tr>{/foreach}
</tbody></table></div></div></div>

<div class="tab-pane" id="t-roster">
<div class="row"><div class="col-md-5"><h4>Shifts</h4>
<table class="table table-condensed"><tbody>
{foreach $roster as $r}<tr><td>{$r.roster_date|date_format:"%a %d %b"}</td><td>{if $r.is_off}<em>off</em>{else}{$r.shift_name}{/if}</td><td>{if !$r.is_off}{$r.start_time|truncate:5:''}–{$r.end_time|truncate:5:''}{/if}</td><td><span class="label label-{if $r.status=='published'}success{else}default{/if}">{$r.status}</span></td></tr>
{foreachelse}<tr><td><em class="muted">Not rostered.</em></td></tr>{/foreach}
</tbody></table></div>
<div class="col-md-7"><h4>Clockings (last 30 days)</h4>
<table class="table table-condensed"><thead><tr><th>When</th><th>Dir</th><th>Source</th><th>Location</th><th>Status</th></tr></thead><tbody>
{foreach $punches as $p}<tr class="{if $p.status=='rejected'}danger{elseif $p.status=='flagged'}warning{/if}">
<td>{$p.punched_at|date_format:"%d/%m %H:%M"}</td><td>{$p.direction}</td><td>{$p.source}</td>
<td class="flag">{if $p.distance_m}{$p.distance_m|string_format:"%d"} m{if $p.accuracy_m} ±{$p.accuracy_m|string_format:"%d"}{/if}{if $p.lat} <a href="https://www.google.com/maps?q={$p.lat},{$p.lng}" target="_blank" rel="noreferrer">map</a>{/if}{else}<span class="muted">—</span>{/if}</td>
<td>{$p.status}{if $p.flag_reason} <span class="muted">{$p.flag_reason}</span>{/if}</td></tr>
{foreachelse}<tr><td colspan="5"><em class="muted">No mobile clockings. Biometric punches live in Pulse Time.</em></td></tr>{/foreach}
</tbody></table></div></div></div>

<div class="tab-pane" id="t-life">
{foreach $checklists as $c}<div class="panel panel-default"><div class="panel-heading">{$c.type|ucfirst} — opened {$c.opened_on}, due {$c.due_on} <span class="label label-{if $c.status=='completed'}success{else}warning{/if}">{$c.status}</span>
<a class="btn btn-xs btn-default pull-right" href="{$lifecycle_url}&id_checklist={$c.id_pulse_hr_checklist}&token={$smarty.get.token|escape:'html':'UTF-8'}">Open</a></div>
<div class="panel-body"><ul class="list-unstyled">{foreach $c.tasks as $t}<li>{if $t.status=='done'}<i class="icon-check"></i>{elseif $t.status=='na'}<i class="icon-minus"></i>{elseif $t.status=='failed'}<i class="icon-warning-sign text-danger"></i>{else}<i class="icon-time"></i>{/if} {$t.title} <span class="muted">{$t.owner_department} · due {$t.due_on}</span>{if $t.note} — <em>{$t.note}</em>{/if}</li>{/foreach}</ul></div></div>
{foreachelse}<p class="muted">No checklist has been opened for this person.</p>{/foreach}
<form method="post" class="form-inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
{if $e.status!='exited'}
<h4>Confirm off probation</h4>
<input type="date" name="confirm_date" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}"> <input name="confirm_rate" type="number" step="0.01" class="form-control" placeholder="New rate (optional)">
<button name="confirmStaff" class="btn btn-success">Confirm</button>
<h4 style="margin-top:16px">Exit</h4>
<input type="date" name="exit_date" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}">
<select name="exit_type" class="form-control">{foreach ['resignation','termination','end_of_contract','retirement','redundancy','abscondment','death'] as $x}<option value="{$x}">{$x|replace:'_':' '}</option>{/foreach}</select>
<input name="exit_reason" class="form-control" placeholder="Reason" style="width:220px">
<label class="checkbox-inline"><input type="checkbox" name="rehire_eligible" value="1" checked> would re-hire</label>
<button name="exitStaff" class="btn btn-danger" data-hr-confirm="Exit this employee? Their contract closes, pending leave is cancelled, future shifts are dropped, portal access ends and the clearance checklist opens.">Record exit</button>
{else}<p class="alert alert-warning">Left on {$e.exit_date} — {$e.exit_reason}. {if $e.rehire_eligible}Eligible for re-hire.{else}<strong>Not</strong> eligible for re-hire.{/if}</p>{/if}
</form></div>

<div class="tab-pane" id="t-perf">
<h4>Discipline and commendations</h4>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Type</th><th>Subject</th><th>Status</th><th>Acknowledged</th><th>Expires</th></tr></thead><tbody>
{foreach $cases as $c}<tr class="{if $c.type=='final_warning' || $c.type=='suspension'}danger{elseif $c.type=='commendation'}success{/if}">
<td>{$c.issued_on}</td><td>{$c.type|replace:'_':' '}</td><td>{$c.subject}</td><td>{$c.status}</td><td>{if $c.acknowledged_at}{$c.acknowledged_at|date_format:"%d/%m %H:%M"} <span class="muted flag">{$c.ack_ip}</span>{else}<span class="label label-warning">not yet</span>{/if}</td><td>{$c.expires_on}</td></tr>
{foreachelse}<tr><td colspan="6"><em class="muted">Clean record.</em></td></tr>{/foreach}
</tbody></table>
<h4>Appraisals</h4>
<table class="table table-condensed"><tbody>{foreach $appraisals as $a}<tr><td>{$a.cycle_name}</td><td>{$a.period_from} – {$a.period_to}</td><td>rating <strong>{$a.overall_rating}</strong></td><td>{$a.status}</td><td>{$a.recommendation|replace:'_':' '}</td></tr>{foreachelse}<tr><td><em class="muted">None.</em></td></tr>{/foreach}</tbody></table>
<h4>Training</h4>
<table class="table table-condensed"><tbody>{foreach $training as $t}<tr class="{if $t.expires_on && $t.expires_on < $smarty.now|date_format:'%Y-%m-%d'}danger{/if}"><td>{$t.course}</td><td>{$t.provider}</td><td>{$t.completed_on}</td><td>{if $t.expires_on}expires {$t.expires_on}{/if}</td><td>{displayPrice price=$t.cost}</td></tr>{foreachelse}<tr><td><em class="muted">None recorded.</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<input name="course" class="form-control" placeholder="Course" required> <input name="provider" class="form-control" placeholder="Provider">
<select name="training_type" class="form-control">{foreach ['induction','safety','food_hygiene','fire','first_aid','service','technical','compliance','other'] as $tt}<option value="{$tt}">{$tt|replace:'_':' '}</option>{/foreach}</select>
<input type="date" name="completed_on" class="form-control"> <input type="date" name="training_expires" class="form-control">
<input name="cost" type="number" step="0.01" class="form-control" placeholder="₦" style="width:110px"> <input name="certificate_no" class="form-control" placeholder="Cert no" style="width:120px">
<button name="addTraining" class="btn btn-default">Add training</button></form></div>

<div class="tab-pane" id="t-access">
<div class="row"><div class="col-md-6"><h4>Links to the rest of the suite</h4>
<form method="post"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<label>Back-office user (PrestaShop employee)</label>
<select name="id_employee" class="form-control"><option value="">— not a system user —</option>{foreach $bo_users as $u}<option value="{$u.id_employee}"{if $e.id_employee==$u.id_employee} selected{/if}>{$u.firstname} {$u.lastname} ({$u.email})</option>{/foreach}</select>
<p class="help-block">Key cards and POS logins hang off this link. Most hotel staff have none — leave it blank.</p>
<button name="saveEmployee" class="btn btn-default">Save link</button></form>
<p style="margin-top:10px">POS: {if !$pos}<span class="muted">Pulse POS is not installed.</span>{elseif $pos_staff}<span class="label label-{if $pos_staff.active}success{else}default{/if}">{$pos_staff.role}{if !$pos_staff.active} (disabled){/if}</span>{else}<span class="muted">no POS login</span>{/if}</p>
<p>Key cards: {if !$kc}<span class="muted">Pulse Key Card is not installed.</span>{elseif !$e.id_employee}<span class="muted">needs a back-office user first</span>{else}issued and revoked from the onboarding / exit checklist{/if}</p>
<p>Payslips: {if $pr}<span class="label label-success">Pulse Payroll installed</span> — visible on the portal behind a PIN re-entry{else}<span class="muted">Pulse Payroll is not installed, so the portal tells this person payslips are unavailable</span>{/if}</p>
</div>
<div class="col-md-6"><h4>Staff portal</h4>
<form method="post"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<p>PIN: {if $e.pin_hash}<span class="label label-success">set {$e.pin_set_at|date_format:"%d/%m/%Y"}</span>{else}<span class="label label-default">not set — this person cannot sign in</span>{/if}
{if $e.ess_locked_until} <span class="label label-danger">locked until {$e.ess_locked_until}</span>{/if}</p>
<label>Set a new PIN</label><input name="pin" class="form-control" inputmode="numeric" placeholder="digits only">
<button name="setPin" class="btn btn-primary">Set PIN</button>
<button name="clearPin" class="btn btn-default" data-hr-confirm="Clear the PIN and sign this person out everywhere?">Clear PIN &amp; end sessions</button>
</form>
<hr>
<h4>Status</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_employee_hr" value="{$e.id_pulse_hr_employee}">
<select name="new_status" class="form-control">{foreach ['probation','active','suspended','on_leave'] as $s}<option value="{$s}"{if $e.status==$s} selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select>
<input name="status_note" class="form-control" placeholder="Note">
<button name="setStatus" class="btn btn-default">Change status</button></form>
{if $reports_to}<h4 style="margin-top:14px">Direct reports</h4><ul>{foreach $reports_to as $r}<li><a href="{$self_url}&id_employee_hr={$r.id_pulse_hr_employee}">{$r.full_name}</a> — {$r.position_title}</li>{/foreach}</ul>{/if}
</div></div></div>

</div></div></div>
