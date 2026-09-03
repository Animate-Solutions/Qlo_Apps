<div class="panel"><h3><i class="icon-certificate"></i> Pulse License</h3>
<div class="alert alert-{if $lic.state=='valid'}success{elseif $lic.state=='grace' || $lic.state=='over_cap'}warning{else}danger{/if}"><strong>{$lic.state|replace:'_':' '|upper}</strong> — {$lic.message}</div>
{if $lic.payload}
<table class="table" style="width:auto"><tbody>
<tr><td>Licensee</td><td><strong>{$lic.licensee}</strong> — {$lic.payload.property}</td></tr>
<tr><td>License ID</td><td><code>{$lic.lid}</code></td></tr>
<tr><td>Type</td><td>{$lic.type|ucfirst}{if $lic.type=='perpetual'} (updates &amp; support until {$lic.maintenance_until|default:'—'}){else}, expires {$lic.expires}{if $lic.days_left!==null} ({$lic.days_left} days){/if}{/if}</td></tr>
<tr><td>Domains</td><td>{", "|implode:$lic.payload.domains}</td></tr>
<tr><td>Rooms</td><td>{$lic.rooms_used} in use / {if $lic.rooms_cap}{$lic.rooms_cap} licensed{else}unlimited{/if}</td></tr>
<tr><td>Modules</td><td>{foreach $modules as $k=>$n}<span class="label label-{if in_array('*',$lic.modules) || in_array($k,$lic.modules)}success{else}default{/if}">{$n}</span> {/foreach}</td></tr>
{if $lic.payload.server}<tr><td>License server</td><td>{$lic.payload.server} — last confirmed {$last_ok|default:'never'} <form method="post" class="inline"><button name="refreshLicense" class="btn btn-xs btn-default">Check now</button></form></td></tr>{/if}
<tr><td>Activated</td><td>{$activated|default:'—'}</td></tr>
</tbody></table>
<form method="post"><button name="deactivateKey" class="btn btn-default btn-sm" onclick="return confirm('Remove the license from this site?')">Remove license</button></form>
{/if}
</div>
<div class="row">
<div class="col-md-7"><div class="panel"><h3>Activate a license key</h3>
<form method="post"><textarea name="license_key" class="form-control" rows="6" placeholder="Paste the key issued by Animate Solutions" required></textarea><br>
<button name="activateKey" class="btn btn-primary">Activate</button></form>
<p class="help-block">Keys are bound to the site domain. Send Animate the domain and fingerprint below when ordering.</p>
<p>Domain: <code>{$domain}</code><br>Fingerprint: <code>{$fingerprint}</code></p></div></div>
<div class="col-md-5"><div class="panel"><h3>Trial</h3>
{if $trial_used}<p>Trial {if $lic.type=='trial'}running{else}used{/if} — expires/expired {$trial_used}.</p>{else}<form method="post"><button name="startTrial" class="btn btn-default">Start 30-day trial</button></form><p class="help-block">All modules, unlimited rooms, once per installation.</p>{/if}
<h3>Licensing models</h3><ul><li><strong>Perpetual</strong> — one-off fee per property, room-band priced; includes 12 months of updates &amp; support, renewable annually (software keeps working after maintenance lapses; updates stop).</li><li><strong>Subscription</strong> — monthly/annual per property; all updates included; 14-day grace after expiry, then read-only until renewed.</li></ul></div></div>
</div>
<div class="panel"><h3>License log</h3><table class="table table-condensed"><tbody>{foreach $log as $l}<tr><td>{$l.date_add}</td><td>{$l.event}</td><td>{$l.lid}</td><td>{$l.detail}</td></tr>{/foreach}</tbody></table></div>
