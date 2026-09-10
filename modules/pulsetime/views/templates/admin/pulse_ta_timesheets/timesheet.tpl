<div class="pulse-ta"><div class="panel">
<h3><i class="icon-user"></i> {$staff.staff_no|escape:'html':'UTF-8'} — {$staff.firstname|escape:'html':'UTF-8'} {$staff.lastname|escape:'html':'UTF-8'}
<small class="text-muted">{$staff.position|escape:'html':'UTF-8'} · {$staff.department|escape:'html':'UTF-8'} · {$from} to {$to}</small>
<a class="btn btn-xs btn-default pull-right" href="{$self_url}&from={$from}&to={$to}">Back</a></h3>

{foreach $days as $d}
<div class="panel panel-default ta-day">
<div class="panel-heading">
  <strong>{$d.business_date|date_format:"%a %d %b %Y"}</strong>
  {if $d.shift_code}<span class="label label-default">{$d.shift_code|escape:'html':'UTF-8'}</span> {$d.shift_start|date_format:"%H:%M"} → {$d.shift_end|date_format:"%d/%m %H:%M"}{if $d.crosses_midnight} <small class="text-muted">crosses midnight</small>{/if}{/if}
  <span class="pull-right">
    {if $d.locked}<span class="label label-success">locked</span>{/if}
    <span class="label {if $d.status=='absent'}label-danger{elseif $d.status=='incomplete'}label-warning{else}label-default{/if}">{$d.status}</span>
  </span>
</div>
<div class="panel-body">
<div class="row">
<div class="col-md-4">
<table class="table table-condensed">
  <tr><th>First in → last out</th><td>{if $d.first_in}{$d.first_in|date_format:"%H:%M:%S"}{else}—{/if} → {if $d.last_out}{$d.last_out|date_format:"%d/%m %H:%M:%S"}{else}—{/if}</td></tr>
  <tr><th>Paired intervals</th><td>{$d.pairs}</td></tr>
  <tr><th>Raw minutes</th><td>{$d.raw_minutes}</td></tr>
  <tr><th>Break deducted</th><td>{$d.break_minutes}</td></tr>
  <tr><th>Rounding applied</th><td>{if $d.rounded_minutes > 0}+{/if}{$d.rounded_minutes} min</td></tr>
  <tr class="success"><th>Worked</th><td><strong>{$d.worked_minutes} min</strong> ({($d.worked_minutes/60)|string_format:"%.2f"} h)</td></tr>
  <tr><th>Scheduled</th><td>{$d.scheduled_minutes} min</td></tr>
  <tr><th>Late / early out / short</th><td>{$d.late_minutes} / {$d.early_out_minutes} / {$d.short_minutes} min</td></tr>
  <tr><th>Overtime</th><td>{$d.ot_minutes} min (daily {$d.ot_daily_minutes}, weekly {$d.ot_weekly_minutes}, rest {$d.ot_restday_minutes}, holiday {$d.ot_holiday_minutes})</td></tr>
  <tr><th>Weighted OT for payroll</th><td><strong>{$d.ot_weighted_minutes} min</strong></td></tr>
  <tr><th>Night minutes</th><td>{$d.night_minutes}</td></tr>
  <tr><th>Sources</th><td>{$d.sources|escape:'html':'UTF-8'}</td></tr>
</table>
<form method="post" class="form-inline noprint"><input type="hidden" name="ld_staff" value="{$d.id_pulse_ta_staff}"><input type="hidden" name="ld_date" value="{$d.business_date}">
  <input type="hidden" name="ld_lock" value="{if $d.locked}0{else}1{/if}">
  <button name="lockDay" class="btn btn-xs btn-default">{if $d.locked}Unlock this day{else}Lock this day{/if}</button>
</form>
</div>
<div class="col-md-4">
<h5>Punches read</h5>
<table class="table table-condensed"><thead><tr><th>#</th><th>Time</th><th>Role</th><th>Source</th><th>Device</th></tr></thead><tbody>
{foreach $detail[$d.business_date].punches as $p}
<tr class="{if $p.role=='ignored'}text-muted{elseif $p.virtual}info{/if}"><td>{$p.seq}</td><td>{$p.punched_at|date_format:"%d/%m %H:%M:%S"}</td><td>{$p.role}</td><td>{$p.source}</td><td>{$p.device_name|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="5"><em>none</em></td></tr>{/foreach}
</tbody></table>
</div>
<div class="col-md-4">
<h5>Corrections</h5>
<table class="table table-condensed"><thead><tr><th>Type</th><th>Detail</th><th>Approver</th></tr></thead><tbody>
{foreach $detail[$d.business_date].trail as $a}
<tr class="{if $a.status=='void'}text-muted{/if}"><td>{$a.type}</td>
<td>{if $a.punched_at}{$a.punched_at|date_format:"%H:%M"} {$a.direction}{/if}{if $a.minutes} {$a.minutes} min{/if}<br><small>{$a.reason|escape:'html':'UTF-8'}</small>{if $a.status=='void'} <span class="label label-default">void</span>{/if}</td>
<td>{$a.approver|escape:'html':'UTF-8'}<br><small class="text-muted">{$a.approved_at|date_format:"%d/%m %H:%M"}</small></td></tr>
{foreachelse}<tr><td colspan="3"><em>none — this day is exactly what the readers recorded</em></td></tr>{/foreach}
</tbody></table>
</div>
</div>
</div></div>
{foreachelse}<p><em>No timesheets for this person in that range.</em></p>{/foreach}
</div></div>
