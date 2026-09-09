<div class="pulse-kc" id="pulse-kc-encoders" data-ajax="{$ajax_url|escape:'html':'UTF-8'}" data-agent="{$local_agent|escape:'html':'UTF-8'}" data-agent-port="{$agent_port|escape:'html':'UTF-8'}">
<div class="panel"><h3><i class="icon-hdd"></i> Encoders</h3>
<p>One row per physical encoder. Credentials are stored encrypted; nothing here is ever written to the audit trail in clear. An encoder that has not answered for {$stale_hrs|escape:'html':'UTF-8'}h is flagged offline by the hourly cron so the Key Desk stops routing keys to it.</p>
<table class="table table-condensed"><thead><tr><th>Name</th><th>Location</th><th>Adapter</th><th>Connection</th><th>Encoder ref</th><th>Status</th><th>Last seen</th><th>Keys cut</th><th>Can do</th><th></th></tr></thead><tbody>
{foreach $encoders as $e}<tr class="{if !$e.active}text-muted{elseif $e.status=='offline' || $e.stale}danger{elseif $e.status=='online'}success{/if}">
  <td><strong>{$e.name|escape:'html':'UTF-8'}</strong>{if $e.test_mode} <span class="label label-warning">test</span>{/if}{if $e.local_only} <span class="label label-default" title="Only reachable from the clerk workstation">local</span>{/if}</td>
  <td>{$e.location|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$e.adapter|replace:'PulseKcAdapter':''|escape:'html':'UTF-8'}</td>
  <td><code>{$e.protocol|escape:'html':'UTF-8'}://{$e.host|escape:'html':'UTF-8'}{if $e.port}:{$e.port|escape:'html':'UTF-8'}{/if}{$e.endpoint|escape:'html':'UTF-8'}</code></td>
  <td>{$e.encoder_ref|default:'—'|escape:'html':'UTF-8'}</td>
  <td>{$e.status|escape:'html':'UTF-8'}{if $e.stale} <span class="label label-danger">stale</span>{/if}{if $e.last_error}<br><small class="text-danger">{$e.last_error|truncate:60|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{$e.last_seen|default:'never'|escape:'html':'UTF-8'}</td><td>{$e.keys_encoded|escape:'html':'UTF-8'}</td>
  <td><small>{if isset($e.capabilities.vendor)}{$e.capabilities.vendor|escape:'html':'UTF-8'}{/if}{if !empty($e.capabilities.mobile)} · mobile{/if}{if !empty($e.capabilities.audit)} · audit{/if}{if !empty($e.capabilities.blacklist)} · blacklist{/if}{if !empty($e.capabilities.multi_room)} · multi-room{/if}</small></td>
  <td class="kc-actions"><form method="post" class="inline"><input type="hidden" name="id_encoder" value="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}">
    <button name="testEncoder" class="btn btn-xs btn-default">Test</button>
    <a class="btn btn-xs btn-primary" href="{$self_url}&id_encoder={$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}">Edit</a>
    {if $e.active}<button name="deleteEncoder" class="btn btn-xs btn-danger" onclick="return confirm('Deactivate this encoder?')">Off</button>{/if}
    <button type="button" class="btn btn-xs btn-info kc-agent-test" data-id="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}" data-local="{$e.local_only|escape:'html':'UTF-8'}">Test from this PC</button>
  </form></td></tr>
{/foreach}
</tbody></table>
{if $jobs}<p class="text-warning">{$jobs|count} queued/failed encoder operation(s). <form method="post" class="inline"><button name="runQueue" class="btn btn-xs btn-primary">Run the queue now</button></form></p>{/if}
</div>

<div class="panel"><h3>{if $edit}Edit {$edit.name|escape:'html':'UTF-8'}{else}Add an encoder{/if}</h3>
<form method="post" class="form-horizontal">
  <input type="hidden" name="id_encoder" value="{if $edit}{$edit.id_pulse_kc_encoder|escape:'html':'UTF-8'}{/if}">
  <div class="row"><div class="col-md-6">
    <div class="form-group"><label class="col-sm-4">Workstation name</label><div class="col-sm-8"><input name="name" class="form-control" value="{if $edit}{$edit.name|escape:'html':'UTF-8'}{/if}" placeholder="Front Desk 1" required></div></div>
    <div class="form-group"><label class="col-sm-4">Location</label><div class="col-sm-8"><select name="location" class="form-control">{foreach $locations as $l}<option value="{$l|escape:'html':'UTF-8'}" {if $edit && $edit.location==$l}selected{/if}>{$l|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
    <div class="form-group"><label class="col-sm-4">Lock system</label><div class="col-sm-8"><select name="adapter" class="form-control">{foreach $adapters as $c => $n}<option value="{$c|escape:'html':'UTF-8'}" {if $edit && $edit.adapter==$c}selected{/if}>{$n|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
    <div class="form-group"><label class="col-sm-4">Protocol</label><div class="col-sm-8"><select name="protocol" class="form-control">{foreach $protocols as $p}<option value="{$p|escape:'html':'UTF-8'}" {if $edit && $edit.protocol==$p}selected{/if}>{$p|escape:'html':'UTF-8'}</option>{/foreach}</select><p class="help-block">"local" = the simulator or an agent on the clerk's own machine; no server-side call is made.</p></div></div>
    <div class="form-group"><label class="col-sm-4">Host</label><div class="col-sm-8"><input name="host" class="form-control" value="{if $edit}{$edit.host|escape:'html':'UTF-8'}{/if}" placeholder="127.0.0.1"></div></div>
    <div class="form-group"><label class="col-sm-4">Port</label><div class="col-sm-8"><input name="port" type="number" class="form-control" value="{if $edit}{$edit.port|escape:'html':'UTF-8'}{/if}"></div></div>
    <div class="form-group"><label class="col-sm-4">Endpoint / base path</label><div class="col-sm-8"><input name="endpoint" class="form-control" value="{if $edit}{$edit.endpoint|escape:'html':'UTF-8'}{else}/{/if}"></div></div>
    <div class="form-group"><label class="col-sm-4">Encoder / terminal id</label><div class="col-sm-8"><input name="encoder_ref" class="form-control" value="{if $edit}{$edit.encoder_ref|escape:'html':'UTF-8'}{/if}" placeholder="ENC01"></div></div>
    <div class="form-group"><label class="col-sm-4">Timeout (s)</label><div class="col-sm-8"><input name="timeout_sec" type="number" class="form-control" value="{if $edit}{$edit.timeout_sec|escape:'html':'UTF-8'}{else}8{/if}"></div></div>
  </div><div class="col-md-6">
    <div class="form-group"><label class="col-sm-4">Operator / user</label><div class="col-sm-8"><input name="cred_user" class="form-control" autocomplete="off" placeholder="{if $edit && $edit.credentials_enc}unchanged — type to replace{/if}"></div></div>
    <div class="form-group"><label class="col-sm-4">Password</label><div class="col-sm-8"><input name="cred_password" type="password" class="form-control" autocomplete="new-password"></div></div>
    <div class="form-group"><label class="col-sm-4">API key / token</label><div class="col-sm-8"><input name="cred_api_key" class="form-control" autocomplete="off"></div></div>
    <div class="form-group"><label class="col-sm-4">Onity site code</label><div class="col-sm-8"><input name="cred_site_code" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Dormakaba site id</label><div class="col-sm-8"><input name="cred_site_id" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Salto installation</label><div class="col-sm-8"><input name="cred_installation" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Hune hotel code</label><div class="col-sm-8"><input name="cred_hotel_code" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Adapter options (JSON)</label><div class="col-sm-8"><textarea name="options_json" class="form-control" rows="3" placeholder='{literal}{"max_rooms":4,"audit_limit":200,"insecure":0}{/literal}'>{if $edit}{$edit.options_json|escape:'html':'UTF-8'}{/if}</textarea></div></div>
    <div class="form-group"><div class="col-sm-8 col-sm-offset-4">
      <label><input type="checkbox" name="local_only" value="1" {if $edit && $edit.local_only}checked{/if}> Reachable only from the clerk workstation</label><br>
      <label><input type="checkbox" name="test_mode" value="1" {if $edit && $edit.test_mode}checked{/if}> Test mode (vendor does not burn a real card)</label><br>
      <label><input type="checkbox" name="active" value="1" {if !$edit || $edit.active}checked{/if}> Active</label>
    </div></div>
    <div class="form-group"><div class="col-sm-8 col-sm-offset-4"><button name="saveEncoder" class="btn btn-primary">Save encoder</button></div></div>
  </div></div>
</form>
<p class="help-block"><strong>Credentials are write-only.</strong> Leave a field blank to keep what is stored; anything you type replaces it. Values are encrypted with the shop key before they touch the database.</p>
</div>
</div>
