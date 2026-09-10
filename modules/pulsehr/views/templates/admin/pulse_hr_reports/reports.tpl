<div class="pulse-hr"><div class="panel"><h3><i class="icon-bar-chart"></i> HR reports</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseHrReports"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<select name="report" class="form-control">
{foreach ['headcount'=>'Headcount vs establishment','turnover'=>'Turnover','leavers'=>'Leavers','absence'=>'Absence','expiry'=>'Expiry dashboard','liability'=>'Leave liability','service'=>'Length of service','paybasis'=>'Pay basis in force','labour'=>'Labour hours per occupied room'] as $k=>$v}
<option value="{$k}"{if $report==$k} selected{/if}>{$v}</option>{/foreach}</select>
<input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control">
<select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.code}"{if $department==$d.code} selected{/if}>{$d.name}</option>{/foreach}</select>
<input name="days" type="number" class="form-control" style="width:90px" value="{$days}" title="Expiry window in days">
<input name="year" type="number" class="form-control" style="width:100px" value="{$year}" title="Liability year">
<button class="btn btn-default">Run</button>
<a class="btn btn-default" href="javascript:window.print()"><i class="icon-print"></i> Print</a>
</form>
<form method="post" class="inline noprint" style="margin-top:6px">
<input type="hidden" name="report" value="{$report}"><input type="hidden" name="from" value="{$from}"><input type="hidden" name="to" value="{$to}">
<input type="hidden" name="department" value="{$department}"><input type="hidden" name="days" value="{$days}"><input type="hidden" name="year" value="{$year}"><input type="hidden" name="on_date" value="{$on_date}">
<button name="exportCsv" class="btn btn-default btn-sm"><i class="icon-download"></i> Export this report as CSV</button></form>
</div>

{if $report=='labour'}
<div class="panel"><h3>Labour hours per occupied room <small class="muted">— hours from {$labour.source}</small></h3>
<p>{$labour.total_hours} hours across {$labour.room_nights} occupied room nights = <strong>{$labour.hours_per_room} hours per occupied room</strong>.
{if !$ta}<br><span class="muted">Pulse Time is not installed, so hours come from accepted mobile punches where there are any, and from the published roster otherwise. A rostered hour is a plan, not a fact — treat the figure as indicative until biometric attendance is in.</span>{/if}</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th class="text-right">Hours</th><th class="text-right">Rooms occupied</th><th class="text-right">Occupancy</th><th class="text-right">Hours / room</th></tr></thead><tbody>
{foreach $labour.rows as $r}<tr><td>{$r.date}</td><td class="text-right">{$r.hours}</td><td class="text-right">{$r.occupied}</td><td class="text-right">{$r.occupancy_pct}%</td><td class="text-right"><strong>{$r.hours_per_room}</strong></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='absence'}
<div class="panel"><h3>Absence {$from} → {$to}</h3>
<p>{$absence.total_days} days of leave taken against {$absence.rostered_shifts} rostered shifts — an absence rate of <strong>{$absence.absence_pct}%</strong>.</p>
<table class="table table-condensed"><thead><tr><th>Department</th><th>Type</th><th>Paid</th><th class="text-right">Requests</th><th class="text-right">Days</th></tr></thead><tbody>
{foreach $absence.rows as $r}<tr><td>{$r.department}</td><td>{$r.type_name}</td><td>{if $r.paid}yes{else}<strong>no</strong>{/if}</td><td class="text-right">{$r.requests}</td><td class="text-right">{$r.days}</td></tr>
{foreachelse}<tr><td colspan="5"><em class="muted">No leave in that window.</em></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='liability'}
<div class="panel"><h3>Leave liability {$liability.year} — {displayPrice price=$liability.total}</h3>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Who</th><th>Department</th><th class="text-right">Days owed</th><th class="text-right">Daily rate</th><th class="text-right">Value</th></tr></thead><tbody>
{foreach $liability.rows as $r}<tr><td>{$r.staff_no}</td><td>{$r.employee_name}</td><td>{$r.dept_name}</td><td class="text-right">{$r.days}</td><td class="text-right">{displayPrice price=$r.daily_rate}</td><td class="text-right">{displayPrice price=$r.value}</td></tr>{/foreach}
</tbody><tfoot><tr><th colspan="5" class="text-right">Total</th><th class="text-right">{displayPrice price=$liability.total}</th></tr></tfoot></table></div>

{elseif $report=='expiry'}
<div class="panel"><h3>Everything lapsing inside {$days} days</h3>
<table class="table table-condensed"><thead><tr><th>Kind</th><th>What</th><th>Who</th><th>Staff no</th><th>Department</th><th>Date</th><th class="text-right">Days left</th></tr></thead><tbody>
{foreach $rows as $r}<tr class="{if $r.days_left<0}danger{elseif $r.days_left<8}warning{/if}"><td>{$r.kind}</td><td>{$r.what}</td><td>{$r.who}</td><td>{$r.staff_no}</td><td>{$r.department}</td><td>{$r.date}</td><td class="text-right">{$r.days_left}</td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">Nothing lapses in that window.</em></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='paybasis'}
<div class="panel"><h3>Pay basis in force on {$on_date}</h3>
<p class="muted">This is exactly what payroll reads: the contract version whose effective window contains the date, not the newest row on the person.</p>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Who</th><th>Department</th><th>Grade</th><th>Contract</th><th>Effective</th><th>Basis</th><th class="text-right">Rate</th><th class="text-right">Monthly equivalent</th></tr></thead><tbody>
{foreach $rows as $r}<tr><td>{$r.staff_no}</td><td><a href="{$employee_url}&id_employee_hr={$r.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$r.employee_name}</a></td><td>{$r.dept_name}</td><td>{$r.grade_code}</td>
<td>{$r.contract_no} <span class="muted">{$r.type|replace:'_':' '}</span></td><td>{$r.effective_from} → {$r.effective_to|default:'—'}</td><td>{$r.pay_basis|replace:'_':' '}</td>
<td class="text-right">{displayPrice price=$r.pay_rate}</td><td class="text-right">{displayPrice price=$r.monthly_equivalent}</td></tr>
{foreachelse}<tr><td colspan="9"><em class="muted">Nobody had a contract in force on that date.</em></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='turnover'}
<div class="panel"><h3>Turnover {$from} → {$to}</h3>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="text-right">Joiners</th><th class="text-right">Leavers</th><th class="text-right">Resigned</th><th class="text-right">Involuntary</th><th class="text-right">Avg headcount</th><th class="text-right">Turnover</th></tr></thead><tbody>
{foreach $rows as $r}<tr class="{if $r.turnover_pct>15}danger{elseif $r.turnover_pct>8}warning{/if}"><td>{$r.department}</td><td class="text-right">{$r.joiners}</td><td class="text-right">{$r.leavers}</td><td class="text-right">{$r.resignations}</td><td class="text-right">{$r.involuntary}</td><td class="text-right">{$r.avg_headcount}</td><td class="text-right"><strong>{$r.turnover_pct}%</strong></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='leavers'}
<div class="panel"><h3>Leavers {$from} → {$to}</h3>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Who</th><th>Department</th><th>Position</th><th>Joined</th><th>Left</th><th>Type</th><th>Reason</th><th class="text-right">Days served</th><th>Re-hire</th></tr></thead><tbody>
{foreach $rows as $r}<tr class="{if $r.days_served<180}warning{/if}"><td>{$r.staff_no}</td><td>{$r.employee_name}</td><td>{$r.department}</td><td>{$r.position}</td><td>{$r.hire_date}</td><td>{$r.exit_date}</td>
<td>{$r.exit_type|replace:'_':' '}</td><td>{$r.exit_reason}</td><td class="text-right">{$r.days_served}</td><td>{if $r.rehire_eligible}yes{else}<strong>no</strong>{/if}</td></tr>
{foreachelse}<tr><td colspan="10"><em class="muted">Nobody left in that window.</em></td></tr>{/foreach}
</tbody></table></div>

{elseif $report=='service'}
<div class="panel"><h3>Length of service</h3>
<table class="table table-condensed"><thead><tr><th>Band</th><th class="text-right">On strength</th></tr></thead><tbody>
{foreach $rows as $r}<tr><td>{$r.band}</td><td class="text-right">{$r.headcount}</td></tr>{/foreach}</tbody></table></div>

{else}
<div class="panel"><h3>Headcount against establishment</h3>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="text-right">Budgeted</th><th class="text-right">On strength</th><th class="text-right">Confirmed</th><th class="text-right">Probation</th><th class="text-right">On leave</th><th class="text-right">Suspended</th><th class="text-right">Women</th><th class="text-right">Vacancies</th><th class="text-right">Over</th><th class="text-right">Filled</th></tr></thead><tbody>
{foreach $headcount as $h}<tr class="{if $h.over>0}danger{elseif $h.vacancies>0}warning{/if}"><td>{$h.department}</td><td class="text-right">{$h.establishment}</td><td class="text-right">{$h.headcount}</td><td class="text-right">{$h.confirmed}</td>
<td class="text-right">{$h.probation}</td><td class="text-right">{$h.on_leave}</td><td class="text-right">{$h.suspended}</td><td class="text-right">{$h.female}</td><td class="text-right">{$h.vacancies}</td><td class="text-right">{$h.over}</td><td class="text-right">{$h.fill_pct}%</td></tr>{/foreach}
</tbody></table></div>
{/if}
</div>
