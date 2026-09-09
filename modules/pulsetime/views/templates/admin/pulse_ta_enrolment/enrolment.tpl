<div class="pulse-ta"><div class="panel"><h3><i class="icon-group"></i> Enrolment</h3>
{if $hr}<p class="text-muted">Pulse HR owns the employee record; this list is mirrored from it and is what the pairing engine reads. Press <em>Mirror from HR</em> after a batch of hires.</p>
{else}<div class="alert alert-info">Pulse HR is not installed, so this local roster <strong>is</strong> the staff list for Time &amp; Attendance. Everything works normally; install Pulse HR later and these rows link up automatically by staff number.</div>{/if}

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-e-staff">Staff ({$staff|count})</a></li>
  <li><a data-toggle="tab" href="#ta-e-problems">Needs attention</a></li>
  <li><a data-toggle="tab" href="#ta-e-device">By device</a></li>
  <li><a data-toggle="tab" href="#ta-e-add">Add a staff member</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-e-staff">
<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaEnrolment"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $k => $v}<option value="{$k}" {if $department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <select name="status" class="form-control"><option value="active" {if $status=='active'}selected{/if}>Active</option><option value="suspended" {if $status=='suspended'}selected{/if}>Suspended</option><option value="exited" {if $status=='exited'}selected{/if}>Exited</option><option value="">Any</option></select>
  <input name="q" value="{$q|escape:'html':'UTF-8'}" class="form-control" placeholder="Name or staff number">
  <button class="btn btn-default">Filter</button>
</form>
<form method="post" style="display:inline"><button name="syncHr" class="btn btn-default btn-sm">Mirror from Pulse HR</button></form>

<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Position</th><th>Default shift</th><th>Basis</th><th>Devices</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $staff as $s}
<tr class="{if !$s.enrolments}warning{/if}">
  <td><strong>{$s.staff_no|escape:'html':'UTF-8'}</strong></td>
  <td><a href="{$self_url}&id_staff={$s.id_pulse_ta_staff}">{$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</a>{if $s.source=='hr'} <small class="text-muted">HR</small>{/if}</td>
  <td>{$s.department|escape:'html':'UTF-8'}</td><td>{$s.position|escape:'html':'UTF-8'}</td>
  <td>{if $s.shift_code}{$s.shift_code|escape:'html':'UTF-8'}{else}<small class="text-muted">property default</small>{/if}</td>
  <td>{$s.pay_basis}</td>
  <td>{if $s.enrolments}{$s.enrolments}{else}<span class="label label-warning">not on any reader</span>{/if}</td>
  <td>{$s.status}</td>
  <td><form method="post" class="form-inline"><input type="hidden" name="id_staff_act" value="{$s.id_pulse_ta_staff}"><input type="hidden" name="only_device" value="0">
    <button name="provisionOne" class="btn btn-xs btn-primary">Enrol on every device</button></form></td>
</tr>
{foreachelse}<tr><td colspan="9"><em>No staff yet. Add one on the last tab, or install Pulse HR and mirror.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="ta-e-problems">
<h4>Enrolments that failed ({$problems.failed|count})</h4>
<table class="table table-condensed"><thead><tr><th>Staff</th><th>Device</th><th>Device id</th><th>Attempts</th><th>Last error</th><th></th></tr></thead><tbody>
{foreach $problems.failed as $p}
<tr class="danger"><td>{$p.staff_no|escape:'html':'UTF-8'} {$p.staff_name|escape:'html':'UTF-8'}</td><td>{$p.device_name_full|escape:'html':'UTF-8'}</td><td><code>{$p.device_user_id|escape:'html':'UTF-8'}</code></td>
<td>{$p.attempts}</td><td><small>{$p.last_error|escape:'html':'UTF-8'}</small></td>
<td><form method="post"><input type="hidden" name="id_staff_act" value="{$p.id_pulse_ta_staff}"><input type="hidden" name="id_device_act" value="{$p.id_pulse_ta_device}"><button name="pushOne" class="btn btn-xs btn-default">Retry</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>None.</em></td></tr>{/foreach}
</tbody></table>

<h4>Device user ids nobody is mapped to ({$problems.unmatched_punches|count} clocking, {$problems.unmapped|count} known to a device)</h4>
<table class="table table-condensed"><thead><tr><th>Device id</th><th>Device</th><th>Punches</th><th>First</th><th>Last</th><th>Map to</th></tr></thead><tbody>
{foreach $problems.unmatched_punches as $u}
<tr class="warning"><td><code>{$u.employee_ref|escape:'html':'UTF-8'}</code></td><td>{$u.device_name|escape:'html':'UTF-8'}</td><td>{$u.punches}</td>
<td>{$u.first_at|date_format:"%d/%m %H:%M"}</td><td>{$u.last_at|date_format:"%d/%m %H:%M"}</td>
<td><form method="post" class="form-inline"><input type="hidden" name="map_device" value="{$u.id_pulse_ta_device}"><input type="hidden" name="map_ref" value="{$u.employee_ref|escape:'html':'UTF-8'}">
<select name="map_staff" class="form-control input-sm">{foreach $staff as $s}<option value="{$s.id_pulse_ta_staff}">{$s.staff_no|escape:'html':'UTF-8'} — {$s.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select>
<button name="mapRef" class="btn btn-xs btn-primary">Map</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>Every id that has clocked belongs to somebody.</em></td></tr>{/foreach}
</tbody></table>

<h4>Active staff on no reader at all ({$problems.not_enrolled|count})</h4>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th></th></tr></thead><tbody>
{foreach $problems.not_enrolled as $s}
<tr class="warning"><td>{$s.staff_no|escape:'html':'UTF-8'}</td><td>{$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}</td><td>{$s.department|escape:'html':'UTF-8'}</td>
<td><form method="post"><input type="hidden" name="id_staff_act" value="{$s.id_pulse_ta_staff}"><input type="hidden" name="only_device" value="0"><button name="provisionOne" class="btn btn-xs btn-primary">Enrol on every device</button></form></td></tr>
{foreachelse}<tr><td colspan="4"><em>Everyone active is on at least one reader.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">Writing the identity to a reader is not the same as capturing a finger or a face — the biometric itself is always enrolled at the device. What this does is create the user so the template has somewhere to attach, and tell Pulse which id that person will punch under.</p>
</div>

<div class="tab-pane" id="ta-e-device">
<form method="get" class="form-inline">
  <input type="hidden" name="controller" value="AdminPulseTaEnrolment"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <select name="id_device" class="form-control"><option value="">Choose a device</option>{foreach $all_devices as $d}<option value="{$d.id_pulse_ta_device}" {if $id_device==$d.id_pulse_ta_device}selected{/if}>{$d.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <button class="btn btn-default">Show</button>
</form>
{if $id_device}
<form method="post" style="margin:8px 0"><input type="hidden" name="id_device_act" value="{$id_device}"><button name="reconcile" class="btn btn-default btn-sm">Read the device's own user list and reconcile</button></form>
<table class="table table-condensed"><thead><tr><th>Device id</th><th>Mapped to</th><th>Name on device</th><th>Card</th><th>Finger</th><th>Face</th><th>Status</th><th>Last error</th></tr></thead><tbody>
{foreach $device_rows as $r}
<tr class="{if !$r.id_pulse_ta_staff}warning{elseif $r.status=='failed'}danger{/if}">
  <td><code>{$r.device_user_id|escape:'html':'UTF-8'}</code></td>
  <td>{if $r.staff_no}{$r.staff_no|escape:'html':'UTF-8'} {$r.firstname|escape:'html':'UTF-8'} {$r.lastname|escape:'html':'UTF-8'}{else}<span class="label label-warning">unmapped</span>{/if}</td>
  <td>{$r.device_name|escape:'html':'UTF-8'}</td><td>{$r.card_no|escape:'html':'UTF-8'}</td>
  <td>{if $r.has_finger}✔{/if}</td><td>{if $r.has_face}✔{/if}</td><td>{$r.status}</td><td><small>{$r.last_error|escape:'html':'UTF-8'}</small></td>
</tr>
{foreachelse}<tr><td colspan="8"><em>Nobody enrolled on this device yet.</em></td></tr>{/foreach}
</tbody></table>
{/if}
</div>

<div class="tab-pane" id="ta-e-add">
<form method="post" class="form-horizontal" style="max-width:760px">
<input type="hidden" name="id_staff_save" value="0">
<div class="form-group"><label class="col-sm-3">Staff number</label><div class="col-sm-4"><input name="staff_no" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-3">Name</label><div class="col-sm-4"><input name="firstname" class="form-control" placeholder="First name"></div><div class="col-sm-4"><input name="lastname" class="form-control" placeholder="Surname"></div></div>
<div class="form-group"><label class="col-sm-3">Department</label><div class="col-sm-4"><select name="department_s" class="form-control">{foreach $departments as $k => $v}<option value="{$k}">{$v}</option>{/foreach}</select></div>
  <div class="col-sm-4"><input name="section" class="form-control" placeholder="Section (optional)"></div></div>
<div class="form-group"><label class="col-sm-3">Position</label><div class="col-sm-8"><input name="position" class="form-control" placeholder="e.g. Room Attendant"></div></div>
<div class="form-group"><label class="col-sm-3">Default shift</label><div class="col-sm-4"><select name="id_shift" class="form-control"><option value="0">Property default</option>{foreach $shifts as $sh}<option value="{$sh.id_pulse_ta_shift}">{$sh.code|escape:'html':'UTF-8'} — {$sh.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3">Pay basis</label><div class="col-sm-4"><select name="pay_basis" class="form-control"><option value="monthly">Monthly</option><option value="daily">Daily</option><option value="hourly">Hourly</option><option value="shift">Per shift</option></select></div></div>
<div class="form-group"><label class="col-sm-3">Hourly / daily rate</label><div class="col-sm-4"><input name="hourly_rate" type="number" step="0.01" class="form-control" placeholder="0.00"></div><div class="col-sm-4"><input name="daily_rate" type="number" step="0.01" class="form-control" placeholder="0.00"></div></div>
<div class="form-group"><div class="col-sm-offset-3 col-sm-8"><label class="checkbox-inline"><input type="checkbox" name="ot_eligible" value="1" checked> Eligible for overtime</label></div></div>
<div class="form-group"><label class="col-sm-3">Back-office user id</label><div class="col-sm-4"><input name="id_employee_link" type="number" class="form-control" placeholder="optional"></div>
  <div class="col-sm-4"><input name="id_pos_staff" type="number" class="form-control" placeholder="POS staff id (optional)"></div></div>
<div class="col-sm-offset-3"><button name="saveStaff" class="btn btn-primary">Add staff member</button></div>
</form>
</div>

</div></div></div>
