<div class="pulse-ta"><div class="panel"><h3><i class="icon-hdd"></i> Clocking devices</h3>

<div class="alert {if $push_enabled}alert-info{else}alert-warning{/if}">
  <strong>Push endpoint:</strong> <code>{$push_url|escape:'html':'UTF-8'}</code>
  {if $push_enabled}enabled{else}<strong>disabled</strong> — turn it on in <a href="{$settings_url}">T&amp;A Settings</a> before pointing a device at it{/if}.
  Set a ZKTeco ADMS device's <em>Comm ▸ Server</em> page to this host and path; the firmware appends <code>/cdata</code>, <code>/getrequest</code> and <code>/devicecmd</code> itself.
  {if $require_key} A shared key is required as <code>?{$key_param|escape:'html':'UTF-8'}=…</code>.{/if}
  An unknown serial registers as <strong>pending</strong> and delivers nothing until it is claimed here.
</div>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#ta-d-list">Fleet ({$devices|count})</a></li>
  <li><a data-toggle="tab" href="#ta-d-form">{if $edit}Edit "{$edit.name|escape:'html':'UTF-8'}"{else}Add a device{/if}</a></li>
  <li><a data-toggle="tab" href="#ta-d-traffic">Push traffic ({$traffic|count})</a></li>
  <li><a data-toggle="tab" href="#ta-d-queue">Queue ({$jobs|count})</a></li>
  <li><a data-toggle="tab" href="#ta-d-diag">Diagnostics</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="ta-d-list">
<table class="table table-condensed"><thead><tr><th>Device</th><th>Adapter</th><th>Where</th><th>Address</th><th>Serial</th><th>Mode</th><th>Health</th><th>Last seen</th><th>Punches</th><th>Enrolled</th><th>Actions</th></tr></thead><tbody>
{foreach $devices as $d}
<tr class="{if $d.status=='pending'}warning{elseif $d.status=='blocked'}danger{elseif $d.health=='offline'}danger{elseif $d.stale}warning{/if}">
  <td><a href="{$self_url}&id_device={$d.id_pulse_ta_device}"><strong>{$d.name|escape:'html':'UTF-8'}</strong></a>
      {if $d.test_mode}<br><span class="label label-info">test mode</span>{/if}
      {if $d.note}<br><small class="text-muted">{$d.note|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><small>{if isset($d.capabilities.vendor)}{$d.capabilities.vendor|escape:'html':'UTF-8'}{else}{$d.adapter|escape:'html':'UTF-8'}{/if}</small>
      {if isset($d.capabilities.verify_note)}<br><small class="text-warning" title="{$d.capabilities.verify_note|escape:'html':'UTF-8'}">verify against your firmware</small>{/if}</td>
  <td>{$d.location|escape:'html':'UTF-8'}{if $d.department}<br><small class="text-muted">{$d.department|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><small>{if $d.mode=='push'}the device dials us{elseif $d.protocol=='file'}{else}{$d.protocol}://{$d.host|escape:'html':'UTF-8'}:{$d.port}{/if}</small></td>
  <td><code>{$d.serial|escape:'html':'UTF-8'}</code></td>
  <td>{$d.mode}{if $d.queued_cmds}<br><span class="badge">{$d.queued_cmds} queued</span>{/if}</td>
  <td>{if $d.status=='pending'}<span class="label label-warning">PENDING</span>
      {elseif $d.status=='blocked'}<span class="label label-danger">blocked</span>
      {elseif $d.health=='online'}<span class="label label-success">online</span>
      {elseif $d.health=='degraded'}<span class="label label-warning">degraded</span>
      {elseif $d.health=='offline'}<span class="label label-danger">offline</span>
      {else}<span class="label label-default">unknown</span>{/if}
      {if $d.last_error}<br><small class="text-danger">{$d.last_error|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{if $d.last_seen_at}{$d.last_seen_at|date_format:"%d/%m %H:%M"}{if $d.silent_min !== null}<br><small class="text-muted">{$d.silent_min} min ago</small>{/if}{else}never{/if}</td>
  <td>{$d.punch_count}{if $d.last_punch_at}<br><small class="text-muted">{$d.last_punch_at|date_format:"%d/%m %H:%M"}</small>{/if}</td>
  <td>{$d.enrolled}{if $d.device_users}<br><small class="text-muted">{$d.device_users} on device</small>{/if}</td>
  <td><form method="post" class="ta-acts"><input type="hidden" name="id_device_act" value="{$d.id_pulse_ta_device}">
    <button name="testDevice" class="btn btn-xs btn-default">Test</button>
    {if $d.status=='active'}<button name="pollDevice" class="btn btn-xs btn-default">Poll</button>{/if}
    {if isset($d.capabilities.sync_time) && $d.capabilities.sync_time}<button name="syncTime" class="btn btn-xs btn-default">Sync time</button>{/if}
    {if isset($d.capabilities.pull_users) && $d.capabilities.pull_users}<button name="reconcileDevice" class="btn btn-xs btn-default">Reconcile users</button>{/if}
    {if $d.status=='pending'}<br><input name="claim_location" class="input-sm" placeholder="where is it?" style="width:110px"><button name="claimDevice" class="btn btn-xs btn-primary">Claim</button>{/if}
    {if $d.status!='blocked'}<br><input name="block_reason" class="input-sm" placeholder="reason" style="width:110px"><button name="blockDevice" class="btn btn-xs btn-link" onclick="return confirm('Block this device? It will no longer be able to deliver punches.')">Block</button>{/if}
  </form></td>
</tr>
{/foreach}
</tbody></table>
<form method="post" class="noprint"><button name="pollAll" class="btn btn-default"><i class="icon-refresh"></i> Poll every device now</button></form>
</div>

<div class="tab-pane" id="ta-d-form">
<form method="post" class="form-horizontal">
<input type="hidden" name="id_device_save" value="{if $edit}{$edit.id_pulse_ta_device}{else}0{/if}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="name" class="form-control" value="{if $edit}{$edit.name|escape:'html':'UTF-8'}{/if}" required></div></div>
  <div class="form-group"><label class="col-sm-4">Adapter</label><div class="col-sm-8"><select name="adapter" class="form-control" id="ta-adapter">{foreach $adapters as $k => $v}<option value="{$k}" {if $edit && $edit.adapter==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Location</label><div class="col-sm-8"><input name="location" class="form-control" value="{if $edit}{$edit.location|escape:'html':'UTF-8'}{else}Staff entrance{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><select name="department" class="form-control"><option value="">Any</option>{foreach $departments as $k => $v}<option value="{$k}" {if $edit && $edit.department==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Mode</label><div class="col-sm-8"><select name="mode" class="form-control"><option value="pull" {if $edit && $edit.mode=='pull'}selected{/if}>Pull — we poll it</option><option value="push" {if $edit && $edit.mode=='push'}selected{/if}>Push — it dials us</option><option value="file" {if $edit && $edit.mode=='file'}selected{/if}>File import</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Protocol</label><div class="col-sm-8"><select name="protocol" class="form-control">{foreach $protocols as $p}<option value="{$p}" {if $edit && $edit.protocol==$p}selected{/if}>{$p}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Host</label><div class="col-sm-8"><input name="host" class="form-control" value="{if $edit}{$edit.host|escape:'html':'UTF-8'}{/if}" placeholder="192.168.1.201"></div></div>
  <div class="form-group"><label class="col-sm-4">Port</label><div class="col-sm-8"><input name="port" type="number" class="form-control" value="{if $edit}{$edit.port}{else}4370{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Endpoint / path</label><div class="col-sm-8"><input name="endpoint" class="form-control" value="{if $edit}{$edit.endpoint|escape:'html':'UTF-8'}{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Serial (SN)</label><div class="col-sm-8"><input name="serial" class="form-control" value="{if $edit}{$edit.serial|escape:'html':'UTF-8'}{/if}"><span class="help-block">Required for a push device — it is the only identity the firmware presents.</span></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Device timezone</label><div class="col-sm-8"><select name="timezone" class="form-control">{foreach $timezones as $tz}<option value="{$tz}" {if $edit && $edit.timezone==$tz}selected{/if}>{$tz}</option>{/foreach}</select><span class="help-block">Punches are converted from this into the shop timezone. A wrong value shifts every punch by hours.</span></div></div>
  <div class="form-group"><label class="col-sm-4">Direction</label><div class="col-sm-8"><select name="direction_mode" class="form-control">
    <option value="both" {if $edit && $edit.direction_mode=='both'}selected{/if}>Trust the device's in/out state</option>
    <option value="auto" {if $edit && $edit.direction_mode=='auto'}selected{/if}>Alternate in / out through the shift</option>
    <option value="in" {if $edit && $edit.direction_mode=='in'}selected{/if}>This reader is IN only</option>
    <option value="out" {if $edit && $edit.direction_mode=='out'}selected{/if}>This reader is OUT only</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Poll every (min)</label><div class="col-sm-8"><input name="poll_interval_min" type="number" class="form-control" value="{if $edit}{$edit.poll_interval_min}{else}10{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Timeout (s) / retries</label><div class="col-sm-4"><input name="timeout_sec" type="number" class="form-control" value="{if $edit}{$edit.timeout_sec}{else}8{/if}"></div><div class="col-sm-4"><input name="retries" type="number" class="form-control" value="{if $edit}{$edit.retries}{else}2{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Status</label><div class="col-sm-8"><select name="status" class="form-control"><option value="pending" {if $edit && $edit.status=='pending'}selected{/if}>pending</option><option value="active" {if $edit && $edit.status=='active'}selected{/if}>active</option><option value="blocked" {if $edit && $edit.status=='blocked'}selected{/if}>blocked</option></select></div></div>
  <div class="form-group"><div class="col-sm-offset-4 col-sm-8">
    <label class="checkbox-inline"><input type="checkbox" name="test_mode" value="1" {if $edit && $edit.test_mode}checked{/if}> Test mode (no writes reach the hardware)</label><br>
    <label class="checkbox-inline"><input type="checkbox" name="clear_after_pull" value="1" {if $edit && $edit.clear_after_pull}checked{/if}> Clear the device log after a clean pull</label>
    <span class="help-block text-danger">Leave "clear after pull" off unless the device is filling up. The reader's own log is the only copy of a punch until Pulse has it.</span></div></div>
  <div class="form-group"><label class="col-sm-4">Note</label><div class="col-sm-8"><input name="note" class="form-control" value="{if $edit}{$edit.note|escape:'html':'UTF-8'}{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Options (JSON)</label><div class="col-sm-8"><textarea name="options_json" class="form-control" rows="3" placeholder='{literal}{"record_size":40,"swap_state_verify":0,"allow_self_signed":0}{/literal}'>{if $edit}{$edit.options_json|escape:'html':'UTF-8'}{/if}</textarea>
    <span class="help-block">Adapter-specific settings. See the README for what each brand accepts.</span></div></div>
  <fieldset><legend style="font-size:14px">Credentials <small class="text-muted">stored encrypted; leave blank to keep the current value</small></legend>
    <div class="form-group"><label class="col-sm-4">User</label><div class="col-sm-8"><input name="cred_user" class="form-control" autocomplete="off"></div></div>
    <div class="form-group"><label class="col-sm-4">Password</label><div class="col-sm-8"><input name="cred_password" type="password" class="form-control" autocomplete="new-password"></div></div>
    <div class="form-group"><label class="col-sm-4">ZK comm key</label><div class="col-sm-8"><input name="cred_comm_key" class="form-control" autocomplete="off" placeholder="numeric, from the device menu"></div></div>
    <div class="form-group"><label class="col-sm-4">API key / secret</label><div class="col-sm-4"><input name="cred_api_key" class="form-control" autocomplete="off"></div><div class="col-sm-4"><input name="cred_api_secret" class="form-control" autocomplete="off"></div></div>
    <div class="form-group"><label class="col-sm-4">Push shared key</label><div class="col-sm-8"><input name="cred_push_key" class="form-control" autocomplete="off" placeholder="per-device override of the property key"></div></div>
  </fieldset>
</div></div>
<div class="col-sm-offset-2"><button name="saveDevice" class="btn btn-primary btn-lg">Save device</button> {if $edit}<a class="btn btn-default" href="{$self_url}">Cancel</a>{/if}</div>
</form>
</div>

<div class="tab-pane" id="ta-d-traffic">
<p class="text-muted">Every inbound request to the push endpoint. This is the audit trail for a surface with no login, so it is kept for the retention period set in Settings. Device text is escaped on the way in and again here.</p>
<table class="table table-condensed"><thead><tr><th>When</th><th>Serial</th><th>Device</th><th>Path</th><th>Table</th><th>IP</th><th>Bytes</th><th>Rows</th><th>Kept</th><th>Result</th><th>Message / first line</th></tr></thead><tbody>
{foreach $traffic as $t}
<tr class="{if $t.result=='rejected' || $t.result=='rate_limited' || $t.result=='too_large'}danger{elseif $t.result=='pending_device'}warning{/if}">
  <td>{$t.date_add|date_format:"%d/%m %H:%M:%S"}</td><td><code>{$t.serial|escape:'html':'UTF-8'}</code></td><td>{$t.device_name|escape:'html':'UTF-8'}</td>
  <td>{$t.path|escape:'html':'UTF-8'}</td><td>{$t.table_name|escape:'html':'UTF-8'}</td><td>{$t.ip|escape:'html':'UTF-8'}</td>
  <td>{$t.bytes}</td><td>{$t.rows_in}</td><td>{$t.rows_kept}</td><td>{$t.result}</td>
  <td><small>{$t.message|escape:'html':'UTF-8'}</small>{if $t.sample}<br><code style="font-size:11px">{$t.sample|escape:'html':'UTF-8'}</code>{/if}</td>
</tr>
{foreachelse}<tr><td colspan="11"><em>No device has called in yet.</em></td></tr>{/foreach}
</tbody></table>
{if $commands}
<h4>ADMS command queue for the selected device</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Kind</th><th>Command</th><th>Status</th><th>Sent</th><th>Replied</th><th>Return</th></tr></thead><tbody>
{foreach $commands as $c}<tr><td>{$c.id_pulse_ta_device_cmd}</td><td>{$c.kind}</td><td><code style="font-size:11px">{$c.cmd|escape:'html':'UTF-8'|truncate:110}</code></td><td>{$c.status}</td><td>{$c.sent_at}</td><td>{$c.replied_at}</td><td>{$c.return_code|escape:'html':'UTF-8'}</td></tr>{/foreach}
</tbody></table>
{/if}
{if $edit}
<form method="post" class="form-inline"><input type="hidden" name="id_device_act" value="{$edit.id_pulse_ta_device}">
  <input name="adms_cmd" class="form-control" style="width:420px" placeholder="e.g. CHECK  or  DATA UPDATE USERINFO PIN=1042&#9;Name=Chidi">
  <button name="queueCmd" class="btn btn-default">Queue an ADMS command</button>
</form>
{/if}
</div>

<div class="tab-pane" id="ta-d-queue">
<table class="table table-condensed"><thead><tr><th>#</th><th>Type</th><th>Device</th><th>Staff</th><th>Attempts</th><th>Next try</th><th>Status</th><th>Last error</th></tr></thead><tbody>
{foreach $jobs as $j}
<tr class="{if $j.status=='failed'}danger{/if}"><td>{$j.id_pulse_ta_job}</td><td>{$j.type}</td><td>{$j.device_name|escape:'html':'UTF-8'}</td><td>{$j.staff_name|escape:'html':'UTF-8'}</td>
<td>{$j.attempts}</td><td>{$j.next_try_at}</td><td>{$j.status}</td><td><small>{$j.last_error|escape:'html':'UTF-8'}</small></td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing queued. Provisioning that fails on an unreachable device lands here and is retried with a growing back-off.</em></td></tr>{/foreach}
</tbody></table>
<form method="post"><button name="runQueue" class="btn btn-default">Run the queue now</button></form>
</div>

<div class="tab-pane" id="ta-d-diag">
<h4>ZK protocol self-test</h4>
<p class="text-muted">Runs the packet checksum, the framing, the comm-key derivation, the record decoders and — most importantly — the ZK timestamp codec against known vectors, entirely offline. A one-hour drift in punch times destroys a payroll, so this is worth running after any PHP upgrade.</p>
<form method="post"><button name="runSelfTest" class="btn btn-default">Run the self-test</button></form>
{if $selftest}
<p style="margin-top:10px"><strong class="{if $selftest.ok}text-success{else}text-danger{/if}">{$selftest.passed} passed, {$selftest.failed} failed.</strong></p>
<table class="table table-condensed"><thead><tr><th>Check</th><th>Got</th><th>Expected</th><th></th></tr></thead><tbody>
{foreach $selftest.cases as $c}<tr class="{if !$c.ok}danger{/if}"><td>{$c.name|escape:'html':'UTF-8'}</td><td><code>{$c.got|escape:'html':'UTF-8'}</code></td><td><code>{$c.want|escape:'html':'UTF-8'}</code></td><td>{if $c.ok}✔{else}✘{/if}</td></tr>{/foreach}
</tbody></table>
{/if}
<h4 style="margin-top:20px">Shared push key</h4>
<p>Current key: <code>{if $push_key}{$push_key|escape:'html':'UTF-8'}{else}<em>not set</em>{/if}</code>. It is only enforced when "Require a shared key" is on in Settings.</p>
<form method="post"><button name="rotateKey" class="btn btn-default" onclick="return confirm('Rotate the shared key? Every push device must be updated or it will be refused.')">Rotate the key</button></form>
</div>

</div></div></div>
