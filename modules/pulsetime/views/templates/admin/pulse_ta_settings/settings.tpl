<div class="pulse-ta"><div class="panel"><h3><i class="icon-cogs"></i> Time &amp; Attendance settings</h3>

<div class="alert alert-info">
  <strong>Shop timezone:</strong> {$shop_tz|escape:'html':'UTF-8'} — every punch is stored in this zone. Each device carries its own timezone and is converted on the way in.<br>
  <strong>Push endpoint:</strong> <code>{$push_url|escape:'html':'UTF-8'}</code><br>
  <strong>Cron:</strong> <code>{$cron_url|escape:'html':'UTF-8'}poll.php?token=…</code> every 5–10 minutes and <code>{$cron_url|escape:'html':'UTF-8'}build.php?token=…</code> once a night.<br>
  Pulse HR {if $hr}<span class="label label-success">installed</span>{else}<span class="label label-default">not installed — the local roster is used</span>{/if} ·
  Pulse POS {if $pos}<span class="label label-success">installed — POS clock-ins are reconciled</span>{else}<span class="label label-default">not installed</span>{/if} ·
  Front Desk {if $fd}<span class="label label-success">installed</span>{else}<span class="label label-default">not installed</span>{/if}
</div>

<form method="post">
<div class="row">
{foreach $groups as $title => $rows}
<div class="col-md-6">
<fieldset class="ta-fs"><legend>{$title}</legend>
{foreach $rows as $k => $f}
<div class="form-group ta-set">
  <label>{$f[0]}</label>
  {if $f[1] == 'bool'}
    <input type="hidden" name="s_{$k}" value="0"><div><label class="checkbox-inline"><input type="checkbox" name="s_{$k}" value="1" {if $values[$k]}checked{/if}> on</label></div>
  {elseif $f[1] == 'int'}
    <input type="number" name="s_{$k}" class="form-control" value="{$values[$k]|escape:'html':'UTF-8'}">
  {elseif $f[1] == 'time'}
    <input type="time" name="s_{$k}" class="form-control" value="{$values[$k]|escape:'html':'UTF-8'}">
  {elseif $f[1] == 'secret'}
    <input name="s_{$k}" class="form-control" value="{$values[$k]|escape:'html':'UTF-8'}" readonly onclick="this.select()">
  {elseif $f[1] == 'select'}
    <select name="s_{$k}" class="form-control">{foreach $f[3] as $o}<option value="{$o}" {if $values[$k]==$o}selected{/if}>{$o}</option>{/foreach}</select>
  {else}
    <input name="s_{$k}" class="form-control" value="{$values[$k]|escape:'html':'UTF-8'}">
  {/if}
  {if $f[2]}<span class="help-block">{$f[2]}</span>{/if}
</div>
{/foreach}
</fieldset>
</div>
{/foreach}
</div>
<button name="saveSettings" class="btn btn-primary btn-lg">Save settings</button>
</form>

<hr>
<div class="row">
<div class="col-md-6">
<h4>Push endpoint security</h4>
<p class="text-muted">The /iclock endpoint is a public HTTP surface that hardware dials into. An unknown serial is registered as <strong>pending</strong> and delivers nothing until an administrator claims it on the Devices screen. Turning on "Require a shared key" adds a second factor; the key below is the property-wide default and any device may carry its own.</p>
<form method="post" class="form-inline">
  <input name="push_key_value" class="form-control" style="width:340px" value="{$push_key|escape:'html':'UTF-8'}" placeholder="leave blank to generate one">
  <button name="setPushKey" class="btn btn-default">Set the shared key</button>
</form>
<p class="text-muted" style="margin-top:6px">Devices then call <code>{$push_url|escape:'html':'UTF-8'}cdata?SN=…&amp;{$values.PUSH_KEY_PARAM|escape:'html':'UTF-8'}=…</code>. Changing the key locks out every device until they are updated.</p>
<a class="btn btn-default btn-sm" href="{$devices_url}">Open Devices</a>
</div>
<div class="col-md-6">
<h4>Tokens &amp; housekeeping</h4>
<form method="post" style="display:inline"><button name="newCronToken" class="btn btn-default btn-sm">Regenerate the cron token</button></form>
<form method="post" style="display:inline"><button name="newKioskToken" class="btn btn-default btn-sm">Regenerate the kiosk token</button></form>
<form method="post" style="display:inline"><button name="purgeLogs" class="btn btn-default btn-sm">Purge old push traffic now</button></form>
<h4 style="margin-top:16px">Right now</h4>
<ul>
  <li>{$counters.active_staff} active staff, {$counters.on_site} on site</li>
  <li>{$counters.punches_today} punch(es) today, {$counters.unmatched} unmatched in the last week</li>
  <li>{$counters.open_exceptions} open exception(s), {$counters.blocking_exceptions} blocking approval</li>
  <li>{$counters.devices_total} device(s): {$counters.devices_offline} offline, {$counters.devices_pending} pending a claim</li>
  <li>{$counters.queue} job(s) waiting in the retry queue</li>
</ul>
</div>
</div>
</div></div>
