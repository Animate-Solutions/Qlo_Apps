<div class="pulse-kc"><div class="panel">
<h3><i class="icon-key"></i> {if $k}{$k.key_no|escape:'html':'UTF-8'}{else}Key not found{/if} <a class="btn btn-default btn-xs pull-right" href="{$self_url}">Back to the register</a></h3>
{if $k}
<div class="row">
  <div class="col-md-6"><table class="table table-condensed">
    <tr><th>Type</th><td>{$k.type|replace:'_':' '|escape:'html':'UTF-8'}{if $k.mobile} · mobile credential{/if}{if $k.mechanical} · mechanical fallback{/if}</td></tr>
    <tr><th>Status</th><td>{$k.status|escape:'html':'UTF-8'}{if $k.cancel_reason} — {$k.cancel_reason|escape:'html':'UTF-8'}{/if}</td></tr>
    <tr><th>Rooms</th><td>{$k.room_nums|default:'—'|escape:'html':'UTF-8'}</td></tr>
    <tr><th>Guest / holder</th><td>{$k.holder_name|default:$k.guest_name|escape:'html':'UTF-8'}{if $k.staff_group} <small class="text-muted">({$k.staff_group|escape:'html':'UTF-8'})</small>{/if}</td></tr>
    <tr><th>Valid</th><td>{$k.valid_from|escape:'html':'UTF-8'} → {$k.valid_to|escape:'html':'UTF-8'}</td></tr>
    <tr><th>Overrides</th><td>{if $k.override_deadbolt}deadbolt{/if} {if $k.override_dnd}do-not-disturb{/if}{if !$k.override_deadbolt && !$k.override_dnd}none{/if}</td></tr>
  </table></div>
  <div class="col-md-6"><table class="table table-condensed">
    <tr><th>Card serial</th><td><code>{$k.card_serial|default:'—'|escape:'html':'UTF-8'}</code></td></tr>
    <tr><th>Sequence</th><td>{$k.sequence|escape:'html':'UTF-8'}</td></tr>
    <tr><th>Vendor reference</th><td><code>{$k.key_ref|default:'—'|escape:'html':'UTF-8'}</code></td></tr>
    <tr><th>Encoder / adapter</th><td>{$k.encoder_name|default:'—'|escape:'html':'UTF-8'} · {$k.adapter|escape:'html':'UTF-8'}</td></tr>
    <tr><th>Payload</th><td>{if $k.payload_hash}<span class="label label-success">stored encrypted</span> <code>sha256:{$k.payload_hash|truncate:16:''|escape:'html':'UTF-8'}</code>{else}<em>none</em>{/if}</td></tr>
    <tr><th>Issued</th><td>{$k.issued_at|default:'—'|escape:'html':'UTF-8'} by {$k.issued_by_name|default:'—'|escape:'html':'UTF-8'}</td></tr>
    <tr><th>Cancelled</th><td>{$k.cancelled_at|default:'—'|escape:'html':'UTF-8'}</td></tr>
    {if $k.last_error}<tr><th>Last error</th><td class="text-danger">{$k.last_error|escape:'html':'UTF-8'}</td></tr>{/if}
  </table></div>
</div>

{if $mobile}<h4>Mobile credentials on this key</h4>
<table class="table table-condensed"><thead><tr><th>Issued</th><th>Channel</th><th>Device bound</th><th>Credential expires</th><th>Refreshes</th><th>Status</th></tr></thead><tbody>
{foreach $mobile as $m}<tr><td>{$m.date_add|escape:'html':'UTF-8'}</td><td>{$m.channel|escape:'html':'UTF-8'}</td><td>{$m.device_bound_at|default:'—'|escape:'html':'UTF-8'}</td><td>{$m.credential_exp|escape:'html':'UTF-8'}</td><td>{$m.refresh_count|escape:'html':'UTF-8'}</td><td>{$m.status|escape:'html':'UTF-8'}</td></tr>{/foreach}
</tbody></table>{/if}

<h4>Where this card has been used</h4>
<table class="table table-condensed"><thead><tr><th>When</th><th>Door</th><th>Room</th><th>Event</th><th>Result</th><th>Battery</th></tr></thead><tbody>
{foreach $audit as $a}<tr class="{if $a.result=='denied'}warning{/if}"><td>{$a.opened_at|escape:'html':'UTF-8'}</td><td>{$a.door_name|escape:'html':'UTF-8'}</td><td>{$a.room_num|default:'—'|escape:'html':'UTF-8'}</td><td>{$a.event|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$a.result|escape:'html':'UTF-8'}</td><td>{if $a.battery_pct !== null}{$a.battery_pct|escape:'html':'UTF-8'}%{else}—{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No lock audit rows carry this serial yet</em></td></tr>{/foreach}
</tbody></table>
{/if}
</div></div>
