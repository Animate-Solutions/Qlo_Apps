<div class="pulse-hr"><div class="panel"><h3><i class="icon-sitemap"></i> Organisation, positions and shift patterns</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#o-dept">Departments</a></li><li><a data-toggle="tab" href="#o-grade">Grades</a></li><li><a data-toggle="tab" href="#o-pos">Positions &amp; establishment</a></li><li><a data-toggle="tab" href="#o-shift">Shift patterns</a></li><li><a data-toggle="tab" href="#o-chart">Org chart</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="o-dept">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Department</th><th>Cost centre</th><th>Head</th><th>Coverage credit (min/room)</th><th>Active</th></tr></thead><tbody>
{foreach $departments as $d}<tr><td><code>{$d.code}</code></td><td>{$d.name}</td><td>{$d.cost_centre}</td>
<td>{foreach $staff as $s}{if $s.id_pulse_hr_employee==$d.id_head}{$s.full_name}{/if}{/foreach}</td>
<td>{if $d.credit_minutes_per_room}{$d.credit_minutes_per_room} min{else}<span class="muted">not room-driven</span>{/if}</td><td>{if $d.active}yes{else}no{/if}</td></tr>{/foreach}
</tbody></table>
<p class="muted">Coverage credit is what turns occupancy into heads: 25 minutes a room means a full house of 52 rooms needs about three housekeepers on an eight-hour shift. Leave it at 0 for a department the room count does not drive.</p>
<form method="post" class="form-inline"><input type="hidden" name="id_department">
<input name="code" class="form-control" placeholder="code" style="width:110px" required> <input name="name" class="form-control" placeholder="Department" required>
<input name="cost_centre" class="form-control" placeholder="Cost centre" style="width:130px">
<select name="id_head" class="form-control"><option value="">Head of department —</option>{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name}</option>{/foreach}</select>
<input name="credit_minutes_per_room" type="number" class="form-control" placeholder="min/room" style="width:100px" value="0">
<input name="sort" type="number" class="form-control" placeholder="sort" style="width:80px">
<button name="saveDept" class="btn btn-default">Save department</button></form>
<hr><h4>Sections</h4>
<table class="table table-condensed"><tbody>{foreach $sections as $s}<tr><td><code>{$s.code}</code></td><td>{$s.name}</td><td class="muted">{$s.dept_code}</td></tr>{foreachelse}<tr><td><em class="muted">None.</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_section">
<select name="id_pulse_hr_department" class="form-control">{foreach $departments as $d}<option value="{$d.id_pulse_hr_department}">{$d.name}</option>{/foreach}</select>
<input name="scode" class="form-control" placeholder="code" style="width:110px" required> <input name="sname" class="form-control" placeholder="Section" required>
<button name="saveSection" class="btn btn-default">Save section</button></form></div>

<div class="tab-pane" id="o-grade">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Grade</th><th>Level</th><th class="text-right">Band from</th><th class="text-right">Band to</th><th class="text-right">Annual leave</th><th class="text-right">Notice</th><th>Active</th></tr></thead><tbody>
{foreach $grades as $g}<tr><td><code>{$g.code}</code></td><td>{$g.name}</td><td>{$g.level}</td><td class="text-right">{displayPrice price=$g.salary_min}</td><td class="text-right">{displayPrice price=$g.salary_max}</td><td class="text-right">{$g.annual_leave_days} d</td><td class="text-right">{$g.notice_days} d</td><td>{if $g.active}yes{else}no{/if}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_grade">
<input name="gcode" class="form-control" placeholder="G9" style="width:80px" required> <input name="gname" class="form-control" placeholder="Grade name" required>
<input name="level" type="number" class="form-control" placeholder="level" style="width:80px">
<input name="salary_min" type="number" step="0.01" class="form-control" placeholder="Band from ₦" style="width:140px">
<input name="salary_max" type="number" step="0.01" class="form-control" placeholder="Band to ₦" style="width:140px">
<input name="annual_leave_days" type="number" step="0.5" class="form-control" placeholder="leave d" style="width:100px">
<input name="gnotice_days" type="number" class="form-control" placeholder="notice d" style="width:100px">
<button name="saveGrade" class="btn btn-default">Save grade</button></form>
<p class="muted">A contract written outside a grade's band is still saved — it is flagged in the audit trail rather than blocked, because a hotel occasionally has to pay above band to keep a chef.</p></div>

<div class="tab-pane" id="o-pos">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Position</th><th>Department</th><th>Grade</th><th class="text-right">Budgeted</th><th>Nights</th></tr></thead><tbody>
{foreach $positions as $p}<tr><td><code>{$p.code}</code></td><td>{$p.title}</td><td>{$p.dept_name}</td><td>{$p.grade_code}</td><td class="text-right">{$p.establishment}</td><td>{if $p.night_shift}yes{/if}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_position">
<input name="pcode" class="form-control" placeholder="code" style="width:130px" required> <input name="title" class="form-control" placeholder="Position title" required>
<select name="pdept" class="form-control">{foreach $departments as $d}<option value="{$d.id_pulse_hr_department}">{$d.name}</option>{/foreach}</select>
<select name="psection" class="form-control"><option value="">Section —</option>{foreach $sections as $s}<option value="{$s.id_pulse_hr_section}">{$s.dept_code} / {$s.name}</option>{/foreach}</select>
<select name="pgrade" class="form-control"><option value="">Grade —</option>{foreach $grades as $g}<option value="{$g.id_pulse_hr_grade}">{$g.code}</option>{/foreach}</select>
<input name="establishment" type="number" class="form-control" placeholder="heads" style="width:90px" value="1">
<label class="checkbox-inline"><input type="checkbox" name="pnight" value="1"> nights</label>
<button name="savePosition" class="btn btn-default">Save position</button></form>
<hr><h4>Establishment against actual</h4>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="text-right">Budgeted</th><th class="text-right">On strength</th><th class="text-right">Vacancies</th><th class="text-right">Over</th><th class="text-right">Filled</th></tr></thead><tbody>
{foreach $headcount as $h}<tr class="{if $h.over>0}danger{elseif $h.vacancies>0}warning{/if}"><td>{$h.department}</td><td class="text-right">{$h.establishment}</td><td class="text-right">{$h.headcount}</td><td class="text-right">{$h.vacancies}</td><td class="text-right">{$h.over}</td><td class="text-right">{$h.fill_pct}%</td></tr>{/foreach}
</tbody></table></div>

<div class="tab-pane" id="o-shift">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Shift</th><th>Start</th><th>End</th><th class="text-right">Break</th><th class="text-right">Paid hours</th><th>Night</th><th>Split</th><th>Department</th></tr></thead><tbody>
{foreach $shifts as $s}<tr><td><span class="swatch" style="background:{$s.colour}"></span><code>{$s.code}</code></td><td>{$s.name}</td><td>{$s.start_time|truncate:5:''}</td><td>{$s.end_time|truncate:5:''}</td><td class="text-right">{$s.break_minutes} min</td><td class="text-right">{$s.paid_hours}</td><td>{if $s.night}<span class="label label-default">nights</span>{/if}</td><td>{if $s.split}yes{/if}</td><td>{$s.department}</td></tr>{/foreach}
</tbody></table>
<p class="muted">A night shift that crosses midnight (22:00 → 06:00) is stored exactly like that; the paid hours are worked out across the boundary, and the night flag is what Payroll reads for the allowance.</p>
<form method="post" class="form-inline"><input type="hidden" name="id_shift">
<input name="shcode" class="form-control" placeholder="code" style="width:80px" required> <input name="shname" class="form-control" placeholder="Shift name" required>
<input name="start_time" class="form-control" placeholder="06:00" style="width:90px"> <input name="end_time" class="form-control" placeholder="14:00" style="width:90px">
<input name="break_minutes" type="number" class="form-control" placeholder="break" style="width:90px" value="30">
<input name="paid_hours" type="number" step="0.25" class="form-control" placeholder="paid h" style="width:90px">
<select name="shdept" class="form-control"><option value="">Any department</option>{foreach $departments as $d}<option value="{$d.code}">{$d.name}</option>{/foreach}</select>
<input name="colour" class="form-control" placeholder="#5bc0de" style="width:100px">
<label class="checkbox-inline"><input type="checkbox" name="night" value="1"> night</label>
<label class="checkbox-inline"><input type="checkbox" name="split" value="1"> split</label>
<label class="checkbox-inline"><input type="checkbox" name="on_call" value="1"> on call</label>
<button name="saveShift" class="btn btn-default">Save shift</button></form></div>

<div class="tab-pane" id="o-chart">
<ul class="list-unstyled org">
{foreach $chart as $c}<li class="d{$c.depth}" style="padding-left:{$c.depth*24}px">{if $c.depth>0}<span class="muted">└ </span>{/if}<a href="{$employee_url}&id_employee_hr={$c.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$c.full_name}</a>
<span class="muted">{$c.position_title}{if $c.dept_name} · {$c.dept_name}{/if}{if $c.grade_code} · {$c.grade_code}{/if}</span></li>
{foreachelse}<li><em class="muted">Nobody on strength yet.</em></li>{/foreach}
</ul>
<p class="muted">The chart is drawn from the reporting line on each contract. Somebody with no manager sits at the top.</p></div>

</div></div></div>
