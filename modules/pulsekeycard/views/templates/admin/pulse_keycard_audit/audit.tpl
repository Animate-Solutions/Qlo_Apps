<div class="pulse-kc"><div class="panel"><h3><i class="icon-shield"></i> Lock audit</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#a-events">Door events ({$rows|count})</a></li><li><a data-toggle="tab" href="#a-denied">Denied ({$denied|count})</a></li><li><a data-toggle="tab" href="#a-batt">Battery ({$battery|count})</a></li><li><a data-toggle="tab" href="#a-doors">Doors &amp; locks</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="a-events">
<form method="get" class="form-inline kc-filters">
  <input type="hidden" name="controller" value="AdminPulseKeycardAudit"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <select name="id_room" class="form-control"><option value="">Any room</option>{foreach $rooms as $r}<option value="{$r.id_room|escape:'html':'UTF-8'}" {if $f.id_room==$r.id_room}selected{/if}>{$r.room_num|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="id_door" class="form-control"><option value="">Any door</option>{foreach $doors as $d}<option value="{$d.id_pulse_kc_door|escape:'html':'UTF-8'}" {if $f.id_door==$d.id_pulse_kc_door}selected{/if}>{$d.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="event" class="form-control"><option value="">Any event</option>{foreach $events as $e}<option value="{$e|escape:'html':'UTF-8'}" {if $f.event==$e}selected{/if}>{$e|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="result" class="form-control"><option value="">Any result</option><option value="granted" {if $f.result=='granted'}selected{/if}>granted</option><option value="denied" {if $f.result=='denied'}selected{/if}>denied</option></select>
  <input name="q" value="{$f.q|escape:'html':'UTF-8'}" class="form-control" placeholder="Card serial or name">
  <input type="date" name="from" value="{$f.from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$f.to|escape:'html':'UTF-8'}" class="form-control">
  <button class="btn btn-default">Filter</button>
</form>
<form method="post" class="inline"><button name="pullAll" class="btn btn-primary btn-sm"><i class="icon-download"></i> Pull every lock now</button></form>
<p class="help-block">A door-open row is attributed to the key that carries the card serial, so the guest or member of staff behind every entry is named. Rows older than {$retention|escape:'html':'UTF-8'} days are purged by the hourly cron.</p>
<table class="table table-condensed"><thead><tr><th>When</th><th>Door</th><th>Room</th><th>Event</th><th>Result</th><th>Card</th><th>Who</th><th>Key</th><th>Battery</th><th>Source</th></tr></thead><tbody>
{foreach $rows as $a}<tr class="{if $a.result=='denied'}warning{elseif $a.event=='battery_low'}danger{/if}">
  <td>{$a.opened_at|escape:'html':'UTF-8'}</td><td>{$a.door_name|default:$a.lock_id|escape:'html':'UTF-8'}</td><td>{$a.room_num|default:'—'|escape:'html':'UTF-8'}</td>
  <td>{$a.event|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$a.result|escape:'html':'UTF-8'}</td><td><code>{$a.card_serial|default:'—'|escape:'html':'UTF-8'}</code></td>
  <td>{$a.holder|default:'—'|escape:'html':'UTF-8'}{if $a.staff_group}<br><small class="text-muted">{$a.staff_group|escape:'html':'UTF-8'}{if $a.shift_start} · shift {$a.shift_start|truncate:5:''|escape:'html':'UTF-8'}–{$a.shift_end|truncate:5:''|escape:'html':'UTF-8'}{/if}</small>{/if}</td>
  <td>{if $a.key_no}<span class="label label-default">{$a.key_type|replace:'_':' '|escape:'html':'UTF-8'}</span> {$a.key_no|escape:'html':'UTF-8'}{else}<em>unknown card</em>{/if}</td>
  <td>{if $a.battery_pct !== null}{$a.battery_pct|escape:'html':'UTF-8'}%{else}—{/if}</td><td>{$a.source|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="10"><em>No events for that filter. Pull a lock, or wait for the hourly cron.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-denied">
<p>Refused swipes in the last 24 hours, worst door first — the first place to look after a reported intrusion.</p>
<table class="table table-condensed"><thead><tr><th>Door</th><th>Room</th><th>Denials</th><th>Last</th></tr></thead><tbody>
{foreach $denied as $d}<tr class="warning"><td>{$d.door_name|escape:'html':'UTF-8'}</td><td>{$d.room_num|default:'—'|escape:'html':'UTF-8'}</td><td>{$d.denials|escape:'html':'UTF-8'}</td><td>{$d.last_at|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="4"><em>No refused swipes in the last day</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-batt">
<p>Locks at or below {$battery_pct|escape:'html':'UTF-8'}%. {if $maintenance}Raising work orders opens one maintenance ticket per lock (at most one a week per door).{else}Install Pulse Maintenance to turn these into work orders automatically.{/if}</p>
<form method="post" class="inline"><button name="raiseBattery" class="btn btn-warning btn-sm" {if !$maintenance}disabled{/if}><i class="icon-wrench"></i> Raise maintenance work orders</button></form>
<table class="table table-condensed"><thead><tr><th>Door</th><th>Room</th><th>Lock</th><th>Battery</th><th>Checked</th><th>Last work order</th></tr></thead><tbody>
{foreach $battery as $b}<tr class="{if $b.battery_pct <= 10}danger{else}warning{/if}"><td>{$b.name|escape:'html':'UTF-8'}</td><td>{$b.room_num|default:'—'|escape:'html':'UTF-8'}</td><td><code>{$b.lock_id|escape:'html':'UTF-8'}</code></td>
  <td>{$b.battery_pct|escape:'html':'UTF-8'}%</td><td>{$b.battery_checked_at|escape:'html':'UTF-8'}</td><td>{$b.battery_ticket_at|default:'—'|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="6"><em>Every lock that reports a battery level is healthy</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-doors">
<form method="post" class="form-inline"><button name="syncDoors" class="btn btn-default btn-sm">Create a door row for every room</button>
  <button name="purgeAudit" class="btn btn-default btn-sm" onclick="return confirm('Delete audit rows older than {$retention|escape:'html':'UTF-8'} days?')">Purge old audit rows</button></form>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Lock id</th><th>Room</th><th>On every guest key</th><th>Battery</th><th>Last pull</th><th></th></tr></thead><tbody>
{foreach $doors as $d}<tr class="{if !$d.active}text-muted{/if}"><td>{$d.code|escape:'html':'UTF-8'}</td><td>{$d.name|escape:'html':'UTF-8'}</td><td>{$d.type|replace:'_':' '|escape:'html':'UTF-8'}</td><td><code>{$d.lock_id|default:'—'|escape:'html':'UTF-8'}</code></td>
  <td>{$d.room_num|default:'—'|escape:'html':'UTF-8'}</td><td>{if $d.is_default}yes{else}no{/if}</td><td>{if $d.battery_pct !== null}{$d.battery_pct|escape:'html':'UTF-8'}%{else}—{/if}</td><td>{$d.last_audit_at|default:'never'|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_door" value="{$d.id_pulse_kc_door|escape:'html':'UTF-8'}"><button name="pullDoor" class="btn btn-xs btn-default" {if !$d.lock_id}disabled{/if}>Pull</button></form></td></tr>
{/foreach}
</tbody></table>
<h4>Add or edit a door</h4>
<form method="post" class="form-inline">
  <input name="id_door_edit" class="form-control input-sm" style="width:60px" placeholder="id">
  <input name="code" class="form-control input-sm" placeholder="Code" required>
  <input name="name" class="form-control input-sm" placeholder="Name" required>
  <select name="type" class="form-control input-sm"><option value="common">common</option><option value="lift">lift</option><option value="gate">gate</option><option value="back_of_house">back of house</option><option value="wall_reader">wall reader</option><option value="safe">safe</option><option value="room">room</option></select>
  <input name="lock_id" class="form-control input-sm" placeholder="Lock id">
  <select name="door_room" class="form-control input-sm"><option value="">no room</option>{foreach $rooms as $r}<option value="{$r.id_room|escape:'html':'UTF-8'}">{$r.room_num|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="id_encoder" class="form-control input-sm"><option value="">default encoder</option>{foreach $encoders as $e}<option value="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}">{$e.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <label><input type="checkbox" name="is_default" value="1"> on every guest key</label>
  <label><input type="checkbox" name="active" value="1" checked> active</label>
  <button name="saveDoor" class="btn btn-primary btn-sm">Save door</button>
</form>
</div>

</div></div></div>
