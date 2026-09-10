<div class="pulse-ta">
<div class="panel"><h3><i class="icon-time"></i> Live Board — {$business_date} <small class="text-muted">as at {$now}</small></h3>
<div class="row ta-tiles">
  <div class="col-md-2 col-sm-4"><div class="ta-tile ta-in"><span class="n">{$counters.on_site}</span><span class="l">On site now</span></div></div>
  <div class="col-md-2 col-sm-4"><div class="ta-tile"><span class="n">{$counters.expected_not_in}</span><span class="l">Rostered, not in</span></div></div>
  <div class="col-md-2 col-sm-4"><div class="ta-tile {if $counters.late}ta-warn{/if}"><span class="n">{$counters.late}</span><span class="l">Late today</span></div></div>
  <div class="col-md-2 col-sm-4"><div class="ta-tile {if $counters.blocking_exceptions}ta-bad{/if}"><span class="n">{$counters.open_exceptions}</span><span class="l">Open exceptions{if $counters.blocking_exceptions} ({$counters.blocking_exceptions} blocking){/if}</span></div></div>
  <div class="col-md-2 col-sm-4"><div class="ta-tile {if $counters.devices_offline || $counters.devices_pending}ta-bad{/if}"><span class="n">{$counters.devices_offline}</span><span class="l">Devices offline{if $counters.devices_pending} · {$counters.devices_pending} pending{/if}</span></div></div>
  <div class="col-md-2 col-sm-4"><div class="ta-tile"><span class="n">{$counters.punches_today}</span><span class="l">Punches today</span></div></div>
</div>
{if !$hr}<div class="alert alert-info" style="margin-top:10px">Pulse HR is not installed, so Time &amp; Attendance is using its own local staff roster. Maintain it under <strong>Enrolment</strong>. Everything else works exactly the same.</div>{/if}
{if $counters.devices_pending}<div class="alert alert-warning" style="margin-top:10px"><strong>{$counters.devices_pending} device(s) have called in with a serial nobody has claimed.</strong> Their punches are being counted but not stored until an administrator claims them — <a href="{$devices_url}">open Devices</a>.</div>{/if}
{if $counters.unmatched}<div class="alert alert-warning" style="margin-top:10px">{$counters.unmatched} punch(es) in the last week came from a device user id nobody is mapped to. Map them under <strong>Enrolment</strong> and the punches are re-attributed automatically.</div>{/if}

<form method="get" class="form-inline noprint" style="margin:10px 0">
  <input type="hidden" name="controller" value="AdminPulseTaBoard"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $k => $v}<option value="{$k}" {if $department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <button class="btn btn-default">Show</button>
</form>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-b-board">Who is in ({$counters.on_site})</a></li>
  <li><a data-toggle="tab" href="#ta-b-dev">Devices ({$devices|count})</a></li>
  <li><a data-toggle="tab" href="#ta-b-exc">Latest exceptions</a></li>
  <li><a data-toggle="tab" href="#ta-b-act">Quick actions</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-b-board">
{foreach $groups as $dept => $rows}
<h4 class="ta-dept">{if isset($departments[$dept])}{$departments[$dept]}{else}{$dept|escape:'html':'UTF-8'}{/if} <small class="text-muted">{$rows|count} staff</small></h4>
<table class="table table-condensed ta-board"><thead><tr><th>Staff</th><th>Position</th><th>Rostered</th><th>First in</th><th>Last punch</th><th>Reader</th><th>On site</th><th>Late</th><th>State</th></tr></thead><tbody>
{foreach $rows as $r}
<tr class="{if $r.is_in}success{elseif $r.state=='not_in' && $r.shift_code}warning{/if}">
  <td><strong>{$r.staff_no|escape:'html':'UTF-8'}</strong> {$r.firstname|escape:'html':'UTF-8'} {$r.lastname|escape:'html':'UTF-8'}</td>
  <td><small>{$r.position|escape:'html':'UTF-8'}</small></td>
  <td>{if $r.shift_code}<span class="label label-default">{$r.shift_code|escape:'html':'UTF-8'}</span> {$r.start_time|truncate:5:''}–{$r.end_time|truncate:5:''}{if $r.crosses_midnight} <small class="text-muted">+1</small>{/if}{else}<small class="text-muted">no roster</small>{/if}</td>
  <td>{if $r.first_at}{$r.first_at|date_format:"%H:%M"}{else}—{/if}</td>
  <td>{if $r.last_at}{$r.last_at|date_format:"%H:%M"} <small class="text-muted">{$r.last_dir}</small>{else}—{/if}</td>
  <td><small>{$r.last_device|escape:'html':'UTF-8'}</small></td>
  <td>{if $r.on_site_minutes}{$r.on_site_minutes|intval} min{/if}</td>
  <td>{if $r.late_minutes > 0}<span class="badge ta-late">{$r.late_minutes} min</span>{/if}</td>
  <td>{if $r.is_in}<span class="label label-success">IN</span>{elseif $r.state=='left'}<span class="label label-default">left</span>{elseif $r.state=='rest'}<span class="label label-info">rest day</span>{elseif $r.state=='leave'}<span class="label label-info">leave</span>{elseif $r.state=='holiday'}<span class="label label-info">holiday</span>{else}<span class="label label-warning">not in</span>{/if}</td>
</tr>
{/foreach}
</tbody></table>
{foreachelse}<p><em>No active staff. Add them under Enrolment, or install Pulse HR and press "Mirror from HR".</em></p>{/foreach}
</div>

<div class="tab-pane" id="ta-b-dev">
<table class="table table-condensed"><thead><tr><th>Device</th><th>Brand</th><th>Where</th><th>Mode</th><th>Status</th><th>Health</th><th>Last seen</th><th>Last punch</th><th>Punches</th></tr></thead><tbody>
{foreach $devices as $d}
<tr class="{if $d.status=='pending'}warning{elseif $d.health=='offline'}danger{/if}">
  <td><a href="{$devices_url}&id_device={$d.id_pulse_ta_device}">{$d.name|escape:'html':'UTF-8'}</a></td>
  <td>{$d.brand|escape:'html':'UTF-8'}</td><td>{$d.location|escape:'html':'UTF-8'}</td><td>{$d.mode}</td>
  <td>{if $d.status=='active'}<span class="label label-success">active</span>{elseif $d.status=='pending'}<span class="label label-warning">pending — claim it</span>{else}<span class="label label-danger">blocked</span>{/if}</td>
  <td>{$d.health}</td>
  <td>{if $d.last_seen_at}{$d.last_seen_at|date_format:"%d/%m %H:%M"}{if $d.silent_min !== null} <small class="text-muted">{$d.silent_min} min ago</small>{/if}{else}never{/if}</td>
  <td>{if $d.last_punch_at}{$d.last_punch_at|date_format:"%d/%m %H:%M"}{else}—{/if}</td>
  <td>{$d.punch_count|intval}</td>
</tr>
{/foreach}
</tbody></table>
<form method="post"><button name="pollAll" class="btn btn-default btn-sm"><i class="icon-refresh"></i> Poll every device now</button></form>
</div>

<div class="tab-pane" id="ta-b-exc">
<table class="table table-condensed"><thead><tr><th>Date</th><th>Staff</th><th>Type</th><th>Severity</th><th>Detail</th></tr></thead><tbody>
{foreach $exceptions as $e}
<tr class="{if $e.severity=='block'}danger{/if}"><td>{$e.business_date}</td><td>{$e.staff_no|escape:'html':'UTF-8'} {$e.staff_name|escape:'html':'UTF-8'}</td><td>{$e.type}</td><td>{$e.severity}</td><td>{$e.detail|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="5"><em>Nothing open. That is the goal.</em></td></tr>{/foreach}
</tbody></table>
<a class="btn btn-default btn-sm" href="{$exceptions_url}">Open the exception queue</a>
</div>

<div class="tab-pane" id="ta-b-act">
<div class="row"><div class="col-md-6">
<h4>Record a punch by hand</h4>
<p class="text-muted">For a finger that will not read, or a member of staff who was let in by the night manager. It is stored as a manual punch and shows as one everywhere.</p>
<form method="post" class="form-horizontal">
  <div class="form-group"><label class="col-sm-3">Staff</label><div class="col-sm-9"><select name="id_staff" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} — {$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">When</label><div class="col-sm-9"><input name="at" class="form-control" value="{$now}:00" placeholder="YYYY-MM-DD HH:MM:SS"></div></div>
  <div class="form-group"><label class="col-sm-3">Direction</label><div class="col-sm-9"><select name="direction" class="form-control"><option value="in">In</option><option value="out">Out</option><option value="break_out">Break out</option><option value="break_in">Break in</option><option value="unknown">Let the engine decide</option></select></div></div>
  <button name="quickPunch" class="btn btn-primary">Record punch</button>
</form>
</div><div class="col-md-6">
<h4>Rebuild timesheets</h4>
<p class="text-muted">Re-pairs punches into shifts for a business date. Safe to run as often as you like — it never touches a punch and it skips any period that is already approved and locked.</p>
<form method="post" class="form-inline">
  <input type="date" name="build_date" class="form-control" value="{$business_date}">
  <button name="rebuildToday" class="btn btn-default">Rebuild that day</button>
</form>
<h4 style="margin-top:20px">Labour this month</h4>
<p>{$labour.worked_hours} hours worked across {$labour.occupied_room_nights} occupied room-nights{if $labour.hours_per_occupied_room} — <strong>{$labour.hours_per_occupied_room} hours per occupied room</strong>{/if}.</p>
<a class="btn btn-default btn-sm" href="{$timesheets_url}">Open Timesheets</a>
</div></div>
</div>

</div></div></div>
