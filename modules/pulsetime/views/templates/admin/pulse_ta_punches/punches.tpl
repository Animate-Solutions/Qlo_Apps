<div class="pulse-ta"><div class="panel"><h3><i class="icon-list"></i> Punches</h3>
<p class="text-muted">This register is append-only. A punch is never edited or deleted — a correction is a separate, approved adjustment made on the Exceptions screen, so the original evidence and the fix both survive a dispute.</p>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-p-list">Register ({$rows|count})</a></li>
  <li><a data-toggle="tab" href="#ta-p-unmatched">Unmatched device ids ({$unmatched_list|count})</a></li>
  <li><a data-toggle="tab" href="#ta-p-add">Record a punch</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-p-list">
<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaPunches"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <input type="date" name="from" value="{$f.from}" class="form-control"> <input type="date" name="to" value="{$f.to}" class="form-control">
  <select name="id_device" class="form-control"><option value="">Any device</option>{foreach $devices as $d}<option value="{$d.id_pulse_ta_device}" {if $f.id_device==$d.id_pulse_ta_device}selected{/if}>{$d.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="department" class="form-control"><option value="">Any department</option>{foreach $departments as $k => $v}<option value="{$k}" {if $f.department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <select name="source" class="form-control"><option value="">Any source</option>{foreach $sources as $k => $v}<option value="{$k}" {if $f.source==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <input name="q" value="{$f.q|escape:'html':'UTF-8'}" class="form-control" placeholder="Name, staff no or device id">
  <label class="checkbox-inline"><input type="checkbox" name="unmatched" value="1" {if $f.unmatched}checked{/if}> Unmatched only</label>
  <button class="btn btn-default">Filter</button>
</form>
<table class="table table-condensed"><thead><tr><th>Punched at</th><th>Staff</th><th>Device id</th><th>Device</th><th>Dir</th><th>Verify</th><th>Source</th><th>Business date</th><th></th></tr></thead><tbody>
{foreach $rows as $r}
<tr class="{if !$r.id_pulse_ta_staff}warning{elseif $r.source!='device'}info{/if}">
  <td><strong>{$r.punched_at|date_format:"%d/%m/%Y %H:%M:%S"}</strong>{if $r.device_time && $r.device_time != $r.punched_at}<br><small class="text-muted">device said {$r.device_time|date_format:"%H:%M:%S"}</small>{/if}</td>
  <td>{if $r.staff_name}{$r.staff_no|escape:'html':'UTF-8'} {$r.staff_name|escape:'html':'UTF-8'}<br><small class="text-muted">{$r.department|escape:'html':'UTF-8'}</small>{else}<span class="label label-warning">unmatched</span>{/if}</td>
  <td><code>{$r.employee_ref|escape:'html':'UTF-8'}</code></td>
  <td>{$r.device_name|escape:'html':'UTF-8'}<br><small class="text-muted">{$r.device_serial|escape:'html':'UTF-8'}</small></td>
  <td>{$r.direction}</td><td>{$r.verify_mode}</td>
  <td><span class="label {if $r.source=='device'}label-default{else}label-info{/if}">{$r.source}</span></td>
  <td>{$r.business_date}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_punch={$r.id_pulse_ta_punch}">Evidence</a></td>
</tr>
{foreachelse}<tr><td colspan="9"><em>No punches in that range.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ta-p-unmatched">
<p class="text-muted">A device user id that has been clocking but is not mapped to anyone. Map it and every punch it already made is re-attributed — nothing is lost and nothing is edited.</p>
<table class="table table-condensed"><thead><tr><th>Device id</th><th>Device</th><th>Punches</th><th>First</th><th>Last</th><th>Map to</th></tr></thead><tbody>
{foreach $unmatched_list as $u}
<tr><td><code>{$u.employee_ref|escape:'html':'UTF-8'}</code></td><td>{$u.device_name|escape:'html':'UTF-8'}</td><td>{$u.punches}</td><td>{$u.first_at|date_format:"%d/%m %H:%M"}</td><td>{$u.last_at|date_format:"%d/%m %H:%M"}</td>
<td><form method="post" class="form-inline"><input type="hidden" name="map_device" value="{$u.id_pulse_ta_device}"><input type="hidden" name="map_ref" value="{$u.employee_ref|escape:'html':'UTF-8'}">
<select name="map_staff" class="form-control input-sm">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} — {$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select>
<button name="mapRef" class="btn btn-xs btn-primary">Map</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>Every device id that has clocked is mapped to a person.</em></td></tr>{/foreach}
</tbody></table>
<p><a href="{$enrolment_url}">Enrolment</a> shows the same list with the staff who are not on any device yet.</p>
</div>

<div class="tab-pane" id="ta-p-add">
<form method="post" class="form-horizontal" style="max-width:640px">
  <div class="form-group"><label class="col-sm-3">Staff</label><div class="col-sm-9"><select name="id_staff_new" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} — {$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">Date</label><div class="col-sm-4"><input type="date" name="punch_date" class="form-control" value="{$business_date}"></div>
    <label class="col-sm-1">Time</label><div class="col-sm-4"><input type="time" name="punch_time" class="form-control" step="1"></div></div>
  <div class="form-group"><label class="col-sm-3">Direction</label><div class="col-sm-9"><select name="direction" class="form-control"><option value="in">In</option><option value="out">Out</option><option value="break_out">Break out</option><option value="break_in">Break in</option><option value="unknown">Let the engine decide</option></select></div></div>
  <div class="col-sm-offset-3"><button name="addPunch" class="btn btn-primary">Record punch</button></div>
</form>
<hr>
<form method="post" class="form-inline">
  <strong>Rebuild one timesheet:</strong>
  <select name="rb_staff" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} — {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input type="date" name="rb_date" class="form-control" value="{$business_date}">
  <button name="rebuildFor" class="btn btn-default">Rebuild</button>
</form>
</div>

</div></div></div>
