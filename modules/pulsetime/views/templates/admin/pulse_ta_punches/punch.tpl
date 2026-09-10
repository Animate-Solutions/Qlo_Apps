<div class="pulse-ta"><div class="panel">
<h3><i class="icon-file-text"></i> Punch #{$p.id_pulse_ta_punch} — evidence <a class="btn btn-xs btn-default pull-right" href="{$self_url}">Back to the register</a></h3>
{if !$p}<p><em>That punch no longer exists.</em></p>{else}
<div class="row"><div class="col-md-6">
<table class="table table-condensed">
  <tr><th style="width:38%">Punched at (shop time)</th><td><strong>{$p.punched_at}</strong></td></tr>
  <tr><th>Device local time as recorded</th><td>{if $p.device_time}{$p.device_time}{else}—{/if}</td></tr>
  <tr><th>Staff</th><td>{if $p.staff_name}{$p.staff_no|escape:'html':'UTF-8'} — {$p.staff_name|escape:'html':'UTF-8'} <small class="text-muted">({$p.department|escape:'html':'UTF-8'})</small>{else}<span class="label label-warning">not mapped to anyone</span>{/if}</td></tr>
  <tr><th>Device user id</th><td><code>{$p.employee_ref|escape:'html':'UTF-8'}</code></td></tr>
  <tr><th>Device</th><td>{$p.device_name|escape:'html':'UTF-8'} <small class="text-muted">serial {$p.device_serial|escape:'html':'UTF-8'}</small></td></tr>
  <tr><th>Direction</th><td>{$p.direction}</td></tr>
  <tr><th>Verify mode</th><td>{$p.verify_mode}</td></tr>
  <tr><th>Work code</th><td>{$p.work_code|escape:'html':'UTF-8'}</td></tr>
  <tr><th>Source</th><td><span class="label {if $p.source=='device'}label-default{else}label-info{/if}">{$p.source}</span></td></tr>
  {if $p.latitude}<tr><th>Coordinates</th><td>{$p.latitude}, {$p.longitude}{if $p.accuracy_m} <small class="text-muted">±{$p.accuracy_m} m</small>{/if}</td></tr>{/if}
  <tr><th>Business date at ingest</th><td>{$p.business_date}</td></tr>
  <tr><th>Recorded in Pulse at</th><td>{$p.date_add}</td></tr>
  <tr><th>De-duplication hash</th><td><code style="font-size:11px">{$p.dedupe_hash}</code></td></tr>
</table>
</div><div class="col-md-6">
<h4>Raw payload from the device</h4>
<p class="text-muted">Exactly what the reader sent, kept verbatim so a disputed punch can be checked against the device's own log.</p>
<pre style="white-space:pre-wrap;word-break:break-all">{$p.raw|escape:'html':'UTF-8'}</pre>
{if $timesheet}
<h4>Timesheet this punch was read into</h4>
<table class="table table-condensed">
  <tr><th>Business date</th><td>{$timesheet.business_date}</td></tr>
  <tr><th>Shift</th><td>{$timesheet.shift_code|escape:'html':'UTF-8'} {if $timesheet.shift_start}{$timesheet.shift_start|date_format:"%H:%M"}–{$timesheet.shift_end|date_format:"%H:%M"}{/if}{if $timesheet.crosses_midnight} <small class="text-muted">crosses midnight</small>{/if}</td></tr>
  <tr><th>First in / last out</th><td>{$timesheet.first_in|date_format:"%H:%M"} → {$timesheet.last_out|date_format:"%H:%M"}</td></tr>
  <tr><th>Worked</th><td>{$timesheet.worked_minutes} min</td></tr>
  <tr><th>Status</th><td>{$timesheet.status}{if $timesheet.locked} <span class="label label-success">period locked</span>{/if}</td></tr>
</table>
{/if}
</div></div>
{/if}
</div></div>
