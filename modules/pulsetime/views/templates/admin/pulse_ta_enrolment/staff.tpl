<div class="pulse-ta"><div class="panel">
<h3><i class="icon-user"></i> {$s.staff_no|escape:'html':'UTF-8'} — {$s.firstname|escape:'html':'UTF-8'} {$s.lastname|escape:'html':'UTF-8'}
<a class="btn btn-xs btn-default pull-right" href="{$self_url}">Back to the list</a></h3>
{if $s.source=='hr'}<div class="alert alert-info">This record is mirrored from Pulse HR. Edit the person there; shift, rates and device mappings are owned here.</div>{/if}

<div class="row">
<div class="col-md-5">
<form method="post" class="form-horizontal">
<input type="hidden" name="id_staff_save" value="{$s.id_pulse_ta_staff}">
<div class="form-group"><label class="col-sm-4">Staff number</label><div class="col-sm-8"><input name="staff_no" class="form-control" value="{$s.staff_no|escape:'html':'UTF-8'}"></div></div>
<div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-4"><input name="firstname" class="form-control" value="{$s.firstname|escape:'html':'UTF-8'}"></div><div class="col-sm-4"><input name="lastname" class="form-control" value="{$s.lastname|escape:'html':'UTF-8'}"></div></div>
<div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><select name="department_s" class="form-control">{foreach $departments as $k => $v}<option value="{$k}" {if $s.department==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Section / position</label><div class="col-sm-4"><input name="section" class="form-control" value="{$s.section|escape:'html':'UTF-8'}"></div><div class="col-sm-4"><input name="position" class="form-control" value="{$s.position|escape:'html':'UTF-8'}"></div></div>
<div class="form-group"><label class="col-sm-4">Default shift</label><div class="col-sm-8"><select name="id_shift" class="form-control"><option value="0">Property default</option>{foreach $shifts as $sh}<option value="{$sh.id_pulse_ta_shift}" {if $s.id_pulse_ta_shift==$sh.id_pulse_ta_shift}selected{/if}>{$sh.code|escape:'html':'UTF-8'} — {$sh.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <span class="help-block">Used on any day with no roster row, so a night worker is never paired against an 08:00 day shift by accident.</span></div></div>
<div class="form-group"><label class="col-sm-4">Pay basis</label><div class="col-sm-8"><select name="pay_basis" class="form-control">{foreach ['monthly','daily','hourly','shift'] as $b}<option value="{$b}" {if $s.pay_basis==$b}selected{/if}>{$b}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Hourly / daily rate</label><div class="col-sm-4"><input name="hourly_rate" type="number" step="0.01" class="form-control" value="{$s.hourly_rate}"></div><div class="col-sm-4"><input name="daily_rate" type="number" step="0.01" class="form-control" value="{$s.daily_rate}"></div></div>
<div class="form-group"><label class="col-sm-4">Status</label><div class="col-sm-4"><select name="staff_status" class="form-control">{foreach ['active','suspended','exited'] as $st}<option value="{$st}" {if $s.status==$st}selected{/if}>{$st}</option>{/foreach}</select></div>
  <div class="col-sm-4"><input type="date" name="exit_date" class="form-control" value="{$s.exit_date}"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><label class="checkbox-inline"><input type="checkbox" name="ot_eligible" value="1" {if $s.ot_eligible}checked{/if}> Eligible for overtime</label></div></div>
<div class="form-group"><label class="col-sm-4">Back-office / POS ids</label><div class="col-sm-4"><input name="id_employee_link" type="number" class="form-control" value="{$s.id_employee}"></div><div class="col-sm-4"><input name="id_pos_staff" type="number" class="form-control" value="{$s.id_pos_staff}"></div></div>
<div class="col-sm-offset-4"><button name="saveStaff" class="btn btn-primary">Save</button></div>
</form>
</div>

<div class="col-md-7">
<h4>Readers this person is enrolled on</h4>
<table class="table table-condensed"><thead><tr><th>Device</th><th>Device id</th><th>Card</th><th>Finger</th><th>Face</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $enrolments as $e}
<tr class="{if $e.status=='failed'}danger{elseif $e.status=='removed'}text-muted{/if}">
  <td>{$e.device_name_full|escape:'html':'UTF-8'} <small class="text-muted">({$e.mode})</small></td>
  <td><code>{$e.device_user_id|escape:'html':'UTF-8'}</code></td>
  <td>{$e.card_no|escape:'html':'UTF-8'}</td><td>{if $e.has_finger}✔{/if}</td><td>{if $e.has_face}✔{/if}</td>
  <td>{$e.status}{if $e.last_error}<br><small class="text-danger">{$e.last_error|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><form method="post" class="form-inline"><input type="hidden" name="id_staff_act" value="{$s.id_pulse_ta_staff}"><input type="hidden" name="id_device_act" value="{$e.id_pulse_ta_device}">
    <button name="pushOne" class="btn btn-xs btn-default">Re-push</button>
    <button name="revokeOne" class="btn btn-xs btn-link" onclick="return confirm('Remove this person from the device?')">Remove</button></form></td>
</tr>
{foreachelse}<tr><td colspan="7"><em>Not on any reader yet.</em></td></tr>{/foreach}
</tbody></table>

<form method="post" class="form-inline" style="margin-bottom:14px">
  <input type="hidden" name="id_staff_act" value="{$s.id_pulse_ta_staff}">
  <select name="id_device_act" class="form-control">{foreach $devices as $d}<option value="{$d.id_pulse_ta_device}">{$d.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input name="device_user_id" class="form-control" placeholder="device id (blank = derive)" style="width:180px">
  <input name="card_no" class="form-control" placeholder="card no" style="width:110px">
  <button name="pushOne" class="btn btn-primary btn-sm">Enrol on that device</button>
  <button name="saveMapping" class="btn btn-default btn-sm" title="Record the mapping without writing to the device">Map only</button>
</form>
<form method="post" class="form-inline">
  <input type="hidden" name="id_staff_act" value="{$s.id_pulse_ta_staff}"><input type="hidden" name="only_device" value="0">
  <button name="provisionOne" class="btn btn-default btn-sm">Enrol on every active device</button>
  <input name="revoke_reason" class="form-control" placeholder="reason" style="width:160px">
  <button name="revokeAll" class="btn btn-danger btn-sm" onclick="return confirm('Remove this person from every reader? Do this the day they leave.')">Remove from every reader</button>
</form>

<h4 style="margin-top:20px">Last two weeks of punches</h4>
<table class="table table-condensed"><thead><tr><th>Punched at</th><th>Device</th><th>Dir</th><th>Verify</th><th>Source</th></tr></thead><tbody>
{foreach $recent as $p}
<tr><td>{$p.punched_at|date_format:"%d/%m %H:%M:%S"}</td><td>{$p.device_name|escape:'html':'UTF-8'}</td><td>{$p.direction}</td><td>{$p.verify_mode}</td><td>{$p.source}</td></tr>
{foreachelse}<tr><td colspan="5"><em>No punches recorded.</em></td></tr>{/foreach}
</tbody></table>
</div>
</div>
</div></div>
