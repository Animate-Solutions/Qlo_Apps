<div class="pulse-hr">
<div class="panel"><h3><i class="icon-group"></i> Human resources — {$d.business_date}</h3>
<div class="kpi"><b>{$d.total}</b><span>On strength</span></div>
<div class="kpi{if $d.probation>0} warn{/if}"><b>{$d.probation}</b><span>On probation</span></div>
<div class="kpi"><b>{$d.on_leave_today|count}</b><span>On leave today</span></div>
<div class="kpi{if $d.suspended>0} bad{/if}"><b>{$d.suspended}</b><span>Suspended</span></div>
<div class="kpi{if $d.doc_expiry|count>0} bad{/if}"><b>{$d.doc_expiry|count}</b><span>Documents lapsing</span></div>
<div class="kpi{if $d.pending_leave|count>0} warn{/if}"><b>{$d.pending_leave|count}</b><span>Leave to approve</span></div>
<div class="kpi"><b>{$d.labour.hours_per_room}</b><span>Labour h / occ. room</span></div>
<div class="kpi"><b>{$d.occupancy.occupied}</b><span>Rooms occupied</span></div>
{if !$fd}<p class="muted"><em>Front Desk is not installed, so occupancy comes from bookings rather than the night audit.</em></p>{/if}
</div>

<div class="row">
<div class="col-lg-6"><div class="panel"><h3>Headcount against establishment</h3>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="text-right">On strength</th><th class="text-right">Budgeted</th><th class="text-right">Variance</th></tr></thead><tbody>
{foreach $d.headcount as $h}<tr class="{if $h.variance<0}warning{elseif $h.variance>0}danger{/if}"><td>{$h.name}</td><td class="text-right">{$h.headcount}</td><td class="text-right">{$h.establishment}</td><td class="text-right">{if $h.variance>0}+{/if}{$h.variance}</td></tr>{/foreach}
</tbody></table><a class="btn btn-default btn-sm" href="{$reports_url}">All HR reports</a></div></div>

<div class="col-lg-6"><div class="panel"><h3>Coverage today <small class="muted">— {$d.occupancy.occupied} of {$d.occupancy.total} rooms occupied ({$d.occupancy.source})</small></h3>
<table class="table table-condensed"><thead><tr><th>Department</th><th class="text-right">Rostered</th><th class="text-right">On leave</th><th class="text-right">Needed</th><th class="text-right">Short</th></tr></thead><tbody>
{foreach $d.coverage as $c}<tr class="{if $c.short>0}danger{/if}"><td>{$c.name}</td><td class="text-right">{$c.rostered}</td><td class="text-right">{$c.on_leave}</td><td class="text-right">{if $c.room_driven}{$c.needed}{else}<span class="muted">—</span>{/if}</td><td class="text-right">{if $c.short>0}<strong>{$c.short}</strong>{else}—{/if}</td></tr>{/foreach}
</tbody></table><a class="btn btn-default btn-sm" href="{$roster_url}">Open the roster</a></div></div>
</div>

<div class="row">
<div class="col-lg-6"><div class="panel"><h3>Expiring documents</h3>
{if $d.doc_expiry}<table class="table table-condensed"><thead><tr><th>Who</th><th>Document</th><th>Expires</th><th></th></tr></thead><tbody>
{foreach $d.doc_expiry as $x}<tr class="{if $x.days_left<0}danger{elseif $x.days_left<8}warning{/if}"><td><a href="{$employees_url}&id_employee_hr={$x.id_pulse_hr_employee}">{$x.employee_name}</a> <span class="muted">{$x.staff_no}</span></td><td>{$x.name}</td><td>{$x.expires_on}</td><td>{if $x.days_left<0}<span class="label label-danger">lapsed {$x.days_left|replace:'-':''} d ago</span>{else}<span class="label label-warning">{$x.days_left} d</span>{/if}</td></tr>{/foreach}
</tbody></table>{else}<p class="muted">Nothing lapsing inside the reminder window.</p>{/if}</div></div>

<div class="col-lg-6"><div class="panel"><h3>Contracts and probation</h3>
<table class="table table-condensed"><tbody>
{foreach $d.contract_expiry as $c}<tr class="warning"><td><a href="{$employees_url}&id_employee_hr={$c.id_pulse_hr_employee}">{$c.employee_name}</a></td><td>{$c.type|replace:'_':' '} contract ends</td><td>{$c.end_date}</td></tr>{/foreach}
{foreach $d.probation_due as $p}<tr><td><a href="{$employees_url}&id_employee_hr={$p.id_pulse_hr_employee}">{$p.full_name}</a></td><td>probation ends</td><td>{$p.probation_end}</td></tr>{/foreach}
{if !$d.contract_expiry && !$d.probation_due}<tr><td colspan="3"><em class="muted">Nothing due.</em></td></tr>{/if}
</tbody></table></div></div>
</div>

<div class="row">
<div class="col-lg-6"><div class="panel"><h3>Leave waiting for a decision</h3>
{if $d.pending_leave}<table class="table table-condensed"><thead><tr><th>Who</th><th>Type</th><th>Dates</th><th class="text-right">Days</th></tr></thead><tbody>
{foreach $d.pending_leave as $l}<tr><td><a href="{$leave_url}">{$l.employee_name}</a> <span class="muted">{$l.dept_name}</span></td><td>{$l.type_name}</td><td>{$l.date_from|date_format:"%d/%m"} – {$l.date_to|date_format:"%d/%m"}</td><td class="text-right">{$l.days}</td></tr>{/foreach}
</tbody></table><a class="btn btn-primary btn-sm" href="{$leave_url}">Approve leave</a>{else}<p class="muted">Nothing waiting.</p>{/if}</div></div>

<div class="col-lg-6"><div class="panel"><h3>Onboarding &amp; exit tasks due</h3>
{if $d.open_tasks}<table class="table table-condensed"><tbody>
{foreach $d.open_tasks as $t}<tr class="{if $t.due_on && $t.due_on < $d.business_date}danger{/if}"><td>{$t.employee_name} <span class="muted">{$t.staff_no}</span></td><td>{$t.title}</td><td>{$t.owner_department}</td><td>{$t.due_on}</td></tr>{/foreach}
</tbody></table><a class="btn btn-default btn-sm" href="{$lifecycle_url}">Open checklists</a>{else}<p class="muted">Nothing outstanding.</p>{/if}</div></div>
</div>

<div class="row">
<div class="col-lg-8"><div class="panel"><h3>Mobile punches a supervisor should look at</h3>
{if $d.flagged_punches}<table class="table table-condensed"><thead><tr><th>When</th><th>Who</th><th>Dir</th><th>Where</th><th>Accuracy</th><th>Why</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $d.flagged_punches as $p}<tr class="{if $p.status=='rejected'}danger{else}warning{/if}">
<td>{$p.punched_at|date_format:"%d/%m %H:%M"}</td><td><a href="{$employees_url}&id_employee_hr={$p.id_pulse_hr_employee}">{$p.employee_name}</a></td><td>{$p.direction}</td>
<td class="flag">{if $p.distance_m}{$p.distance_m|string_format:"%d"} m{if $p.lat} <a href="https://www.google.com/maps?q={$p.lat},{$p.lng}" target="_blank" rel="noreferrer">map</a>{/if}{else}<span class="muted">no location</span>{/if}</td>
<td class="flag">{if $p.accuracy_m}±{$p.accuracy_m|string_format:"%d"} m{else}—{/if}</td><td><span class="label label-default">{$p.flag_reason}</span> <span class="muted">{$p.source}</span></td>
<td class="hr-actions"><form method="post" class="form-inline"><input type="hidden" name="id_punch" value="{$p.id_pulse_hr_punch}"><input name="review_note" class="input-sm" placeholder="note" style="width:110px">
<button name="reviewPunch" value="1" class="btn btn-xs btn-success" formaction="{$self_url}&accept=1">Accept</button>
<button name="reviewPunch" value="1" class="btn btn-xs btn-danger" formaction="{$self_url}&accept=0">Reject</button></form></td></tr>{/foreach}
</tbody></table>{else}<p class="muted">Nothing flagged. A mobile punch is flagged when it lands outside the geofence, carries no location, or the phone's GPS was too vague to trust.</p>{/if}</div></div>

<div class="col-lg-4"><div class="panel"><h3>Staff portal</h3>
<p>Staff sign in at <a href="{$ess_url}" target="_blank" rel="noreferrer">{$ess_url}</a> with their staff number and PIN.</p>
<ul class="list-unstyled">
<li>{if $ta}<span class="label label-success">Pulse Time</span> biometric attendance{else}<span class="label label-default">no Pulse Time</span> mobile punches only{/if}</li>
<li>{if $pr}<span class="label label-success">Pulse Payroll</span> payslips on the portal{else}<span class="label label-default">no Pulse Payroll</span> the portal says payslips are unavailable{/if}</li>
<li>{if $kc}<span class="label label-success">Key Cards</span> issued and revoked by checklist{else}<span class="label label-default">no Key Cards</span> card tasks are manual{/if}</li>
<li>{if $pos}<span class="label label-success">POS</span> PINs issued and disabled by checklist{else}<span class="label label-default">no POS</span>{/if}</li>
</ul>
<p class="muted">Pending detail changes from staff: <strong>{$d.change_requests}</strong> — <a href="{$employees_url}">review them</a>.</p>
<form method="post" class="form-inline"><input type="month" name="month" class="form-control input-sm" value="{$d.business_date|truncate:7:''}"> <button name="accrueNow" class="btn btn-default btn-sm">Accrue leave for this month</button></form>
</div>
{if $d.birthdays}<div class="panel"><h3>Birthdays this week</h3><ul class="list-unstyled">{foreach $d.birthdays as $b}<li>{$b.dob|date_format:"%d %b"} — {$b.full_name} <span class="muted">{$b.dept_name}</span></li>{/foreach}</ul></div>{/if}
</div>
</div>
</div>
