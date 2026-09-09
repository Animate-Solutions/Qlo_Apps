<div class="pulse-ta"><div class="panel">
<h3><i class="icon-wrench"></i> Fix exception #{$e.id_pulse_ta_exception} <a class="btn btn-xs btn-default pull-right" href="{$self_url}">Back to the queue</a></h3>
{if !$e}<p><em>That exception no longer exists.</em></p>{else}
<div class="row">
<div class="col-md-5">
<table class="table table-condensed">
  <tr><th style="width:38%">Business date</th><td><strong>{$e.business_date}</strong></td></tr>
  <tr><th>Staff</th><td>{$e.staff_no|escape:'html':'UTF-8'} — {$e.staff_name|escape:'html':'UTF-8'} <small class="text-muted">({$e.dept|escape:'html':'UTF-8'})</small></td></tr>
  <tr><th>Type</th><td>{$e.type}</td></tr>
  <tr><th>Severity</th><td>{if $e.severity=='block'}<span class="label label-danger">blocks approval</span>{else}<span class="label label-default">{$e.severity}</span>{/if}</td></tr>
  <tr><th>Detail</th><td>{$e.detail|escape:'html':'UTF-8'}</td></tr>
  <tr><th>Rostered shift</th><td>{if $e.shift_code}{$e.shift_code|escape:'html':'UTF-8'} {$e.shift_start|date_format:"%d/%m %H:%M"} → {$e.shift_end|date_format:"%d/%m %H:%M"}{else}—{/if}</td></tr>
  <tr><th>Recorded</th><td>{if $e.first_in}{$e.first_in|date_format:"%d/%m %H:%M"}{else}no in{/if} → {if $e.last_out}{$e.last_out|date_format:"%d/%m %H:%M"}{else}no out{/if}</td></tr>
  <tr><th>Worked so far</th><td>{$e.worked_minutes} min</td></tr>
  <tr><th>Status</th><td>{$e.status}{if $e.locked} — <span class="label label-success">period locked</span>{/if}</td></tr>
</table>
{if $pos}<h4>POS clock for the same window</h4>
<table class="table table-condensed"><thead><tr><th>Clock in</th><th>Clock out</th></tr></thead><tbody>
{foreach $pos as $c}<tr><td>{$c.clock_in}</td><td>{if $c.clock_out}{$c.clock_out}{else}<em>still open</em>{/if}</td></tr>{/foreach}
</tbody></table>
<p class="text-muted">A POS clock-in is not a competing truth — it is a second witness. Use it to justify the punch time you add.</p>{/if}
</div>

<div class="col-md-4">
<h4>Punches the engine read that day</h4>
<table class="table table-condensed"><thead><tr><th>Time</th><th>Role</th><th>Source</th><th>Device</th></tr></thead><tbody>
{foreach $punches as $p}
<tr class="{if $p.role=='ignored'}text-muted{/if}"><td>{$p.punched_at|date_format:"%d/%m %H:%M:%S"}</td><td>{$p.role}</td><td>{$p.source}</td><td>{$p.device_name|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="4"><em>No punches at all. That is the exception.</em></td></tr>{/foreach}
</tbody></table>

{if $trail}
<h4>Corrections already on this day</h4>
<table class="table table-condensed"><thead><tr><th>Type</th><th>Detail</th><th>Approver</th><th></th></tr></thead><tbody>
{foreach $trail as $a}
<tr class="{if $a.status=='void'}text-muted{/if}"><td>{$a.type}</td>
<td>{if $a.punched_at}{$a.punched_at|date_format:"%d/%m %H:%M"} {$a.direction}{/if}{if $a.minutes} {$a.minutes} min{/if}<br><small>{$a.reason|escape:'html':'UTF-8'}</small></td>
<td>{$a.approver|escape:'html':'UTF-8'}<br><small class="text-muted">{$a.approved_at|date_format:"%d/%m %H:%M"}</small></td>
<td>{if $a.status=='approved'}<form method="post" class="form-inline"><input type="hidden" name="id_adjustment" value="{$a.id_pulse_ta_adjustment}"><input name="void_reason" class="input-sm" placeholder="reason" style="width:90px"><button name="voidAdjustment" class="btn btn-xs btn-link">void</button></form>{else}<small>{$a.status}</small>{/if}</td></tr>
{/foreach}
</tbody></table>
{/if}
</div>

<div class="col-md-3">
<h4>Correct it</h4>
{if $e.locked}<div class="alert alert-warning">This date is inside an approved, locked period. Reopen the period on the Timesheets screen before correcting it — reopening is recorded with your name and a reason.</div>
{elseif $e.status != 'open'}<div class="alert alert-info">Already {$e.status}.</div>
{else}
<form method="post">
<input type="hidden" name="id_exception_r" value="{$e.id_pulse_ta_exception}">
<div class="form-group"><label>What happened</label>
<select name="how" class="form-control" id="ta-how">
  <option value="add_punch">Add the missing punch</option>
  <option value="ignore_punch">Ignore a stray punch</option>
  <option value="set_minutes">Set the worked minutes</option>
  <option value="add_overtime">Add approved overtime</option>
  <option value="paid_absence">Paid absence (leave, sick with cover)</option>
  <option value="unpaid_absence">Unpaid absence</option>
  <option value="waive_late">Waive the lateness</option>
  <option value="waive">Waive — the record is right</option>
</select></div>
<div class="form-group"><label>Punch date &amp; time</label>
  <input type="date" name="adj_date" class="form-control" value="{$e.business_date}">
  <input type="time" name="adj_time" class="form-control" step="60"></div>
<div class="form-group"><label>Direction</label><select name="adj_direction" class="form-control"><option value="out">Out</option><option value="in">In</option><option value="break_out">Break out</option><option value="break_in">Break in</option><option value="unknown">Let the engine decide</option></select></div>
<div class="form-group"><label>Minutes (for set / overtime)</label><input type="number" name="adj_minutes" class="form-control" value="0"></div>
<div class="form-group"><label>Punch to ignore</label><select name="adj_id_punch" class="form-control"><option value="">—</option>{foreach $punches as $p}<option value="{$p.id_pulse_ta_punch}">{$p.punched_at|date_format:"%H:%M:%S"} {$p.role}</option>{/foreach}</select></div>
<div class="form-group"><label>Reason <span class="text-danger">*</span></label><textarea name="reason" class="form-control" rows="3" placeholder="Who confirmed it, and how you know. This is what an auditor reads."></textarea></div>
<button name="resolveException" class="btn btn-primary btn-block">Record the correction and rebuild</button>
</form>
<p class="text-muted" style="margin-top:10px">The correction is stored as an adjustment with your name against it. The original punches stay exactly as the device sent them.</p>
{/if}
</div>
</div>
{/if}
</div></div>
