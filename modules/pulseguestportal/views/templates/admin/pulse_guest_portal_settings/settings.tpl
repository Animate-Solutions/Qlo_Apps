<div class="pulse-gp">
<div class="panel"><h3><i class="icon-list"></i> Sections &amp; languages</h3>
<p class="help-block">Unticking a section hides it on every screen at the next reload. The launcher URL is <code>{$portal_url}</code> — the TV should be pointed at <code>{$portal_url}?mac=&lt;MAC&gt;</code>.</p>
<form method="post"><div class="row"><div class="col-md-8"><strong>Sections</strong><br>
{foreach $all_sections as $s}<label style="display:inline-block;width:170px"><input type="checkbox" name="section[]" value="{$s|escape:'html':'UTF-8'}" {if in_array($s, $sections)}checked{/if}> {$s|escape:'html':'UTF-8'}</label>{/foreach}</div>
<div class="col-md-4"><strong>Languages offered</strong><br>
{foreach $all_langs as $code => $name}<label style="display:block"><input type="checkbox" name="lang[]" value="{$code|escape:'html':'UTF-8'}" {if isset($langs[$code])}checked{/if}> {$name|escape:'html':'UTF-8'} ({$code|escape:'html':'UTF-8'})</label>{/foreach}</div></div>
<button name="saveSections" class="btn btn-primary" style="margin-top:8px">Save sections &amp; languages</button></form></div>

<div class="row">
<div class="col-md-6"><div class="panel"><h3>Adult PIN</h3>
<p class="help-block">Adult channels and titles stay hidden until this PIN is typed on the screen. It is stored hashed; leave the box empty and save to clear it (adult content then stays hidden for everyone).</p>
<form method="post" class="form-inline"><input name="adult_pin" class="form-control" placeholder="{if $pin_set}PIN is set — type a new one{else}4 to 8 digits{/if}" autocomplete="off"> <button name="savePin" class="btn btn-default">Save PIN</button></form></div>

<div class="panel"><h3>Logo</h3>
<form method="post" enctype="multipart/form-data" class="form-inline"><input type="file" name="logofile"> <button name="uploadLogo" class="btn btn-default">Upload</button></form>
{if $logo}<p style="margin-top:8px"><img src="{$upload_base|escape:'html':'UTF-8'}{$logo}" style="max-height:70px;background:#00424b;padding:6px"></p>{/if}</div></div>

<div class="col-md-6"><div class="panel"><h3>Room controls</h3>
<p class="help-block">Adapter in use: <strong>{$adapters[$adapter]|default:$adapter|escape:'html':'UTF-8'}</strong> &middot; {$points|escape:'html':'UTF-8'} control point(s) registered. The simulator stores states in Pulse and contacts no hardware — the guest screen says so plainly. Provisioning asks the adapter what each room exposes and writes the register.</p>
<form method="post" class="form-inline"><input name="control_key" class="form-control" placeholder="Shared HMAC key for the HTTP adapter" autocomplete="off"> <button name="saveControlKey" class="btn btn-default">Store key</button></form>
<form method="post" class="form-inline" style="margin-top:6px"><button name="testControl" class="btn btn-default">Test adapter</button> <button name="provision" class="btn btn-default" data-gp-confirm="Provision control points for every room?">Provision all rooms</button></form>
{if $test}<div class="alert {if $test.ok}alert-success{else}alert-danger{/if}" style="margin-top:8px">{$test.message|escape:'html':'UTF-8'}</div>{/if}
<table class="table table-condensed" style="margin-top:8px"><tbody>{foreach $control_log as $l}<tr class="{if $l.result=='failed'}danger{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M"}</td><td>{$l.room_num|escape:'html':'UTF-8'}</td><td>{$l.code|escape:'html':'UTF-8'} {$l.action|escape:'html':'UTF-8'} {$l.value|escape:'html':'UTF-8'}</td><td>{$l.result|escape:'html':'UTF-8'}</td><td><small>{$l.message|escape:'html':'UTF-8'}</small></td></tr>{foreachelse}<tr><td><em>No control activity yet</em></td></tr>{/foreach}</tbody></table></div>

<div class="panel"><h3>API &amp; cron</h3>
<p class="help-block">The screens authenticate with their own device tokens. This portal-scoped API token is only needed when Pulse Laundry runs on another host, or for an integration that drives the portal from outside.</p>
<form method="post" class="form-inline"><button name="makeToken" class="btn btn-default">Create a portal API token</button></form>
{if $api_token}<p><code style="word-break:break-all">{$api_token|escape:'html':'UTF-8'}</code></p>{/if}
<p class="help-block">Run the portal housekeeping job every five minutes (wake-up calls, offline detection, session and casting expiry):<br><code>*/5 * * * * curl -s "{$cron_url}"</code></p></div></div>
</div></div>
