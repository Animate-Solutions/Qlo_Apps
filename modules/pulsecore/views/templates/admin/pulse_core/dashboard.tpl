<div class="panel"><h3><i class="icon-cogs"></i> Pulse hospitality suite</h3>
{if $license}<div class="alert alert-{if $license.state=='valid'}success{elseif $license.state=='grace'}warning{else}danger{/if}">License: <strong>{$license.state|upper}</strong> — {$license.message}</div>{else}<div class="alert alert-warning">Licensing module not installed.</div>{/if}
<table class="table"><thead><tr><th>Module</th><th>Version</th><th>Status</th></tr></thead><tbody>
{foreach $modules as $m}<tr><td>{$m.display}</td><td>{$m.version}</td><td>{if $m.enabled}<span class="label label-success">enabled</span>{elseif $m.installed}<span class="label label-warning">disabled</span>{else}<span class="label label-default">not installed</span>{/if}</td></tr>{/foreach}</tbody></table></div>
<div class="panel"><h3><i class="icon-key"></i> API tokens (housekeeping phones, TV portal, POS tablets, PABX bridge)</h3>
{if $new_token}<div class="alert alert-info">New token — copy it now, it will not be shown again:<br><code style="font-size:14px">{$new_token}</code></div>{/if}
<table class="table table-condensed"><thead><tr><th>Label</th><th>Token</th><th>Scopes</th><th>Created</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $tokens as $t}<tr><td>{$t.label}</td><td><code>{$t.token_short}</code></td><td>{$t.scopes}</td><td>{$t.date_add}</td><td>{if $t.active}active{else}revoked{/if}</td><td>{if $t.active}<form method="post" class="inline"><input type="hidden" name="id_token" value="{$t.id_pulse_api_token}"><button name="revokeToken" class="btn btn-xs btn-danger" onclick="return confirm('Revoke?')">Revoke</button></form>{/if}</td></tr>{foreachelse}<tr><td colspan="6"><em>No tokens yet</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input name="label" class="form-control" placeholder="Label (e.g. HK phone 1)" required>
{foreach ['frontdesk','housekeeping','portal','pos','pabx','engineering'] as $s}<label class="checkbox-inline"><input type="checkbox" name="scopes[]" value="{$s}"> {$s}</label>{/foreach}
<button name="createToken" class="btn btn-default">Create token</button></form></div>
<div class="panel"><h3><i class="icon-list"></i> Recent audit log</h3><table class="table table-condensed"><thead><tr><th>When</th><th>Who</th><th>Module</th><th>Event</th><th>Entity</th><th>Payload</th></tr></thead><tbody>
{foreach $audit as $a}<tr><td>{$a.date_add}</td><td>{$a.who}</td><td>{$a.module}</td><td>{$a.event}</td><td>{$a.entity} {$a.id_entity}</td><td><small>{$a.payload|truncate:120}</small></td></tr>{/foreach}</tbody></table></div>
