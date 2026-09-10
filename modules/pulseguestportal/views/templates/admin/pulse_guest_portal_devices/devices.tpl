<div class="pulse-gp"><div class="panel"><h3><i class="icon-desktop"></i> Portal devices <span class="badge">{$counts.online|intval} online</span> {if $counts.pending}<span class="badge" style="background:#e8a33d">{$counts.pending|escape:'html':'UTF-8'} waiting</span>{/if}</h3>
<p class="help-block">A screen appears here the moment it boots the launcher URL <code>{$portal_url}?mac=&lt;MAC&gt;</code>. Until it is paired to a room it shows only the pairing code, and it is called offline after {$offline_min|escape:'html':'UTF-8'} minute(s) without a heartbeat. Check-out policy: <strong>{$wipe_policy|escape:'html':'UTF-8'}</strong>.</p>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseGuestPortalDevices"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<input name="q" value="{$q|escape}" class="form-control" placeholder="MAC, serial, room or label"> <select name="only" class="form-control"><option value="">All</option><option value="pending" {if $only=='pending'}selected{/if}>Waiting to pair</option><option value="offline" {if $only=='offline'}selected{/if}>Offline</option></select> <button class="btn btn-default">Search</button></form>

<table class="table table-condensed" id="gp-device-board" data-refresh="1"><thead><tr><th></th><th>Room</th><th>Label</th><th>Type</th><th>MAC / serial</th><th>Model</th><th>Pair code</th><th>Last seen</th><th>Status</th><th class="noprint">Actions</th></tr></thead><tbody>
{foreach $devices as $d}<tr class="{if $d.status=='pending'}warning{elseif $d.status=='blocked'}danger{elseif !$d.online}active{/if}">
<td><span class="gp-dot {if $d.status=='pending'}pending{elseif $d.online}on{else}off{/if}"></span></td>
<td>{if $d.room_num}{$d.room_num|escape:'html':'UTF-8'}{else}<em>—</em>{/if}</td><td>{$d.label|escape:'html':'UTF-8'}</td><td>{$d.type|escape:'html':'UTF-8'}</td><td><small>{$d.mac|escape:'html':'UTF-8'}{if $d.serial}<br>{$d.serial|escape:'html':'UTF-8'}{/if}</small></td><td><small>{$d.model|escape:'html':'UTF-8'} {$d.firmware|escape:'html':'UTF-8'}</small></td><td><code>{$d.pair_code|escape:'html':'UTF-8'}</code></td>
<td>{if $d.last_seen}{$d.last_seen|date_format:"%d/%m %H:%M"}{else}<em>never</em>{/if}{if $d.boots}<br><small class="text-muted">{$d.boots|escape:'html':'UTF-8'} boot(s)</small>{/if}</td><td>{$d.status|escape:'html':'UTF-8'}</td>
<td class="noprint"><form method="post" class="form-inline"><input type="hidden" name="id_device" value="{$d.id_pulse_gp_device|escape:'html':'UTF-8'}">
{if $d.status=='pending'}<select name="id_room" class="input-sm" required><option value="">Room…</option>{foreach $rooms as $r}<option value="{$r.id_room|escape:'html':'UTF-8'}">{$r.room_num|escape:'html':'UTF-8'}{if $r.devices} ({$r.devices|escape:'html':'UTF-8'}){/if}</option>{/foreach}</select>
<select name="type" class="input-sm"><option value="tv">TV</option><option value="tablet">Tablet</option><option value="cast">Cast</option><option value="kiosk">Kiosk</option></select>
<input name="label" class="input-sm" placeholder="Label" style="width:90px"> <button name="approve" class="btn btn-xs btn-success">Pair</button>
{else}<button name="reload" class="btn btn-xs btn-default">Reload</button>
<button name="wipe" class="btn btn-xs btn-default" data-gp-confirm="Wipe this screen now?">Wipe</button>
{if $d.status=='blocked'}<button name="unblock" class="btn btn-xs btn-success">Unblock</button>{else}<button name="block" class="btn btn-xs btn-warning">Block</button>{/if}
<button name="rotate" class="btn btn-xs btn-default" data-gp-confirm="Rotate the token? The screen re-pairs on its next boot.">Token</button>
<button name="retire" class="btn btn-xs btn-link" data-gp-confirm="Retire this device?">✕</button>
<br><input name="text" class="input-sm" placeholder="Push a message to this screen" style="width:170px"> <button name="pushMsg" class="btn btn-xs btn-default">Send</button>{/if}
</form></td></tr>
{foreachelse}<tr><td colspan="10"><em>No devices match</em></td></tr>{/foreach}</tbody></table>
</div>

<div class="row"><div class="col-md-6"><div class="panel"><h3>Register a device by hand</h3>
<p class="help-block">For a TV that has not booted yet: take the MAC from the set's <em>Menu ▸ Support ▸ About this TV</em> and pair it to its room now, so the screen is personalised the first time it starts.</p>
<form method="post" class="form-inline"><input name="mac" class="form-control" placeholder="MAC e.g. 00:16:6C:AB:CD:EF"> <input name="serial" class="form-control" placeholder="Serial"> <input name="model" class="form-control" placeholder="HG50AU800" style="width:130px">
<select name="type" class="form-control"><option value="tv">TV</option><option value="tablet">Tablet</option><option value="cast">Cast</option><option value="kiosk">Kiosk</option></select>
<select name="id_room" class="form-control"><option value="">Room…</option>{foreach $rooms as $r}<option value="{$r.id_room|escape:'html':'UTF-8'}">{$r.room_num|escape:'html':'UTF-8'}</option>{/foreach}</select>
<input name="label" class="form-control" placeholder="Label" style="width:120px"> <button name="addDevice" class="btn btn-primary">Register</button></form></div></div>
<div class="col-md-6"><div class="panel"><h3>Recent commands</h3><table class="table table-condensed"><tbody>
{foreach $commands as $c}<tr><td>{$c.date_add|date_format:"%d/%m %H:%M"}</td><td>{$c.room_num|default:$c.label|escape:'html':'UTF-8'}</td><td>{$c.type|escape:'html':'UTF-8'}</td><td>{$c.status|escape:'html':'UTF-8'}</td><td><small>{$c.payload|truncate:60|escape:'html':'UTF-8'}</small></td></tr>{foreachelse}<tr><td><em>No commands queued</em></td></tr>{/foreach}</tbody></table></div></div></div>
</div>
