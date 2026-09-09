<div class="pulse-kc"><div class="panel"><h3><i class="icon-cogs"></i> Common doors, cron and integration</h3>
<div class="row"><div class="col-md-7">
<h4>Doors</h4>
<p>Doors flagged <em>on every guest key</em> are added to each card automatically — main entrance, lift, pool. Everything else is ticked per key at the desk. A room's own lock lives here too, so lock audit can be attributed to a room.</p>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Lock id</th><th>Room</th><th>Default</th><th>Encoder</th><th>Active</th></tr></thead><tbody>
{foreach $doors as $d}<tr class="{if !$d.active}text-muted{/if}"><td>{$d.code|escape:'html':'UTF-8'}</td><td>{$d.name|escape:'html':'UTF-8'}</td><td>{$d.type|replace:'_':' '|escape:'html':'UTF-8'}</td><td><code>{$d.lock_id|default:'—'|escape:'html':'UTF-8'}</code></td>
  <td>{$d.room_num|default:'—'|escape:'html':'UTF-8'}</td><td>{if $d.is_default}yes{else}no{/if}</td><td>{$d.id_pulse_kc_encoder|default:'—'|escape:'html':'UTF-8'}</td><td>{if $d.active}yes{else}no{/if}</td></tr>
{/foreach}</tbody></table>
<form method="post" class="form-inline">
  <input name="id_door" class="form-control input-sm" style="width:60px" placeholder="id">
  <input name="code" class="form-control input-sm" placeholder="Code" required>
  <input name="name" class="form-control input-sm" placeholder="Name" required>
  <select name="type" class="form-control input-sm">{foreach $door_types as $t}<option value="{$t|escape:'html':'UTF-8'}">{$t|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input name="lock_id" class="form-control input-sm" placeholder="Lock id">
  <select name="id_room" class="form-control input-sm"><option value="">no room</option>{foreach $rooms as $r}<option value="{$r.id_room|escape:'html':'UTF-8'}">{$r.room_num|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="id_encoder" class="form-control input-sm"><option value="">default</option>{foreach $encoders as $e}<option value="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}">{$e.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <label><input type="checkbox" name="is_default" value="1"> default</label>
  <label><input type="checkbox" name="active" value="1" checked> active</label>
  <button name="saveDoor" class="btn btn-primary btn-sm">Save</button>
  <button name="syncDoors" class="btn btn-default btn-sm">Create a door for every room</button>
</form>
</div><div class="col-md-5">
<h4>Scheduled job</h4>
<p>Run hourly. It expires keys past check-out, drains the retry queue for encoders that were down, purges old audit rows, flags encoders nobody has heard from, pulls lock audit and raises battery work orders.</p>
<pre class="kc-cron">{$cron_url|escape:'html':'UTF-8'}</pre>
<form method="post" class="inline"><button name="rotateToken" class="btn btn-default btn-xs" onclick="return confirm('Rotate the cron token? Update your crontab afterwards.')">Rotate token</button></form>
<h4>API</h4>
<p>JSON API at <code>{$api_url|escape:'html':'UTF-8'}</code> with a Bearer token from Pulse Core. Scopes: <code>desk</code> (issue, duplicate, cancel, extend, encoder status), <code>portal</code> (mobile key fetch and refresh), <code>security</code> (lock audit).</p>
<h4>Integration</h4>
<ul>
  <li>Front Desk: {if $fd}<span class="label label-success">connected</span> — keys follow check-in, room move, stay change and check-out{else}<span class="label label-default">not installed</span> — the Key Desk still issues keys by room{/if}</li>
  <li>Maintenance tickets: {if $maintenance}<span class="label label-success">connected</span> — flat locks raise a maintenance work order{else}<span class="label label-default">not installed</span> — battery alerts stay on the Lock Audit screen{/if}</li>
</ul>
</div></div>
</div></div>
