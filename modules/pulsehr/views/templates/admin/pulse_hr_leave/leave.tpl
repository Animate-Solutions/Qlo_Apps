<div class="pulse-hr"><div class="panel"><h3><i class="icon-calendar"></i> Leave — {$business_date} <small class="muted">occupancy {$occupancy}%</small></h3>
<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#l-queue">To approve ({$pending|count})</a></li>
<li><a data-toggle="tab" href="#l-cal">Calendar</a></li><li><a data-toggle="tab" href="#l-new">Book leave</a></li>
<li><a data-toggle="tab" href="#l-types">Types &amp; entitlements</a></li><li><a data-toggle="tab" href="#l-black">Blackouts</a></li>
<li><a data-toggle="tab" href="#l-liab">Liability</a></li><li><a data-toggle="tab" href="#l-hist">History</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="l-queue">
{if $on_leave}<p><strong>Off today:</strong> {foreach $on_leave as $o}{$o.employee_name} <span class="muted">({$o.type_code}, {$o.dept_name})</span>{if !$o@last} · {/if}{/foreach}</p>{/if}
<table class="table table-condensed"><thead><tr><th>Asked</th><th>Who</th><th>Type</th><th>Dates</th><th class="text-right">Days</th><th>Relief</th><th>Reason</th><th>Step</th><th class="hr-actions">Decision</th></tr></thead><tbody>
{foreach $pending as $p}<tr>
<td>{$p.date_add|date_format:"%d/%m"}</td><td><a href="{$employee_url}&id_employee_hr={$p.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$p.employee_name}</a><div class="muted">{$p.dept_name}</div></td>
<td><span class="swatch" style="background:{$p.colour}"></span>{$p.type_name}</td><td>{$p.date_from} → {$p.date_to}</td><td class="text-right">{$p.days}</td>
<td>{$p.id_relief|intval}</td><td>{$p.reason|escape:'html':'UTF-8'}</td><td>level {$p.current_level|intval}</td>
<td class="hr-actions"><form method="post" class="form-inline"><input type="hidden" name="id_request" value="{$p.id_pulse_hr_leave_request}"><input name="comment" class="input-sm" placeholder="comment" style="width:130px">
<button name="decide" class="btn btn-xs btn-success" formaction="{$self_url}&decision=approved">Approve</button>
<button name="decide" class="btn btn-xs btn-danger" formaction="{$self_url}&decision=rejected">Reject</button>
<button name="cancelRequest" class="btn btn-xs btn-link" data-hr-confirm="Cancel this request?">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em class="muted">Nothing waiting for a decision.</em></td></tr>{/foreach}
</tbody></table>
<p class="muted">Approving moves the days from pending to taken, drops the person off the roster for those dates and raises <code>actionPulseHrLeaveApproved</code>.</p></div>

<div class="tab-pane" id="l-cal">
<form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulseHrLeave"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control">
<select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.code}"{if $department==$d.code} selected{/if}>{$d.name}</option>{/foreach}</select>
<button class="btn btn-default">Show</button></form>
<div style="overflow-x:auto"><table class="table table-condensed roster" style="margin-top:8px"><thead><tr><th style="width:180px">Who</th>{foreach $cal_dates as $d}<th>{$d|date_format:"%d"}<br><span class="muted">{$d|date_format:"%a"}</span></th>{/foreach}</tr></thead><tbody>
{foreach $calendar as $idEmp => $row}<tr><td class="name">{$row.employee_name}<div class="muted">{$row.dept_name}</div></td>
{foreach $cal_dates as $d}<td class="{if isset($row.days[$d])}leave{/if}">{if isset($row.days[$d])}<span title="{$row.days[$d].status}">{$row.days[$d].type}</span>{/if}</td>{/foreach}</tr>
{foreachelse}<tr><td colspan="{($cal_dates|@count)+1}"><em class="muted">Nobody is booked off in that window.</em></td></tr>{/foreach}
</tbody></table></div></div>

<div class="tab-pane" id="l-new"><form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Employee</label><div class="col-sm-8"><select name="id_employee_hr" class="form-control" required>{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name} ({$s.staff_no}) — {$s.dept_name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Type</label><div class="col-sm-8"><select name="id_leave_type" class="form-control">{foreach $types as $t}{if $t.active}<option value="{$t.id_pulse_hr_leave_type}">{$t.name}{if !$t.paid} (unpaid){/if}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">From</label><div class="col-sm-8"><input type="date" name="date_from" id="hr-leave-from" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4">To</label><div class="col-sm-8"><input type="date" name="date_to" id="hr-leave-to" class="form-control" required> <span class="help-block" id="hr-leave-days"></span></div></div>
<div class="form-group"><label class="col-sm-4">Half day</label><div class="col-sm-8"><select name="half_day" class="form-control"><option value="none">Full days</option><option value="start">Half on the first day</option><option value="end">Half on the last day</option></select></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Relief</label><div class="col-sm-8"><select name="id_relief" class="form-control"><option value="">—</option>{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Phone while away</label><div class="col-sm-8"><input name="contact_phone" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Address while away</label><div class="col-sm-8"><input name="address_on_leave" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Reason</label><div class="col-sm-8"><input name="reason" class="form-control"></div></div>
<button name="newRequest" class="btn btn-primary">Raise request</button>
<p class="help-block">Balance, service, consecutive-day caps, overlaps, blackouts and departmental coverage are all checked when you press this. Warnings appear but do not block a decision.</p>
</div></div></form></div>

<div class="tab-pane" id="l-types">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Type</th><th>Paid</th><th>Accrual</th><th class="text-right">Days/yr</th><th class="text-right">Carry cap</th><th class="text-right">Max run</th><th class="text-right">Min service</th><th>Gender</th><th>Doc</th><th>Encash</th><th>Active</th></tr></thead><tbody>
{foreach $types as $t}<tr><td><span class="swatch" style="background:{$t.colour}"></span><code>{$t.code}</code></td><td>{$t.name}</td><td>{if $t.paid}yes{else}<strong>no</strong>{/if}</td><td>{$t.accrual}</td>
<td class="text-right">{$t.days_per_year}</td><td class="text-right">{$t.carry_over_cap}</td><td class="text-right">{$t.max_consecutive}</td><td class="text-right">{$t.min_service_months} m</td>
<td>{$t.gender}</td><td>{if $t.requires_document}yes{/if}</td><td>{if $t.encashable}yes{/if}</td><td>{if $t.active}yes{else}no{/if}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_type">
<input name="code" class="form-control" placeholder="CODE" style="width:90px" required> <input name="name" class="form-control" placeholder="Leave type" required>
<select name="accrual" class="form-control"><option value="none">no accrual</option><option value="monthly">monthly</option><option value="annual">annual</option><option value="on_event">on event</option></select>
<input name="days_per_year" type="number" step="0.5" class="form-control" placeholder="days/yr" style="width:100px">
<input name="carry_over_cap" type="number" step="0.5" class="form-control" placeholder="carry cap" style="width:100px">
<input name="max_consecutive" type="number" class="form-control" placeholder="max run" style="width:90px">
<input name="min_service_months" type="number" class="form-control" placeholder="min mths" style="width:100px">
<select name="gender" class="form-control"><option value="any">any</option><option value="m">men</option><option value="f">women</option></select>
<label class="checkbox-inline"><input type="checkbox" name="paid" value="1" checked> paid</label>
<label class="checkbox-inline"><input type="checkbox" name="working_days_only" value="1" checked> working days only</label>
<label class="checkbox-inline"><input type="checkbox" name="requires_document" value="1"> needs a document</label>
<label class="checkbox-inline"><input type="checkbox" name="encashable" value="1"> encashable</label>
<button name="saveType" class="btn btn-default">Save type</button></form>
<hr><h4>Entitlement by grade <small class="muted">— overrides the type default</small></h4>
<table class="table table-condensed"><tbody>{foreach $entitlements as $en}<tr><td>{$en.type_name}</td><td>{$en.grade_code} {$en.grade_name}</td><td>{$en.days} days</td></tr>{foreachelse}<tr><td><em class="muted">None — every grade uses the type default, except annual leave which follows the grade's own figure.</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline">
<select name="ent_type" class="form-control">{foreach $types as $t}<option value="{$t.id_pulse_hr_leave_type}">{$t.name}</option>{/foreach}</select>
<select name="ent_grade" class="form-control">{foreach $grades as $g}<option value="{$g.id_pulse_hr_grade}">{$g.code} {$g.name}</option>{/foreach}</select>
<input name="ent_days" type="number" step="0.5" class="form-control" placeholder="days" style="width:100px">
<button name="saveEntitlement" class="btn btn-default">Save entitlement</button></form>
<hr><h4>Encash</h4>
<form method="post" class="form-inline"><select name="id_employee_hr" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name}</option>{/foreach}</select>
<select name="id_leave_type" class="form-control">{foreach $types as $t}{if $t.encashable}<option value="{$t.id_pulse_hr_leave_type}">{$t.name}</option>{/if}{/foreach}</select>
<input name="enc_days" type="number" step="0.5" class="form-control" placeholder="days" style="width:100px">
<button name="encash" class="btn btn-default">Encash</button></form>
<hr><form method="post" class="form-inline"><input name="co_year" type="number" class="form-control" style="width:110px" placeholder="year to carry from">
<button name="carryOver" class="btn btn-default" data-hr-confirm="Carry unused leave forward from that year, capped per type?">Run year-end carry over</button></form></div>

<div class="tab-pane" id="l-black">
<p class="muted">A blackout is how you stop four housekeepers booking the same week of a full house. It bites only when forecast occupancy is at or above the threshold and the department already has its allowance off.</p>
<table class="table table-condensed"><thead><tr><th>Name</th><th>Department</th><th>From</th><th>To</th><th class="text-right">Max off</th><th class="text-right">From occupancy</th><th>Reason</th><th>Active</th></tr></thead><tbody>
{foreach $blackouts as $b}<tr><td>{$b.name}</td><td>{$b.department|default:'all'}</td><td>{$b.date_from}</td><td>{$b.date_to}</td><td class="text-right">{$b.max_off}</td><td class="text-right">{$b.min_occupancy_pct}%</td><td>{$b.reason}</td><td>{if $b.active}yes{else}no{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em class="muted">None.</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_blackout">
<input name="bname" class="form-control" placeholder="Christmas week" required>
<select name="bdept" class="form-control"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.code}">{$d.name}</option>{/foreach}</select>
<input type="date" name="bfrom" class="form-control" required> <input type="date" name="bto" class="form-control" required>
<input name="max_off" type="number" class="form-control" placeholder="max off" style="width:100px" value="0">
<input name="min_occupancy_pct" type="number" step="0.1" class="form-control" placeholder="occ %" style="width:100px" value="0">
<input name="breason" class="form-control" placeholder="Reason">
<button name="saveBlackout" class="btn btn-default">Save blackout</button></form></div>

<div class="tab-pane" id="l-liab">
<h4>Leave liability {$liability.year} — <strong>{displayPrice price=$liability.total}</strong></h4>
<p class="muted">Days of annual leave still owed, valued at the daily rate from the contract in force today. This is the number finance provides for.</p>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Who</th><th>Department</th><th class="text-right">Days owed</th><th class="text-right">Daily rate</th><th class="text-right">Value</th></tr></thead><tbody>
{foreach $liability.rows as $r}<tr><td>{$r.staff_no}</td><td>{$r.employee_name}</td><td>{$r.dept_name}</td><td class="text-right">{$r.days}</td><td class="text-right">{displayPrice price=$r.daily_rate}</td><td class="text-right">{displayPrice price=$r.value}</td></tr>
{foreachelse}<tr><td colspan="6"><em class="muted">Nothing owed.</em></td></tr>{/foreach}
</tbody><tfoot><tr><th colspan="5" class="text-right">Total</th><th class="text-right">{displayPrice price=$liability.total}</th></tr></tfoot></table></div>

<div class="tab-pane" id="l-hist">
<table class="table table-condensed"><thead><tr><th>No</th><th>Who</th><th>Type</th><th>Dates</th><th class="text-right">Days</th><th>Status</th><th>Decided</th><th>Note</th></tr></thead><tbody>
{foreach $recent as $r}<tr><td>{$r.request_no}</td><td>{$r.employee_name}</td><td>{$r.type_name}</td><td>{$r.date_from} → {$r.date_to}</td><td class="text-right">{$r.days}</td>
<td><span class="label label-{if $r.status=='approved' || $r.status=='taken'}success{elseif $r.status=='rejected'}danger{else}default{/if}">{$r.status}</span></td><td>{$r.decided_at|date_format:"%d/%m"}</td><td>{$r.decision_note}</td></tr>
{foreachelse}<tr><td colspan="8"><em class="muted">Nothing yet.</em></td></tr>{/foreach}
</tbody></table></div>

</div></div></div>
