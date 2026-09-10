<div class="pulse-ta"><div class="panel"><h3><i class="icon-check"></i> Timesheets — {$from} to {$to}</h3>

<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaTimesheets"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control">
  <select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $k => $v}<option value="{$k}" {if $department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <select name="status" class="form-control"><option value="">Any status</option>{foreach $statuses as $s}<option value="{$s}" {if $status==$s}selected{/if}>{$s}</option>{/foreach}</select>
  <button class="btn btn-default">Show</button>
</form>

{if !$extract.complete}<div class="alert alert-warning"><strong>{$extract.unlocked_days} day(s) in this range are not yet locked.</strong> Payroll only reads locked days, so approve the period below before running a payroll for it.</div>{/if}
{if $counts.block}<div class="alert alert-danger"><strong>{$counts.block} blocking exception(s) are open.</strong> A period cannot be approved until they are cleared — <a href="{$exceptions_url}">open the queue</a>.</div>{/if}

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-t-tot">Totals by person ({$totals|count})</a></li>
  <li><a data-toggle="tab" href="#ta-t-grid">Day by day ({$grid|count})</a></li>
  <li><a data-toggle="tab" href="#ta-t-dept">By department</a></li>
  <li><a data-toggle="tab" href="#ta-t-per">Approval periods ({$periods|count})</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-t-tot">
<table class="table table-condensed"><thead><tr><th>Staff</th><th>Dept</th><th>Basis</th><th>Days</th><th>Worked</th><th>Absent</th><th>Leave</th><th>Worked h</th><th>OT h</th><th>Weighted OT h</th><th>Night h</th><th>Late min</th><th>Locked</th><th></th></tr></thead><tbody>
{foreach $totals as $t}
<tr class="{if $t.with_exceptions}warning{/if}">
  <td><a href="{$self_url}&id_staff={$t.id_pulse_ta_staff}&from={$from}&to={$to}">{$t.staff_no|escape:'html':'UTF-8'} {$t.staff_name|escape:'html':'UTF-8'}</a></td>
  <td>{$t.department|escape:'html':'UTF-8'}</td><td>{$t.pay_basis}</td>
  <td>{$t.days}</td><td>{$t.days_worked}</td><td>{$t.days_absent}</td><td>{$t.days_leave}</td>
  <td><strong>{($t.worked_minutes/60)|string_format:"%.2f"}</strong></td>
  <td>{($t.ot_minutes/60)|string_format:"%.2f"}</td>
  <td>{($t.ot_weighted_minutes/60)|string_format:"%.2f"}</td>
  <td>{($t.night_minutes/60)|string_format:"%.2f"}</td>
  <td>{$t.late_minutes}</td>
  <td>{if $t.all_locked}<span class="label label-success">yes</span>{else}<span class="label label-default">no</span>{/if}</td>
  <td>{if $t.with_exceptions}<span class="badge">{$t.with_exceptions}</span>{/if}</td>
</tr>
{foreachelse}<tr><td colspan="14"><em>No timesheets in that range. Rebuild the range below, or check that punches have arrived.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline noprint">
  <input type="hidden" name="from" value="{$from}"><input type="hidden" name="to" value="{$to}"><input type="hidden" name="department" value="{$department}">
  <button name="rebuildRange" class="btn btn-default">Rebuild {$from} → {$to}</button>
  <button name="exportCsv" class="btn btn-default">Export CSV</button>
  <span class="text-muted">Rebuilding never changes a punch and never touches a locked period.</span>
</form>
<p class="text-muted" style="margin-top:10px">Labour: {$labour.worked_hours} hours over {$labour.occupied_room_nights} occupied room-nights{if $labour.hours_per_occupied_room} — <strong>{$labour.hours_per_occupied_room} hours per occupied room</strong>{/if}.</p>
</div>

<div class="tab-pane" id="ta-t-grid">
<table class="table table-condensed"><thead><tr><th>Date</th><th>Staff</th><th>Shift</th><th>In</th><th>Out</th><th>Worked</th><th>Break</th><th>Round</th><th>OT</th><th>Night</th><th>Late</th><th>Status</th><th>Exc</th><th></th></tr></thead><tbody>
{foreach $grid as $g}
<tr class="{if $g.status=='absent'}danger{elseif $g.status=='incomplete'}warning{elseif $g.locked}success{/if}">
  <td>{$g.business_date}</td>
  <td>{$g.staff_no|escape:'html':'UTF-8'} {$g.staff_name|escape:'html':'UTF-8'}</td>
  <td>{if $g.shift_code}{$g.shift_code|escape:'html':'UTF-8'}{if $g.crosses_midnight} <small class="text-muted" title="crosses midnight">+1</small>{/if}{/if}</td>
  <td>{if $g.first_in}{$g.first_in|date_format:"%H:%M"}{else}—{/if}</td>
  <td>{if $g.last_out}{$g.last_out|date_format:"%d/%m %H:%M"}{else}—{/if}</td>
  <td><strong>{$g.worked_minutes}</strong></td><td>{$g.break_minutes}</td>
  <td>{if $g.rounded_minutes}{if $g.rounded_minutes > 0}+{/if}{$g.rounded_minutes}{/if}</td>
  <td>{if $g.ot_minutes}{$g.ot_minutes}{if $g.ot_holiday_minutes} <small class="text-muted">hol</small>{elseif $g.ot_restday_minutes} <small class="text-muted">rest</small>{/if}{/if}</td>
  <td>{if $g.night_minutes}{$g.night_minutes}{/if}</td>
  <td>{if $g.late_minutes}{$g.late_minutes}{/if}</td>
  <td>{$g.status}</td>
  <td>{if $g.open_exceptions}<a class="badge" href="{$exceptions_url}&id_staff={$g.id_pulse_ta_staff}">{$g.open_exceptions}</a>{/if}</td>
  <td>{if $g.locked}<span class="label label-success">locked</span>{/if}</td>
</tr>
{foreachelse}<tr><td colspan="14"><em>Nothing built for that range yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ta-t-dept">
<table class="table table-condensed"><thead><tr><th>Department</th><th>Staff</th><th>Days</th><th>Worked h</th><th>OT h</th><th>Weighted OT h</th><th>Night h</th><th>Absences</th><th>Late days</th></tr></thead><tbody>
{foreach $by_dept as $d}
<tr><td>{if isset($departments[$d.department])}{$departments[$d.department]}{else}{$d.department|escape:'html':'UTF-8'}{/if}</td><td>{$d.staff}</td><td>{$d.days}</td>
<td>{($d.worked_minutes/60)|string_format:"%.1f"}</td><td>{($d.ot_minutes/60)|string_format:"%.1f"}</td><td>{($d.ot_weighted_minutes/60)|string_format:"%.1f"}</td>
<td>{($d.night_minutes/60)|string_format:"%.1f"}</td><td>{$d.absences}</td><td>{$d.late_days}</td></tr>
{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ta-t-per">
<p class="text-muted">Approving a period locks every timesheet in it. From that moment no punch can be added, no adjustment made and no rebuild run for those dates, and Payroll may read the numbers. A mistake is fixed by reopening the period with a reason, which is audited.</p>
<table class="table table-condensed"><thead><tr><th>Period</th><th>Dept</th><th>Range</th><th>Staff</th><th>Worked h</th><th>Weighted OT h</th><th>Blocking</th><th>Status</th><th>Approved by</th><th>Actions</th></tr></thead><tbody>
{foreach $periods as $p}
<tr class="{if $p.status=='locked'}success{elseif $p.open_exceptions}warning{/if}">
  <td>{$p.name|escape:'html':'UTF-8'}</td><td>{if $p.department}{$p.department|escape:'html':'UTF-8'}{else}all{/if}</td>
  <td>{$p.date_from} → {$p.date_to}</td><td>{$p.staff_count}</td>
  <td>{($p.worked_minutes/60)|string_format:"%.1f"}</td><td>{($p.ot_weighted_minutes/60)|string_format:"%.1f"}</td>
  <td>{if $p.open_exceptions}<span class="label label-danger">{$p.open_exceptions}</span>{else}<span class="label label-success">0</span>{/if}</td>
  <td>{$p.status}</td>
  <td>{if $p.approver}{$p.approver|escape:'html':'UTF-8'}<br><small class="text-muted">{$p.approved_at|date_format:"%d/%m/%Y %H:%M"}</small>{/if}{if $p.reopen_reason}<br><small class="text-danger">reopened: {$p.reopen_reason|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><form method="post" class="form-inline"><input type="hidden" name="id_period" value="{$p.id_pulse_ta_period}">
    {if $p.status=='open' || $p.status=='reopened'}
      <button name="rebuildPeriod" class="btn btn-xs btn-default">Rebuild</button>
      <button name="submitPeriod" class="btn btn-xs btn-default">Submit</button>
    {/if}
    {if $p.status=='submitted' || $p.status=='open' || $p.status=='reopened'}
      <button name="approvePeriod" class="btn btn-xs btn-primary" onclick="return confirm('Approve and lock this period? Payroll will treat it as final.')">Approve &amp; lock</button>
      {if $p.open_exceptions}<label class="checkbox-inline"><input type="checkbox" name="force" value="1"> override</label>{/if}
    {/if}
    {if $p.status=='locked'}<input name="reopen_reason" class="input-sm" placeholder="reason" style="width:110px"><button name="reopenPeriod" class="btn btn-xs btn-link" onclick="return confirm('Reopen an approved period? This is audited.')">Reopen</button>{/if}
  </form></td>
</tr>
{foreachelse}<tr><td colspan="10"><em>No periods yet. Create one below — a month or a fortnight, per department or for the whole property.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline">
  <input name="p_name" class="form-control" placeholder="e.g. September 2026" value="{$from|date_format:'%B %Y'}">
  <input type="date" name="p_from" class="form-control" value="{$from}"> <input type="date" name="p_to" class="form-control" value="{$to}">
  <select name="p_department" class="form-control"><option value="">Whole property</option>{foreach $departments as $k => $v}<option value="{$k}">{$v}</option>{/foreach}</select>
  <button name="createPeriod" class="btn btn-default">Create period</button>
</form>
</div>

</div></div></div>
