<div class="pulse-ta"><div class="panel"><h3><i class="icon-calendar"></i> Overtime &amp; Roster</h3>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-o-roster">Roster grid</a></li>
  <li><a data-toggle="tab" href="#ta-o-shifts">Shifts ({$shifts|count})</a></li>
  <li><a data-toggle="tab" href="#ta-o-rules">Overtime rules ({$rules|count})</a></li>
  <li><a data-toggle="tab" href="#ta-o-hol">Public holidays</a></li>
  <li><a data-toggle="tab" href="#ta-o-reg">Overtime register ({$overtime|count})</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-o-roster">
<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaOvertime"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  Week from <input type="date" name="week_from" value="{$week_from}" class="form-control">
  <select name="days" class="form-control"><option value="7" {if $days==7}selected{/if}>7 days</option><option value="14" {if $days==14}selected{/if}>14 days</option></select>
  <select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $k => $v}<option value="{$k}" {if $department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <button class="btn btn-default">Show</button>
</form>
<form method="post"><input type="hidden" name="week_from" value="{$week_from}">
<div class="table-responsive"><table class="table table-condensed ta-roster"><thead><tr><th>Staff</th>
{foreach $week.dates as $d}<th class="{if isset($holidays[$d])}ta-hol{/if}">{$d|date_format:"%a %d/%m"}{if isset($holidays[$d])}<br><small title="{$holidays[$d].note|escape:'html':'UTF-8'}">{$holidays[$d].name|escape:'html':'UTF-8'}{if !$holidays[$d].confirmed} *{/if}</small>{/if}</th>{/foreach}
</tr></thead><tbody>
{foreach $week.rows as $r}
<tr><td><small><strong>{$r.staff_no|escape:'html':'UTF-8'}</strong> {$r.lastname|escape:'html':'UTF-8'}, {$r.firstname|escape:'html':'UTF-8'}<br><span class="text-muted">{$r.department|escape:'html':'UTF-8'}</span></small></td>
{foreach $week.dates as $d}
<td><select name="cell[{$r.id_pulse_ta_staff}][{$d}]" class="input-sm ta-cell">
  <option value="">—</option>
  {foreach $shifts as $sh}<option value="{$sh.id_pulse_ta_shift}" {if $r.cells[$d] && $r.cells[$d].code==$sh.code}selected{/if}>{$sh.code|escape:'html':'UTF-8'}</option>{/foreach}
  <option value="REST" {if $r.cells[$d] && $r.cells[$d].day_type=='rest'}selected{/if}>rest</option>
  <option value="LEAVE" {if $r.cells[$d] && $r.cells[$d].day_type=='leave'}selected{/if}>leave</option>
  <option value="CLEAR">clear</option>
</select></td>
{/foreach}
</tr>
{foreachelse}<tr><td colspan="{$week.dates|count+1}"><em>No staff. Add them under Enrolment.</em></td></tr>{/foreach}
</tbody></table></div>
<button name="setRoster" class="btn btn-primary">Save the grid</button>
<button name="importHrRoster" class="btn btn-default">Mirror the roster from Pulse HR</button>
<span class="text-muted">A night shift rostered on a date owns the punches up to its end the next morning — that is what makes 22:00–06:00 pay correctly.</span>
</form>

<h4 style="margin-top:20px">Coverage</h4>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Shift</th><th>Department</th><th>Heads</th></tr></thead><tbody>
{foreach $coverage as $c}<tr class="{if $c.is_night}info{/if}"><td>{$c.roster_date}</td><td>{$c.code|escape:'html':'UTF-8'} {$c.name|escape:'html':'UTF-8'}</td><td>{$c.department|escape:'html':'UTF-8'}</td><td>{$c.heads}</td></tr>
{foreachelse}<tr><td colspan="4"><em>Nothing rostered in this range.</em></td></tr>{/foreach}
</tbody></table>

<h4>Apply a pattern</h4>
<p class="text-muted">A hotel rota is a repeating cycle, not a date list. Enter shift codes separated by commas — <code>EARLY,EARLY,LATE,LATE,NIGHT,NIGHT,REST,REST</code> — and it is dealt out day by day across the range.</p>
<form method="post" class="form-inline">
  <select name="pstaff[]" multiple size="6" class="form-control" style="min-width:260px">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input name="pattern" class="form-control" style="width:340px" value="EARLY,EARLY,LATE,LATE,NIGHT,NIGHT,REST,REST">
  <input type="date" name="pat_from" class="form-control" value="{$week_from}">
  <input type="date" name="pat_to" class="form-control" value="{$week_from|date_format:'%Y-%m-%d'}">
  <label class="checkbox-inline"><input type="checkbox" name="publish" value="1" checked> publish</label>
  <button name="applyPattern" class="btn btn-default">Apply</button>
</form>
</div>

<div class="tab-pane" id="ta-o-shifts">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Dept</th><th>Hours</th><th>Midnight</th><th>Night</th><th>Break</th><th>Grace in/out</th><th>Window ±</th><th>Min/max</th><th>Paid min</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $shifts as $sh}
<tr class="{if $sh.crosses_midnight}info{/if}">
  <td><strong>{$sh.code|escape:'html':'UTF-8'}</strong></td><td>{$sh.name|escape:'html':'UTF-8'}</td><td>{$sh.department|escape:'html':'UTF-8'}</td>
  <td>{$sh.start_time|truncate:5:''}–{$sh.end_time|truncate:5:''}</td>
  <td>{if $sh.crosses_midnight}<span class="label label-info">+1 day</span>{/if}</td>
  <td>{if $sh.is_night}✔{/if}</td>
  <td>{$sh.break_minutes}m {if $sh.break_paid}paid{/if}{if $sh.break_punched} punched{/if}</td>
  <td>{$sh.grace_in_min}/{$sh.grace_out_min}</td><td>{$sh.window_before_min}/{$sh.window_after_min}</td>
  <td>{$sh.min_shift_min}/{$sh.max_shift_min}</td><td>{$sh.paid_minutes}</td>
  <td>{if $sh.active}✔{/if}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_shift={$sh.id_pulse_ta_shift}">Edit</a></td>
</tr>
{/foreach}
</tbody></table>
<form method="post" class="form-horizontal" style="max-width:820px">
<input type="hidden" name="id_shift_save" value="{if $edit_shift}{$edit_shift.id_pulse_ta_shift}{else}0{/if}">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-5">Code / name</label><div class="col-sm-3"><input name="code" class="form-control" value="{if $edit_shift}{$edit_shift.code|escape:'html':'UTF-8'}{/if}"></div><div class="col-sm-4"><input name="sname" class="form-control" value="{if $edit_shift}{$edit_shift.name|escape:'html':'UTF-8'}{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Department</label><div class="col-sm-7"><select name="sdepartment" class="form-control"><option value="">Any</option>{foreach $departments as $k => $v}<option value="{$k}" {if $edit_shift && $edit_shift.department==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-5">Start / end</label><div class="col-sm-3"><input type="time" name="start_time" class="form-control" value="{if $edit_shift}{$edit_shift.start_time|truncate:5:''}{else}08:00{/if}"></div><div class="col-sm-4"><input type="time" name="end_time" class="form-control" value="{if $edit_shift}{$edit_shift.end_time|truncate:5:''}{else}17:00{/if}"></div></div>
<div class="form-group"><div class="col-sm-offset-5 col-sm-7">
  <label class="checkbox-inline"><input type="checkbox" name="crosses_midnight" value="1" {if $edit_shift && $edit_shift.crosses_midnight}checked{/if}> Ends the next day</label>
  <label class="checkbox-inline"><input type="checkbox" name="is_night" value="1" {if $edit_shift && $edit_shift.is_night}checked{/if}> Night shift</label></div></div>
<div class="form-group"><label class="col-sm-5">Break minutes</label><div class="col-sm-3"><input name="break_minutes" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.break_minutes}{else}30{/if}"></div>
  <div class="col-sm-4"><label class="checkbox-inline"><input type="checkbox" name="break_paid" value="1" {if $edit_shift && $edit_shift.break_paid}checked{/if}> paid</label>
  <label class="checkbox-inline"><input type="checkbox" name="break_punched" value="1" {if $edit_shift && $edit_shift.break_punched}checked{/if}> punched</label></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-5">Grace in / out (min)</label><div class="col-sm-3"><input name="grace_in_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.grace_in_min}{else}10{/if}"></div><div class="col-sm-4"><input name="grace_out_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.grace_out_min}{else}10{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Punch window before / after (min)</label><div class="col-sm-3"><input name="window_before_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.window_before_min}{else}120{/if}"></div><div class="col-sm-4"><input name="window_after_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.window_after_min}{else}180{/if}"></div>
  <span class="help-block col-sm-offset-5">How far either side of the shift a punch still belongs to it. This is what stops a night worker's 05:55 clock-out being read as an early arrival for the morning shift.</span></div>
<div class="form-group"><label class="col-sm-5">Min / max shift (min)</label><div class="col-sm-3"><input name="min_shift_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.min_shift_min}{else}240{/if}"></div><div class="col-sm-4"><input name="max_shift_min" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.max_shift_min}{else}900{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Nominal paid minutes</label><div class="col-sm-3"><input name="paid_minutes" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.paid_minutes}{else}480{/if}"></div>
  <div class="col-sm-4"><input name="colour" class="form-control" value="{if $edit_shift}{$edit_shift.colour|escape:'html':'UTF-8'}{else}#2e86c1{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Sort</label><div class="col-sm-3"><input name="sort" type="number" class="form-control" value="{if $edit_shift}{$edit_shift.sort}{else}0{/if}"></div>
  <div class="col-sm-4"><label class="checkbox-inline"><input type="checkbox" name="sactive" value="1" {if !$edit_shift || $edit_shift.active}checked{/if}> active</label></div></div>
</div></div>
<div class="col-sm-offset-2"><button name="saveShift" class="btn btn-primary">Save shift</button> {if $edit_shift}<a class="btn btn-default" href="{$self_url}">Cancel</a>{/if}</div>
</form>
</div>

<div class="tab-pane" id="ta-o-rules">
<p class="text-muted">A rate change is a data edit with an effective date, never a code release. Precedence: a public holiday beats a rest day, a rest day beats the daily and weekly thresholds, and no minute is ever paid twice.</p>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Scope</th><th>Dept</th><th>Threshold</th><th>×</th><th>Cap</th><th>Approval</th><th>From</th><th>To</th><th>Active</th></tr></thead><tbody>
{foreach $rules as $r}
<tr><td><strong>{$r.code|escape:'html':'UTF-8'}</strong></td><td>{$r.name|escape:'html':'UTF-8'}</td><td>{$r.scope}</td><td>{$r.department|escape:'html':'UTF-8'}</td>
<td>{$r.threshold_minutes} min</td><td>{$r.multiplier}</td><td>{if $r.cap_minutes}{$r.cap_minutes} min{else}—{/if}</td><td>{if $r.requires_approval}✔{/if}</td>
<td>{$r.effective_from}</td><td>{$r.effective_to}</td><td>{if $r.active}✔{/if}</td></tr>
{/foreach}
</tbody></table>
<form method="post" class="form-inline">
  <input type="hidden" name="id_rule" value="0">
  <input name="rcode" class="form-control" placeholder="CODE" style="width:110px">
  <input name="rname" class="form-control" placeholder="Name" style="width:220px">
  <select name="scope" class="form-control"><option value="daily">daily</option><option value="weekly">weekly</option><option value="rest_day">rest day</option><option value="holiday">holiday</option><option value="night">night</option></select>
  <select name="rdepartment" class="form-control"><option value="">All</option>{foreach $departments as $k => $v}<option value="{$k}">{$v}</option>{/foreach}</select>
  <input name="threshold_minutes" type="number" class="form-control" placeholder="threshold min" style="width:120px" value="480">
  <input name="multiplier" type="number" step="0.001" class="form-control" placeholder="×" style="width:80px" value="1.5">
  <input name="cap_minutes" type="number" class="form-control" placeholder="cap" style="width:80px" value="0">
  <input type="date" name="effective_from" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}">
  <input type="date" name="effective_to" class="form-control">
  <label class="checkbox-inline"><input type="checkbox" name="ractive" value="1" checked> active</label>
  <button name="saveRule" class="btn btn-default">Add rule</button>
</form>
</div>

<div class="tab-pane" id="ta-o-hol">
<p class="text-muted">Nigerian public holidays are seeded for 2026 and 2027. Dates marked <strong>*</strong> depend on a moon sighting or a federal declaration — confirm them each year and tick "confirmed".</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Name</th><th>Country</th><th>Type</th><th>×</th><th>Confirmed</th><th>Note</th><th>Active</th></tr></thead><tbody>
{foreach $all_holidays as $h}
<tr class="{if !$h.confirmed}warning{/if}"><td>{$h.holiday_date}</td><td>{$h.name|escape:'html':'UTF-8'}</td><td>{$h.country}</td><td>{$h.type}</td><td>{$h.multiplier}</td>
<td>{if $h.confirmed}✔{else}<span class="label label-warning">confirm</span>{/if}</td><td><small>{$h.note|escape:'html':'UTF-8'}</small></td><td>{if $h.active}✔{/if}</td></tr>
{/foreach}
</tbody></table>
<form method="post" class="form-inline">
  <input type="hidden" name="id_holiday" value="0">
  <input type="date" name="holiday_date" class="form-control">
  <input name="hname" class="form-control" placeholder="Name" style="width:220px">
  <input name="country" class="form-control" value="NG" style="width:60px">
  <select name="htype" class="form-control"><option value="public">public</option><option value="religious">religious</option><option value="state">state</option><option value="company">company</option></select>
  <input name="hmultiplier" type="number" step="0.001" class="form-control" value="2" style="width:80px">
  <label class="checkbox-inline"><input type="checkbox" name="confirmed" value="1"> confirmed</label>
  <label class="checkbox-inline"><input type="checkbox" name="hactive" value="1" checked> active</label>
  <input name="hnote" class="form-control" placeholder="note" style="width:220px">
  <button name="saveHoliday" class="btn btn-default">Add holiday</button>
</form>
</div>

<div class="tab-pane" id="ta-o-reg">
<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaOvertime"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control">
  <select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $k => $v}<option value="{$k}" {if $department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <button class="btn btn-default">Show</button>
</form>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Staff</th><th>Shift</th><th>Worked</th><th>OT</th><th>Daily</th><th>Weekly</th><th>Rest day</th><th>Holiday</th><th>Weighted</th><th>Locked</th></tr></thead><tbody>
{foreach $overtime as $o}
<tr class="{if $o.ot_holiday_minutes || $o.ot_restday_minutes}info{/if}">
  <td>{$o.business_date}</td><td>{$o.staff_no|escape:'html':'UTF-8'} {$o.staff_name|escape:'html':'UTF-8'}</td><td>{$o.shift_code|escape:'html':'UTF-8'}</td>
  <td>{$o.worked_minutes}</td><td><strong>{$o.ot_minutes}</strong></td>
  <td>{$o.ot_daily_minutes}</td><td>{$o.ot_weekly_minutes}</td><td>{$o.ot_restday_minutes}</td><td>{$o.ot_holiday_minutes}</td>
  <td>{$o.ot_weighted_minutes}</td><td>{if $o.locked}✔{/if}</td>
</tr>
{foreachelse}<tr><td colspan="11"><em>No overtime in that range.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">"Weighted" is minutes × multiplier — the number Payroll pays. Night minutes are reported separately on the timesheet; the night rule's multiplier is a premium Payroll applies to them, not extra overtime.</p>
</div>

</div></div></div>
