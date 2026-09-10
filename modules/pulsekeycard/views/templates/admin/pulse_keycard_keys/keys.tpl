<div class="pulse-kc"><div class="panel"><h3><i class="icon-list"></i> Keys register</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#k-list">Cards ({$keys|count})</a></li><li><a data-toggle="tab" href="#k-mobile">Mobile keys ({$mobile_keys|count})</a></li><li><a data-toggle="tab" href="#k-queue">Retry queue ({$jobs|count})</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="k-list">
<form method="get" class="form-inline kc-filters">
  <input type="hidden" name="controller" value="AdminPulseKeycardKeys"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <input name="q" value="{$f.q|escape:'html':'UTF-8'}" class="form-control" placeholder="Key no, card serial, guest, room">
  <select name="status" class="form-control"><option value="">Any status</option>{foreach $statuses as $s}<option value="{$s|escape:'html':'UTF-8'}" {if $f.status==$s}selected{/if}>{$s|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="type" class="form-control"><option value="">Any type</option>{foreach $types as $t}<option value="{$t|escape:'html':'UTF-8'}" {if $f.type==$t}selected{/if}>{$t|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input type="date" name="from" value="{$f.from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$f.to|escape:'html':'UTF-8'}" class="form-control">
  <button class="btn btn-default">Filter</button>
</form>
<table class="table table-condensed"><thead><tr><th>Key</th><th>Type</th><th>Rooms</th><th>Holder</th><th>Card</th><th>Seq</th><th>Valid from</th><th>Valid to</th><th>Encoder</th><th>Status</th><th>Issued by</th><th></th></tr></thead><tbody>
{foreach $keys as $k}<tr class="{if $k.status=='failed'}danger{elseif $k.status=='lost'}warning{/if}">
  <td><a href="{$self_url}&id_key={$k.id_pulse_kc_key|escape:'html':'UTF-8'}">{$k.key_no|escape:'html':'UTF-8'}</a></td>
  <td>{$k.type|replace:'_':' '|escape:'html':'UTF-8'}{if $k.mobile} <span class="label label-info">mobile</span>{/if}{if $k.mechanical} <span class="label label-warning">metal</span>{/if}</td>
  <td>{$k.room_nums|default:'—'|escape:'html':'UTF-8'}</td><td>{$k.holder_name|default:$k.guest_name|escape:'html':'UTF-8'}{if $k.staff_group}<br><small class="text-muted">{$k.staff_group|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><code>{$k.card_serial|default:'—'|escape:'html':'UTF-8'}</code></td><td>{$k.sequence|escape:'html':'UTF-8'}</td>
  <td>{$k.valid_from|date_format:"%d/%m %H:%M"}</td><td>{$k.valid_to|date_format:"%d/%m %H:%M"}</td>
  <td>{$k.encoder_name|default:$k.adapter|escape:'html':'UTF-8'}</td><td>{$k.status|escape:'html':'UTF-8'}{if $k.cancel_reason}<br><small class="text-muted">{$k.cancel_reason|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{$k.issued_by_name|escape:'html':'UTF-8'}</td>
  <td class="kc-actions"><form method="post" class="inline"><input type="hidden" name="id_key" value="{$k.id_pulse_kc_key|escape:'html':'UTF-8'}">
    {if $k.status=='issued'}<input name="valid_to" class="input-sm" style="width:145px" value="{$k.valid_to|escape:'html':'UTF-8'}"><button name="extendKey" class="btn btn-xs btn-info">Extend</button>
    <button name="cancelKey" class="btn btn-xs btn-default" onclick="return confirm('Cancel key {$k.key_no|escape:'html':'UTF-8'}?')">Cancel</button>
    <button name="markLost" class="btn btn-xs btn-danger" onclick="return confirm('Mark {$k.key_no|escape:'html':'UTF-8'} lost and blacklist the card?')">Lost</button>{/if}
    {if $k.status=='failed'}<button name="retryKey" class="btn btn-xs btn-primary">Retry</button>{/if}
  </form></td></tr>
{foreachelse}<tr><td colspan="12"><em>No keys match the filter</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="k-mobile">
<table class="table table-condensed"><thead><tr><th>Issued</th><th>Key</th><th>Rooms</th><th>Guest</th><th>Channel</th><th>Device</th><th>Valid to</th><th>Credential expires</th><th>Refreshes</th><th>Delivered</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $mobile_keys as $m}<tr><td>{$m.date_add|date_format:"%d/%m %H:%M"}</td><td>{$m.key_no|escape:'html':'UTF-8'}</td><td>{$m.room_nums|escape:'html':'UTF-8'}</td><td>{$m.guest_name|escape:'html':'UTF-8'}</td><td>{$m.channel|escape:'html':'UTF-8'}</td>
  <td>{if $m.device_fingerprint}<span class="label label-default">bound {$m.device_bound_at|date_format:"%d/%m"}</span>{else}<em>open</em>{/if}</td>
  <td>{$m.valid_to|date_format:"%d/%m %H:%M"}</td><td>{$m.credential_exp|date_format:"%d/%m %H:%M"}</td><td>{$m.refresh_count|escape:'html':'UTF-8'}</td><td>{$m.delivered_via|default:'—'|escape:'html':'UTF-8'}</td><td>{$m.status|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_mobile" value="{$m.id_pulse_kc_mobile_key|escape:'html':'UTF-8'}">
    <button name="resendMobile" class="btn btn-xs btn-default">Re-send link</button>
    <button name="revokeMobile" class="btn btn-xs btn-danger" onclick="return confirm('Revoke this mobile key?')">Revoke</button></form></td></tr>
{foreachelse}<tr><td colspan="12"><em>No live mobile keys</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="k-queue">
<p>Operations parked because an encoder was unreachable. They retry on the hourly cron with a growing back-off; press Run now to force one pass.</p>
<form method="post" class="inline"><button name="runQueue" class="btn btn-primary btn-sm">Run the queue now</button></form>
<table class="table table-condensed"><thead><tr><th>Queued</th><th>Type</th><th>Key</th><th>Rooms</th><th>Attempts</th><th>Next try</th><th>Status</th><th>Last error</th></tr></thead><tbody>
{foreach $jobs as $j}<tr class="{if $j.status=='failed'}danger{/if}"><td>{$j.date_add|date_format:"%d/%m %H:%M"}</td><td>{$j.type|escape:'html':'UTF-8'}</td><td>{$j.key_no|default:'—'|escape:'html':'UTF-8'}</td><td>{$j.room_nums|default:'—'|escape:'html':'UTF-8'}</td>
  <td>{$j.attempts|escape:'html':'UTF-8'}</td><td>{$j.next_try_at|date_format:"%d/%m %H:%M"}</td><td>{$j.status|escape:'html':'UTF-8'}</td><td><small>{$j.last_error|truncate:80|escape:'html':'UTF-8'}</small></td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing queued — every encoder call has gone through</em></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
