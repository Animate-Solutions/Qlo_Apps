<div class="pulse-crm"><div class="panel"><h3><i class="icon-cogs"></i> CRM plumbing</h3>
<div class="row"><div class="col-md-6">
<h4>Where messages actually go</h4>
<table class="table table-condensed"><tbody>
<tr><td>Front Desk (folio, guest 360, comms log)</td><td>{if $fd}<span class="badge crm-pro">installed</span>{else}<span class="badge crm-det">not installed</span> — email still works, SMS has nowhere to go{/if}</td></tr>
<tr><td>Pulse Comms template map</td><td>{if $comms}<span class="badge crm-pro">shared</span> — CRM templates are injected into it{else}<span class="badge">standalone fallback</span>{/if}</td></tr>
<tr><td>SMS / WhatsApp adapter</td><td>{if $sms_key}{$sms_adapter|default:'PulseCommsTermii'|escape:'html':'UTF-8'}{else}<span class="badge crm-det">no API key</span> — set PULSE_FD_SMS_API_KEY in Front Desk settings{/if}</td></tr>
<tr><td>Quiet hours right now</td><td>{if $quiet}<span class="badge crm-det">in force</span> — sends are held{else}<span class="badge crm-pro">open</span>{/if}</td></tr>
</tbody></table>

<h4>Cron</h4>
<p>One job does the lot: refresh segments, advance journeys, send scheduled campaigns, expire points, re-tier members, fire occasion reminders. Run it every fifteen minutes.</p>
<pre>*/15 * * * * php {'{'}path{'}'}/modules/pulsecrm/cron/crm.php {$cron_token|escape:'html':'UTF-8'}</pre>
<p>Or over HTTP, if that is all the host gives you:</p>
<pre>{$cron_url|escape:'html'}</pre>
<form method="post" class="form-inline"><button name="rollToken" class="btn btn-default btn-sm" onclick="return confirm('Roll the token? Any crontab using the old one stops working.')">Roll the token</button></form>
<p class="help-block">Each pass is chunked, so a shared host that kills long requests simply picks up where it left off next time. Nothing is lost by a missed run.</p>

<h4>Consent register</h4>
<table class="table table-condensed"><thead><tr><th>Channel</th><th>State</th><th>Guests</th></tr></thead><tbody>
{foreach $consent_summary as $c}<tr><td>{$c.channel|escape:'html':'UTF-8'}</td><td>{$c.state|escape:'html':'UTF-8'}</td><td>{$c.n|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="3"><em>Nothing recorded yet.</em></td></tr>{/foreach}
</tbody></table>
<p class="help-block">Under the NDPR consent must be freely given, specific and demonstrable. Pulse stores the channel, the state, the timestamp, the source and the evidence for every decision, and refuses to send on anything that is not an explicit opt-in.</p>
</div>

<div class="col-md-6">
<h4>Managed preference list</h4>
<p class="help-block">These are the choices the desk picks from at check-in. Free text is always allowed as well; anything in the allergy or dietary categories automatically becomes a service note.</p>
<table class="table table-condensed"><thead><tr><th>Category</th><th>Code</th><th>Label</th><th>#</th></tr></thead><tbody>
{foreach $options as $o}<tr class="{if !$o.active}text-muted{/if}"><td>{$o.category|replace:'_':' '|escape:'html':'UTF-8'}</td><td><small>{$o.code|escape:'html':'UTF-8'}</small></td><td>{$o.label|escape:'html':'UTF-8'}</td><td>{$o.sort|escape:'html':'UTF-8'}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_option" value="{(int)$smarty.get.id_option}">
  <select name="category" class="form-control">
    <option value="room_position">room position</option><option value="floor">floor</option><option value="pillow">pillow</option><option value="bed">bed</option>
    <option value="allergy">allergy</option><option value="dietary">dietary</option><option value="newspaper">newspaper</option><option value="transport">transport</option>
    <option value="amenity">amenity</option><option value="housekeeping">housekeeping</option><option value="other">other</option></select>
  <input name="code" class="form-control" placeholder="code" style="width:120px" required>
  <input name="label" class="form-control" placeholder="Label shown at the desk" size="30" required>
  <input type="number" name="sort" class="form-control" placeholder="#" style="width:70px">
  <button name="saveOption" class="btn btn-primary">Save option</button></form>

<h4>Tags</h4>
<table class="table table-condensed"><tbody>
{foreach $tags as $t}<tr><td><span class="badge crm-tag-{$t.colour|escape:'html':'UTF-8'}">{$t.name|escape:'html':'UTF-8'}</span></td><td><small>{$t.code|escape:'html':'UTF-8'}</small></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_tag" value="{(int)$smarty.get.id_tag}">
  <input name="tcode" class="form-control" placeholder="code" style="width:120px" required>
  <input name="tname" class="form-control" placeholder="Name" required>
  <select name="tcolour" class="form-control"><option value="default">grey</option><option value="info">blue</option><option value="success">green</option><option value="warning">gold</option><option value="danger">red</option></select>
  <button name="saveTagDef" class="btn btn-primary">Save tag</button></form>
</div></div>
</div></div>
