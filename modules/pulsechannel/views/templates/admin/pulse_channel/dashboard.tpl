<div class="pulse-ch"><div class="panel"><h3><i class="icon-exchange"></i> Channel Manager — {$business_date} <small class="text-muted">ARI window {$window} days</small></h3>
{if !$fd}<div class="alert alert-info">Front Desk is not installed. Availability still comes off real QloApps bookings, but group blocks, out-of-order rooms and overbooking limits are not counted.</div>{/if}
{if $unmapped}<div class="alert alert-danger"><b>{$unmapped|count} unmapped room type(s) on enabled channels.</b> Unmapped inventory is the number one cause of overbooking — an OTA keeps selling what we never told it about.
<ul class="ch-unmapped">{foreach $unmapped as $u}<li>{$u.channel|escape:'html':'UTF-8'} — <b>{$u.room_type|escape:'html':'UTF-8'}</b> ({$u.rooms} rooms) <a class="btn btn-xs btn-danger" href="{$map_url}&id_channel={$u.id_pulse_ch_channel}">Map it now</a></li>{/foreach}</ul></div>{/if}
{if $broken}<div class="alert alert-warning"><b>{$broken|count} mapping(s) are dead</b> (blank code, deleted room type or a disabled rate plan) — they push nothing:
{foreach $broken as $b}<span class="label label-warning">{$b.channel|escape:'html':'UTF-8'}: {$b.room_type|default:'(deleted room type)'|escape:'html':'UTF-8'} / {$b.rate_plan|default:'(no rate plan)'|escape:'html':'UTF-8'}</span> {/foreach}
<a class="btn btn-xs btn-default" href="{$map_url}">Fix mappings</a></div>{/if}

<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#c-chan">Channels ({$channels|count})</a></li><li><a data-toggle="tab" href="#c-queue">Queue ({$queue|count})</a></li><li><a data-toggle="tab" href="#c-res">Reservations</a></li><li><a data-toggle="tab" href="#c-parity">Parity</a></li><li><a data-toggle="tab" href="#c-prod">Production</a></li><li><a data-toggle="tab" href="#c-ops">Operations</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="c-chan"><table class="table table-condensed"><thead><tr><th>Channel</th><th>Mode</th><th>Health</th><th>Last success</th><th>Queue</th><th>Maps</th><th>Unmapped</th><th>Errors 24h</th><th>Bookings 30d</th><th>Revenue 30d</th><th>Failed</th><th></th></tr></thead><tbody>
{foreach $channels as $c}<tr class="{if !$c.enabled}text-muted{elseif $c.health=='down'}danger{elseif $c.health=='degraded' || $c.unmapped>0}warning{/if}">
<td><b>{$c.name|escape:'html':'UTF-8'}</b> <small class="text-muted">{$c.code|escape:'html':'UTF-8'}</small>{if $c.test_mode} <span class="label label-default">test</span>{/if}{if !$c.enabled} <span class="label label-default">off</span>{/if}</td>
<td>{$c.sync_mode}</td>
<td><span class="ch-dot ch-{$c.health}"></span> {$c.health}{if $c.last_error} <small class="text-danger" title="{$c.last_error|escape}">{$c.last_error|truncate:40|escape:'html':'UTF-8'}</small>{/if}</td>
<td>{if $c.last_success}{$c.last_success|date_format:"%d/%m %H:%M"}{if $c.minutes_since_success !== null} <small class="text-muted">({$c.minutes_since_success}m)</small>{/if}{else}<em>never</em>{/if}</td>
<td>{$c.queue_pending}{if $c.queue_failed} / <span class="text-warning">{$c.queue_failed} failed</span>{/if}{if $c.queue_poison} / <span class="text-danger">{$c.queue_poison} poison</span>{/if}</td>
<td>{$c.mappings}</td><td>{if $c.unmapped}<span class="badge ch-bad">{$c.unmapped}</span>{else}0{/if}</td>
<td>{$c.errors_24h}/{$c.calls_24h}{if $c.error_rate > 0} <small>({$c.error_rate}%)</small>{/if}</td>
<td>{$c.res_30d}</td><td>{displayPrice price=$c.rev_30d}</td>
<td>{if $c.res_failed}<a class="badge ch-bad" href="{$res_url}&id_channel={$c.id_pulse_ch_channel}">{$c.res_failed}</a>{else}0{/if}</td>
<td class="text-right"><form method="post" class="inline"><input type="hidden" name="id_channel" value="{$c.id_pulse_ch_channel}">
<button name="testChannel" class="btn btn-xs btn-default" title="Test connection"><i class="icon-plug"></i></button>
<button name="syncNow" class="btn btn-xs btn-primary" title="Sync now"><i class="icon-refresh"></i></button>
<a class="btn btn-xs btn-default" href="{$ari_url}&id_channel={$c.id_pulse_ch_channel}" title="ARI"><i class="icon-calendar"></i></a>
<a class="btn btn-xs btn-default" href="{$set_url}&id_channel={$c.id_pulse_ch_channel}" title="Settings"><i class="icon-cogs"></i></a></form></td></tr>
{foreachelse}<tr><td colspan="12"><em>No channels yet — add one in <a href="{$set_url}">Channel Settings</a>.</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><button name="syncNow" class="btn btn-primary"><i class="icon-refresh"></i> Sync everything now</button>
<button name="rebuildAri" class="btn btn-default" onclick="return confirm('Recompute the whole ARI window for every channel?')"><i class="icon-repeat"></i> Rebuild ARI</button></form></div>

<div class="tab-pane" id="c-queue"><table class="table table-condensed"><thead><tr><th>#</th><th>Channel</th><th>Room type</th><th>Dates</th><th>Type</th><th>Reason</th><th>Status</th><th>Attempts</th><th>Next try</th><th>Last error</th><th></th></tr></thead><tbody>
{foreach $queue as $q}<tr class="{if $q.status=='poison'}danger{elseif $q.status=='failed'}warning{/if}"><td>{$q.id_pulse_ch_queue}</td><td>{$q.channel|escape:'html':'UTF-8'}</td><td>{$q.room_type|default:'all mapped'|escape:'html':'UTF-8'}</td><td>{$q.date_from} → {$q.date_to}</td><td>{$q.type}</td><td>{$q.reason|escape:'html':'UTF-8'}</td><td>{$q.status}</td><td>{$q.attempts}</td><td>{$q.next_attempt_at|date_format:"%d/%m %H:%M"}</td><td><small>{$q.last_error|truncate:70|escape:'html':'UTF-8'}</small></td>
<td>{if $q.status=='failed' || $q.status=='poison'}<form method="post" class="inline"><input type="hidden" name="id_queue" value="{$q.id_pulse_ch_queue}"><button name="requeue" class="btn btn-xs btn-default">Retry</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="11"><em>Queue is empty — everything the channels know matches what we hold.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="c-res">
{if $failed_res}<h4 class="text-danger">Failed — never dropped, waiting for a human ({$failed_res|count})</h4>
<table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Room code</th><th>Stay</th><th>Amount</th><th>Error</th><th></th></tr></thead><tbody>
{foreach $failed_res as $r}<tr class="danger"><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.channel_room_code|escape:'html':'UTF-8'}</td><td>{$r.date_from} → {$r.date_to}</td><td>{displayPrice price=$r.amount_tax_incl}</td><td><small>{$r.error|truncate:80|escape:'html':'UTF-8'}</small></td><td><a class="btn btn-xs btn-danger" href="{$res_url}&id_res={$r.id_pulse_ch_reservation}">Assign</a></td></tr>{/foreach}</tbody></table>{/if}
<h4>Latest delivered</h4><table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Room type</th><th>Stay</th><th>Rooms</th><th>Gross</th><th>Commission</th><th>Net</th><th>Status</th><th>Order</th></tr></thead><tbody>
{foreach $recent as $r}<tr class="{if $r.overbooked}warning{/if}"><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.room_type|escape:'html':'UTF-8'}</td><td>{$r.date_from} → {$r.date_to}</td><td>{$r.rooms}</td><td>{displayPrice price=$r.amount_tax_incl}</td><td>{displayPrice price=$r.commission_amount}</td><td>{displayPrice price=$r.net_amount}</td><td>{$r.status}{if $r.overbooked} <span class="label label-warning">oversold</span>{/if}</td><td>{$r.order_ref|escape:'html':'UTF-8'}{if $r.room_num} · {$r.room_num|escape:'html':'UTF-8'}{/if}</td></tr>
{foreachelse}<tr><td colspan="11"><em>No channel reservations yet.</em></td></tr>{/foreach}</tbody></table>
<a class="btn btn-default" href="{$res_url}">Open the reservations screen</a></div>

<div class="tab-pane" id="c-parity"><p class="text-muted">Our computed rate against what each channel was last told, next 30 days.</p>
<table class="table table-condensed"><thead><tr><th>Channel</th><th>In sync</th><th>Drifting</th><th>Waiting to push</th><th>Never pushed</th><th>Worst drift</th></tr></thead><tbody>
{foreach $parity as $p}<tr class="{if $p.drift > 0 || $p.never_pushed > 0}warning{/if}"><td>{$p.channel|escape:'html':'UTF-8'}</td><td>{$p.ok}</td><td>{if $p.drift}<span class="badge ch-bad">{$p.drift}</span>{else}0{/if}</td><td>{$p.pending}</td><td>{if $p.never_pushed}<span class="badge ch-bad">{$p.never_pushed}</span>{else}0{/if}</td><td>{displayPrice price=$p.worst}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No ARI computed yet — press Rebuild ARI.</em></td></tr>{/foreach}</tbody></table>
<a class="btn btn-default" href="{$ari_url}">Open the ARI calendar</a></div>

<div class="tab-pane" id="c-prod"><form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulseChannel"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control"> <button class="btn btn-default">Run</button></form>
<table class="table table-condensed"><thead><tr><th>Channel</th><th>Bookings</th><th>Rooms</th><th>Room nights</th><th>Gross</th><th>Commission</th><th>Net</th><th>ADR</th></tr></thead><tbody>
{foreach $production as $p}<tr><td>{$p.channel|escape:'html':'UTF-8'}</td><td>{$p.bookings}</td><td>{$p.rooms}</td><td>{$p.room_nights}</td><td>{displayPrice price=$p.gross}</td><td>{displayPrice price=$p.commission}</td><td>{displayPrice price=$p.net}</td><td>{displayPrice price=$p.adr}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No production in this period.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="c-ops">
<div class="row"><div class="col-md-5"><h4>Close out a date range</h4><p class="text-muted">Stop-sell everything (or re-open) on one or every channel — for a power cut, a flooded floor, a full house.</p>
<form method="post" class="form-inline"><select name="id_channel" class="form-control"><option value="0">All enabled channels</option>{foreach $channels as $c}{if $c.enabled}<option value="{$c.id_pulse_ch_channel}">{$c.name|escape:'html':'UTF-8'}</option>{/if}{/foreach}</select>
<input type="date" name="co_from" value="{$business_date}" class="form-control"> <input type="date" name="co_to" value="{$business_date}" class="form-control">
<select name="co_open" class="form-control"><option value="0">Stop-sell</option><option value="1">Re-open</option></select>
<button name="closeOut" class="btn btn-danger" onclick="return confirm('Apply this to every mapped room type on the selected channels for that range?')">Apply</button></form></div>
<div class="col-md-7"><h4>Recent errors</h4><table class="table table-condensed"><tbody>
{foreach $errors_log as $l}<tr class="danger"><td>{$l.date_add|date_format:"%d/%m %H:%M"}</td><td>{$l.channel|escape:'html':'UTF-8'}</td><td>{$l.type|escape:'html':'UTF-8'}</td><td><small>{$l.error|truncate:90|escape:'html':'UTF-8'}</small></td></tr>
{foreachelse}<tr><td><em>No errors in the log.</em></td></tr>{/foreach}</tbody></table>
<h4>24-hour health</h4><table class="table table-condensed"><thead><tr><th>Channel</th><th>Calls</th><th>Errors</th><th>Avg ms</th><th>Slowest</th></tr></thead><tbody>
{foreach $health as $h}<tr><td>{$h.name|escape:'html':'UTF-8'}</td><td>{$h.calls}</td><td>{$h.errors}</td><td>{$h.avg_ms}</td><td>{$h.max_ms}</td></tr>{/foreach}</tbody></table>
<p class="text-muted"><b>Cron:</b> <code>php modules/pulsechannel/cron/sync.php {$cron_token}</code> every 5 minutes, <code>php modules/pulsechannel/cron/rebuild_ari.php {$cron_token}</code> nightly after the audit.</p></div></div></div>

</div></div></div>
