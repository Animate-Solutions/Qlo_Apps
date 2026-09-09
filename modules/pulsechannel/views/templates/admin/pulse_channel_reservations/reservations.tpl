<div class="pulse-ch"><div class="panel"><h3><i class="icon-inbox"></i> Channel Reservations</h3>
<form method="get" class="form-inline ch-filter"><input type="hidden" name="controller" value="AdminPulseChannelReservations"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_channel" class="form-control"><option value="0">All channels</option>{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}" {if $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control"> <button class="btn btn-default">Filter</button></form>
<form method="post" class="inline"><input type="hidden" name="id_channel" value="{$id_channel}"><button name="pullNow" class="btn btn-primary"><i class="icon-download"></i> Pull now</button></form>

{if $detail}
<div class="panel ch-detail"><h4>{$detail.channel|escape:'html':'UTF-8'} — {$detail.channel_ref|escape:'html':'UTF-8'} <span class="label label-{if $detail.status=='failed'}danger{elseif $detail.status=='delivered' || $detail.status=='modified'}success{else}default{/if}">{$detail.status}</span></h4>
{if $detail.error}<div class="alert alert-danger">{$detail.error|escape:'html':'UTF-8'}</div>{/if}
<div class="row"><div class="col-md-5"><table class="table table-condensed"><tbody>
<tr><th>Guest</th><td>{$detail.guest_name|escape:'html':'UTF-8'} {if $detail.email}&lt;{$detail.email|escape:'html':'UTF-8'}&gt;{/if} {$detail.phone|escape:'html':'UTF-8'}</td></tr>
<tr><th>Stay</th><td>{$detail.date_from} → {$detail.date_to} · {$detail.rooms} room(s) · {$detail.adults}A/{$detail.children}C</td></tr>
<tr><th>Channel codes</th><td><code>{$detail.channel_room_code|escape:'html':'UTF-8'}</code> / <code>{$detail.channel_rate_code|escape:'html':'UTF-8'}</code></td></tr>
<tr><th>Money</th><td>{displayPrice price=$detail.amount_tax_incl} gross · tax {displayPrice price=$detail.tax_amount} · commission {$detail.commission_pct}% = {displayPrice price=$detail.commission_amount} · net {displayPrice price=$detail.net_amount}</td></tr>
<tr><th>Payment</th><td>{$detail.payment_type|replace:'_':' '}</td></tr>
<tr><th>QloApps</th><td>{if $detail.id_order}order #{$detail.id_order}, bookings {$detail.booking_ids|escape:'html':'UTF-8'}{else}<em>not created yet</em>{/if}</td></tr>
<tr><th>Acked</th><td>{if $detail.acked}yes, {$detail.acked_at}{else}no{/if}</td></tr>
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_res_a" value="{$detail.id_pulse_ch_reservation}">
{if $detail.status!='delivered' && $detail.status!='modified'}<button name="deliverRes" class="btn btn-success">Create the booking</button>{/if}
<button name="retryRes" class="btn btn-default">Retry</button>
{if $detail.id_htl_booking}<button name="cancelRes" class="btn btn-danger" onclick="return confirm('Cancel this booking in QloApps?')">Cancel booking</button>{/if}
{if !$detail.acked}<button name="ackRes" class="btn btn-default">Acknowledge</button>{/if}</form></div>
<div class="col-md-7"><h5>Manual assign</h5><p class="text-muted">Fix what the payload got wrong, then deliver. Nothing here is thrown away.</p>
<form method="post" class="form-horizontal"><input type="hidden" name="id_res_a" value="{$detail.id_pulse_ch_reservation}">
<div class="form-group"><label class="col-sm-3">Room type</label><div class="col-sm-9"><select name="a_product" class="form-control">{foreach $room_types as $rt}<option value="{$rt.id_product}" {if $detail.id_product==$rt.id_product}selected{/if}>{$rt.name|escape:'html':'UTF-8'} ({$rt.rooms} rooms)</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3">Rate plan</label><div class="col-sm-9"><select name="a_plan" class="form-control">{foreach $rate_plans as $rp}<option value="{$rp.id_pulse_ch_rate_plan}" {if $detail.id_pulse_ch_rate_plan==$rp.id_pulse_ch_rate_plan}selected{/if}>{$rp.code|escape:'html':'UTF-8'} — {$rp.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3">Stay</label><div class="col-sm-9"><input type="date" name="a_from" value="{$detail.date_from}" class="form-control"><input type="date" name="a_to" value="{$detail.date_to}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-3">Rooms / adults / children</label><div class="col-sm-9"><input name="a_rooms" value="{$detail.rooms}" class="form-control" size="3"><input name="a_adults" value="{$detail.adults}" class="form-control" size="3"><input name="a_children" value="{$detail.children}" class="form-control" size="3"></div></div>
<div class="form-group"><label class="col-sm-3">Guest / email</label><div class="col-sm-9"><input name="a_guest" value="{$detail.guest_name|escape}" class="form-control"><input name="a_email" value="{$detail.email|escape}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-3">Gross amount</label><div class="col-sm-9"><input name="a_amount" value="{$detail.amount_tax_incl}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-3">Note</label><div class="col-sm-9"><input name="a_notes" value="{$detail.notes|escape}" class="form-control"><label><input type="checkbox" name="a_ignore" value="1"> Ignore this one instead (test booking, duplicate)</label></div></div>
<button name="assignRes" class="btn btn-primary btn-lg">Assign &amp; deliver</button></form></div></div>
<h5>Raw payload</h5><pre class="ch-raw">{$detail.raw_payload|escape}</pre>
<a class="btn btn-default" href="{$self_url}">Back to the list</a></div>
{/if}

<ul class="nav nav-tabs"><li class="active{if $failed} ch-alerttab{/if}"><a data-toggle="tab" href="#r-failed">Failed ({$failed|count})</a></li><li><a data-toggle="tab" href="#r-new">Awaiting delivery ({$received|count})</a></li><li><a data-toggle="tab" href="#r-ok">Delivered ({$delivered|count})</a></li><li><a data-toggle="tab" href="#r-cancel">Cancelled / ignored ({$cancelled|count})</a></li><li><a data-toggle="tab" href="#r-prod">Production</a></li><li><a data-toggle="tab" href="#r-paste">Paste a payload</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="r-failed">{if $failed}<p class="text-danger">These bookings exist at the OTA and do not exist here. Assign each one.</p>{/if}
<table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Room code</th><th>Rate code</th><th>Stay</th><th>Rooms</th><th>Amount</th><th>Error</th><th></th></tr></thead><tbody>
{foreach $failed as $r}<tr class="danger"><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td><code>{$r.channel_room_code|escape:'html':'UTF-8'}</code></td><td><code>{$r.channel_rate_code|escape:'html':'UTF-8'}</code></td><td>{$r.date_from} → {$r.date_to}</td><td>{$r.rooms}</td><td>{displayPrice price=$r.amount_tax_incl}</td><td><small>{$r.error|truncate:70|escape:'html':'UTF-8'}</small></td><td><a class="btn btn-xs btn-danger" href="{$self_url}&id_res={$r.id_pulse_ch_reservation}">Assign</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nothing in the failed queue.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="r-new"><table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Stay</th><th>Amount</th><th>Action</th><th></th></tr></thead><tbody>
{foreach $received as $r}<tr><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.date_from} → {$r.date_to}</td><td>{displayPrice price=$r.amount_tax_incl}</td><td>{$r.action}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_res_a" value="{$r.id_pulse_ch_reservation}"><button name="deliverRes" class="btn btn-xs btn-success">Deliver</button></form> <a class="btn btn-xs btn-default" href="{$self_url}&id_res={$r.id_pulse_ch_reservation}">Open</a></td></tr>
{foreachelse}<tr><td colspan="7"><em>Nothing waiting.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="r-ok"><table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Room type</th><th>Room</th><th>Stay</th><th>Rooms</th><th>Gross</th><th>Commission</th><th>Net</th><th>Order</th><th>Ack</th><th></th></tr></thead><tbody>
{foreach $delivered as $r}<tr class="{if $r.overbooked}warning{/if}"><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.room_type|escape:'html':'UTF-8'}</td><td>{$r.room_num|default:'—'|escape:'html':'UTF-8'}</td><td>{$r.date_from} → {$r.date_to}</td><td>{$r.rooms}</td><td>{displayPrice price=$r.amount_tax_incl}</td><td>{displayPrice price=$r.commission_amount}</td><td>{displayPrice price=$r.net_amount}</td><td>{$r.order_ref|escape:'html':'UTF-8'}</td><td>{if $r.acked}✓{else}—{/if}</td><td><a class="btn btn-xs btn-default" href="{$self_url}&id_res={$r.id_pulse_ch_reservation}">Open</a></td></tr>
{foreachelse}<tr><td colspan="13"><em>No delivered reservations yet.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="r-cancel"><table class="table table-condensed"><thead><tr><th>Ref</th><th>Channel</th><th>Guest</th><th>Stay</th><th>Status</th><th>Note</th></tr></thead><tbody>
{foreach $cancelled as $r}<tr class="text-muted"><td>{$r.channel_ref|escape:'html':'UTF-8'}</td><td>{$r.channel|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.date_from} → {$r.date_to}</td><td>{$r.status}</td><td>{$r.notes|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="6"><em>Nothing cancelled.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="r-prod"><table class="table table-condensed"><thead><tr><th>Channel</th><th>Bookings</th><th>Rooms</th><th>Room nights</th><th>Gross</th><th>Commission</th><th>Net</th><th>ADR</th></tr></thead><tbody>
{foreach $production as $p}<tr><td>{$p.channel|escape:'html':'UTF-8'}</td><td>{$p.bookings}</td><td>{$p.rooms}</td><td>{$p.room_nights}</td><td>{displayPrice price=$p.gross}</td><td>{displayPrice price=$p.commission}</td><td>{displayPrice price=$p.net}</td><td>{displayPrice price=$p.adr}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No production in this period.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="r-paste"><form method="post"><p class="text-muted">A booking arrived by e-mail or the OTA extranet and the link was down? Paste the JSON here and it goes through the same pipeline — dedupe, mapping, delivery, audit.</p>
<select name="paste_channel" class="form-control">{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}">{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<textarea name="paste_json" rows="10" class="form-control" placeholder='{literal}{"reference":"BDC-123456","status":"new","guest_name":"Chinedu Okafor","email":"chinedu@example.com","room_code":"DLX-KING","rate_code":"BAR","arrival":"2026-09-12","departure":"2026-09-15","rooms":1,"adults":2,"children":0,"amount":195000,"currency":"NGN","commission_pct":15,"payment_type":"hotel_collect"}{/literal}'></textarea>
<button name="pasteRes" class="btn btn-primary">Accept payload</button></form></div>

</div></div></div>
