<div class="pulse-ch"><div class="panel"><h3><i class="icon-plug"></i> Channels</h3>
<table class="table table-condensed"><thead><tr><th>Channel</th><th>Adapter</th><th>Mode</th><th>Auth</th><th>Hotel code</th><th>Currency</th><th>Commission</th><th>Allot</th><th>Buffer</th><th>Health</th><th></th></tr></thead><tbody>
{foreach $channels as $c}<tr class="{if !$c.enabled}text-muted{/if}"><td><b>{$c.name|escape:'html':'UTF-8'}</b> <small class="text-muted">{$c.code|escape:'html':'UTF-8'}</small>{if $c.test_mode} <span class="label label-default">test</span>{/if}</td>
<td><small>{$c.adapter|escape:'html':'UTF-8'}</small></td><td>{$c.sync_mode}</td><td>{$c.auth_type}</td><td>{$c.hotel_code}</td><td>{$c.currency_iso}</td><td>{$c.commission_pct}%</td><td>{if $c.allotment}{$c.allotment}{else}all{/if}</td><td>{$c.oversell_buffer}</td>
<td><span class="ch-dot ch-{$c.health}"></span> {$c.health}</td>
<td class="text-right"><a class="btn btn-xs btn-default" href="{$self_url}&id_channel={$c.id_pulse_ch_channel}">Edit</a>
<form method="post" class="inline"><input type="hidden" name="id_channel_t" value="{$c.id_pulse_ch_channel}">
<button name="testChannel" class="btn btn-xs btn-default">Test</button>
<button name="toggleChannel" class="btn btn-xs {if $c.enabled}btn-warning{else}btn-success{/if}">{if $c.enabled}Disable{else}Enable{/if}</button>
<button name="deleteChannel" class="btn btn-xs btn-link" onclick="return confirm('Remove this channel with its mappings and ARI? Delivered reservations and logs stay.')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="11"><em>No channels.</em></td></tr>{/foreach}</tbody></table>

<h4>{if $edit}Edit {$edit.name}{else}Add a channel{/if}</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_pulse_ch_channel" value="{if $edit}{$edit.id_pulse_ch_channel}{/if}"><div class="row">
<div class="col-md-4">
<div class="form-group"><label class="col-sm-4">Code</label><div class="col-sm-8"><input name="c_code" class="form-control" value="{if $edit}{$edit.code|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="c_name" class="form-control" value="{if $edit}{$edit.name|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Adapter</label><div class="col-sm-8"><select name="c_adapter" class="form-control">{foreach $adapters as $k => $v}<option value="{$k}" {if $edit && $edit.adapter==$k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Sync mode</label><div class="col-sm-8"><select name="c_mode" class="form-control">{foreach ['both','push','pull','off'] as $m}<option value="{$m}" {if $edit && $edit.sync_mode==$m}selected{/if}>{$m}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Hotel code</label><div class="col-sm-8"><input name="c_hotel_code" class="form-control" value="{if $edit}{$edit.hotel_code|escape}{/if}"><small class="text-muted">the property id the OTA issued you</small></div></div>
<div class="form-group"><label class="col-sm-4">Currency</label><div class="col-sm-8"><input name="c_currency" class="form-control" value="{if $edit}{$edit.currency_iso}{else}NGN{/if}" size="4"></div></div>
<div class="form-group"><label class="col-sm-4">Commission %</label><div class="col-sm-8"><input name="c_commission" class="form-control" value="{if $edit}{$edit.commission_pct}{else}15{/if}"></div></div>
<div class="form-group"><label class="col-sm-4">Flags</label><div class="col-sm-8">
<label><input type="checkbox" name="c_enabled" value="1" {if $edit && $edit.enabled}checked{/if}> Enabled</label>
<label><input type="checkbox" name="c_test" value="1" {if !$edit || $edit.test_mode}checked{/if}> Test mode</label>
<label><input type="checkbox" name="c_auto" value="1" {if !$edit || $edit.auto_deliver}checked{/if}> Create bookings automatically</label></div></div>
</div>
<div class="col-md-4">
<div class="form-group"><label class="col-sm-4">Push endpoint</label><div class="col-sm-8"><input name="c_endpoint" class="form-control" value="{if $edit}{$edit.endpoint|escape}{/if}" placeholder="https://…"></div></div>
<div class="form-group"><label class="col-sm-4">Pull endpoint</label><div class="col-sm-8"><input name="c_pull" class="form-control" value="{if $edit}{$edit.pull_endpoint|escape}{/if}"></div></div>
<div class="form-group"><label class="col-sm-4">Ack endpoint</label><div class="col-sm-8"><input name="c_ack" class="form-control" value="{if $edit}{$edit.ack_endpoint|escape}{/if}"></div></div>
<div class="form-group"><label class="col-sm-4">Auth type</label><div class="col-sm-8"><select name="c_auth" class="form-control">{foreach ['none','basic','bearer','hmac','api_key','soap_ws_security'] as $a}<option value="{$a}" {if $edit && $edit.auth_type==$a}selected{/if}>{$a}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Auth header</label><div class="col-sm-8"><input name="c_auth_header" class="form-control" value="{if $edit}{$edit.auth_header|escape}{else}Authorization{/if}"></div></div>
<div class="form-group"><label class="col-sm-4">Payload format</label><div class="col-sm-8"><select name="c_format" class="form-control">{foreach ['json','xml','csv','form'] as $f}<option value="{$f}" {if $edit && $edit.payload_format==$f}selected{/if}>{$f}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Credentials</label><div class="col-sm-8">
<input name="c_user" class="form-control" placeholder="username{if $has_creds} (stored — blank keeps it){/if}" autocomplete="off">
<input name="c_pass" type="password" class="form-control" placeholder="password" autocomplete="new-password">
<input name="c_key" class="form-control" placeholder="api key / token" autocomplete="off">
<input name="c_secret" class="form-control" placeholder="hmac secret" autocomplete="off">
<small class="text-muted">Stored encrypted. {if $has_creds}Currently held: {$cred_keys}.{/if}</small></div></div>
<div class="form-group"><label class="col-sm-4">CSV folders</label><div class="col-sm-8"><input name="c_csv_in" class="form-control" value="{if $edit}{$edit.csv_in_dir|escape}{/if}" placeholder="inbound drop folder"><input name="c_csv_out" class="form-control" value="{if $edit}{$edit.csv_out_dir|escape}{/if}" placeholder="ARI output folder"></div></div>
</div>
<div class="col-md-4">
<div class="form-group"><label class="col-sm-5">Push window (days)</label><div class="col-sm-7"><input name="c_window" class="form-control" value="{if $edit}{$edit.push_window_days}{else}365{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Allotment (0 = all)</label><div class="col-sm-7"><input name="c_allot" class="form-control" value="{if $edit}{$edit.allotment}{else}0{/if}"><small class="text-muted">rooms this channel may sell per night</small></div></div>
<div class="form-group"><label class="col-sm-5">Oversell buffer</label><div class="col-sm-7"><input name="c_buffer" class="form-control" value="{if $edit}{$edit.oversell_buffer}{else}0{/if}"><small class="text-muted">capped by the room type overbooking limit</small></div></div>
<div class="form-group"><label class="col-sm-5">Release days</label><div class="col-sm-7"><input name="c_release" class="form-control" value="{if $edit}{$edit.release_days}{else}0{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Batch size</label><div class="col-sm-7"><input name="c_batch" class="form-control" value="{if $edit}{$edit.batch_size}{else}200{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Timeout (s)</label><div class="col-sm-7"><input name="c_timeout" class="form-control" value="{if $edit}{$edit.timeout_sec}{else}20{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Body template</label><div class="col-sm-7"><textarea name="c_template" rows="5" class="form-control" placeholder="optional SOAP/WS-Security wrapper containing {literal}{{body}}{/literal}">{if $edit}{$edit.payload_template|escape}{/if}</textarea>
<small class="text-muted">Placeholders: {literal}{{body}} {{hotel_code}} {{username}} {{password}} {{api_key}} {{timestamp}} {{echo_token}}{/literal}</small></div></div>
<div class="form-group"><label class="col-sm-5">Notes</label><div class="col-sm-7"><textarea name="c_notes" rows="3" class="form-control">{if $edit}{$edit.notes|escape}{/if}</textarea></div></div>
<button name="saveChannel" class="btn btn-primary btn-lg">Save channel</button></div></div></form>

<hr><h4>Integrator quick reference</h4>
<p class="text-muted">Partners and intermediaries (eZee Centrix, RateTiger, SiteMinder or a bespoke OTA connector) talk to us here. The full contract is in <code>modules/pulsechannel/README.md</code>.</p>
<table class="table table-condensed"><tbody>
<tr><th>Base URL</th><td><code>{$shop_url}pulse/api/channel/</code></td></tr>
<tr><th>Resources</th><td><code>ping</code> · <code>ari</code> · <code>reservation</code> (inbound webhook) · <code>ack</code> · <code>health</code></td></tr>
<tr><th>Auth (partner)</th><td><code>X-Pulse-Channel: &lt;code&gt;</code>, <code>X-Pulse-Timestamp: &lt;unix&gt;</code>, <code>X-Pulse-Signature: sha256=hmac_sha256(timestamp + "." + body, secret)</code></td></tr>
<tr><th>Auth (internal)</th><td><code>Authorization: Bearer &lt;pulse_api_token&gt;</code> with scope <code>channel</code></td></tr>
<tr><th>Cron</th><td><code>php modules/pulsechannel/cron/sync.php {$cron_token}</code> · <code>php modules/pulsechannel/cron/rebuild_ari.php {$cron_token}</code></td></tr>
</tbody></table>
<form method="post" class="inline"><button name="rotateSecret" class="btn btn-default" onclick="return confirm('Rotate the shared webhook secret? Partners using the old one will start failing.')">Rotate webhook secret</button></form>
</div></div>
