<div class="pulse-gp"><div class="panel"><h3><i class="icon-tv"></i> Guest Portal — {$business_date|escape:'html':'UTF-8'}</h3>
<div class="row">
<div class="col-md-2"><div class="gp-kpi">{$counts.online|intval} / {$counts.active|intval}</div><small>screens online</small></div>
<div class="col-md-2"><div class="gp-kpi">{$counts.pending|intval}</div><small>waiting to be paired</small></div>
<div class="col-md-2"><div class="gp-kpi">{$orders|count}</div><small>room-service orders today</small></div>
<div class="col-md-2"><div class="gp-kpi">{$requests|count}</div><small>open guest requests</small></div>
<div class="col-md-2"><div class="gp-kpi">{$unread|intval}</div><small>unread messages</small></div>
<div class="col-md-2"><div class="gp-kpi">{if $feedback.overall}{$feedback.overall|escape:'html':'UTF-8'}{else}—{/if}</div><small>avg rating {$from|escape:'html':'UTF-8'} → {$to|escape:'html':'UTF-8'}</small></div>
</div>
<p class="help-block">URL Launcher target <code>{$portal_url}</code> &middot; API <code>{$api_url}</code> &middot; room controls via <code>{$adapter|escape:'html':'UTF-8'}</code>{if !$pos_on} &middot; <span class="text-danger">Pulse POS is off — in-room dining is hidden</span>{/if}{if !$fd_on} &middot; <span class="text-danger">Front Desk is off — folio and express check-out are hidden</span>{/if}</p>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#gp-screens">Screens</a></li><li><a data-toggle="tab" href="#gp-req">Requests ({$requests|count})</a></li><li><a data-toggle="tab" href="#gp-ord">Orders ({$orders|count})</a></li><li><a data-toggle="tab" href="#gp-msg">Messages ({$unread|intval})</a></li><li><a data-toggle="tab" href="#gp-fb">Feedback</a></li><li><a data-toggle="tab" href="#gp-vod">VOD &amp; controls</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="gp-screens">
{foreach $board as $floor => $devs}<div class="gp-floor"><strong>Floor {$floor|escape:'html':'UTF-8'}</strong> <span class="text-muted">({$devs|count})</span><div class="gp-devgrid">
{foreach $devs as $d}<a class="gp-dev {if $d.status=='pending'}pending{elseif !$d.online}off{/if}" href="{$devices_url}&amp;q={$d.uid|escape:'url'}" title="{$d.uid|escape:'html':'UTF-8'} {$d.model|escape:'html':'UTF-8'}"><span class="gp-dot {if $d.status=='pending'}pending{elseif $d.online}on{else}off{/if}"></span>{if $d.room_num}{$d.room_num|escape:'html':'UTF-8'}{else}{$d.label|escape:'html':'UTF-8'}{/if}<br><small class="text-muted">{$d.type|escape:'html':'UTF-8'}{if $d.last_seen} · {$d.last_seen|date_format:"%d/%m %H:%M"}{else} · never{/if}</small></a>{/foreach}
</div></div>{foreachelse}<p><em>No screens have booted yet. Point a TV's URL Launcher at {$portal_url} and it will appear here waiting for approval.</em></p>{/foreach}
<form method="post" class="form-inline noprint" style="margin-top:12px"><button name="reloadAll" class="btn btn-default btn-sm" data-gp-confirm="Reload every active screen?"><i class="icon-refresh"></i> Reload all screens</button></form>
</div>

<div class="tab-pane" id="gp-req"><table class="table table-condensed"><thead><tr><th>No</th><th>Room</th><th>Guest</th><th>Request</th><th>Detail</th><th>When</th><th>Ticket</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $requests as $r}<tr class="{if $r.status=='new'}warning{elseif $r.status=='failed'}danger{/if}"><td>{$r.request_no|escape:'html':'UTF-8'}</td><td>{$r.room_num|escape:'html':'UTF-8'}</td><td>{$r.guest_name|escape:'html':'UTF-8'}</td><td>{$r.type|escape:'html':'UTF-8'}</td><td>{$r.detail|escape:'html':'UTF-8'}{if $r.fail_reason}<br><small class="text-danger">{$r.fail_reason|escape:'html':'UTF-8'}</small>{/if}</td><td>{if $r.scheduled_for}{$r.scheduled_for|date_format:"%d/%m %H:%M"}{else}{$r.date_add|date_format:"%d/%m %H:%M"}{/if}</td><td>{$r.ticket_no|escape:'html':'UTF-8'}{if $r.ticket_status} <span class="label label-default">{$r.ticket_status|escape:'html':'UTF-8'}</span>{/if}</td><td>{$r.status|escape:'html':'UTF-8'}</td>
<td class="noprint"><form method="post" class="form-inline"><input type="hidden" name="id_request" value="{$r.id_pulse_gp_request|escape:'html':'UTF-8'}"><select name="status" class="input-sm"><option value="in_progress">in progress</option><option value="done">done</option><option value="cancelled">cancelled</option></select> <button name="setRequest" class="btn btn-xs btn-default">Go</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em>Nothing outstanding</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="gp-ord"><table class="table table-condensed"><thead><tr><th>Room</th><th>Check</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th><th>Ready</th><th>Problem</th></tr></thead><tbody>
{foreach $orders as $o}<tr class="{if $o.status=='failed'}danger{elseif $o.status=='ready'}success{/if}"><td>{$o.room_num|escape:'html':'UTF-8'}</td><td>{$o.check_no|escape:'html':'UTF-8'}</td><td>{$o.items_count|escape:'html':'UTF-8'}</td><td>{displayPrice price=$o.total}</td><td>{$o.status|escape:'html':'UTF-8'}</td><td>{$o.date_add|date_format:"%H:%M"}</td><td>{if $o.date_ready}{$o.date_ready|date_format:"%H:%M"}{/if}</td><td>{$o.fail_reason|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No portal orders today</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="gp-msg"><table class="table table-condensed"><thead><tr><th>Room</th><th>Guest</th><th>Last message</th><th>Unread</th><th>When</th><th></th></tr></thead><tbody>
{foreach $inbox as $i}<tr class="{if $i.unread}warning{/if}"><td>{$i.room_num|escape:'html':'UTF-8'}</td><td>{$i.guest|escape:'html':'UTF-8'}</td><td>{$i.last_body|truncate:80|escape:'html':'UTF-8'}</td><td>{$i.unread|escape:'html':'UTF-8'}</td><td>{$i.last_at|date_format:"%d/%m %H:%M"}</td><td><a class="btn btn-xs btn-default" href="{$messages_url}&amp;id_htl_booking={$i.id_htl_booking|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="6"><em>No unread messages</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input name="body" class="form-control" style="width:60%" placeholder="Broadcast to every occupied room (generator switchover, breakfast times…)" required> <button name="broadcast" class="btn btn-default" data-gp-confirm="Send this to every occupied room?">Broadcast</button></form></div>

<div class="tab-pane" id="gp-fb"><form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulseGuestPortal"><input type="hidden" name="token" value="{$smarty.get.token|escape}"><input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control"> <button class="btn btn-default">Run</button></form>
<p>{$feedback.n|escape:'html':'UTF-8'} response(s) &middot; overall {$feedback.overall|default:'—'|escape:'html':'UTF-8'} &middot; room {$feedback.room|default:'—'|escape:'html':'UTF-8'} &middot; service {$feedback.service|default:'—'|escape:'html':'UTF-8'} &middot; F&amp;B {$feedback.fnb|default:'—'|escape:'html':'UTF-8'} &middot; cleanliness {$feedback.cleanliness|default:'—'|escape:'html':'UTF-8'} &middot; NPS {if $feedback.nps !== null}{$feedback.nps|escape:'html':'UTF-8'}{else}—{/if}</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Room</th><th>Guest</th><th>Overall</th><th>Comment</th></tr></thead><tbody>
{foreach $recent_feedback as $f}<tr class="{if $f.rating_overall <= 3 && $f.rating_overall > 0}danger{/if}"><td>{$f.date_add|date_format:"%d/%m %H:%M"}</td><td>{$f.room_num|escape:'html':'UTF-8'}</td><td>{$f.guest_name|escape:'html':'UTF-8'}</td><td>{$f.rating_overall|escape:'html':'UTF-8'}/5</td><td>{$f.comment|truncate:120|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="5"><em>No feedback yet</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="gp-vod"><div class="row"><div class="col-md-6"><h4>Movies played {$from|escape:'html':'UTF-8'} → {$to|escape:'html':'UTF-8'}</h4><table class="table table-condensed"><tbody>
{foreach $plays as $p}<tr><td>{$p.date_add|date_format:"%d/%m %H:%M"}</td><td>{$p.room_num|escape:'html':'UTF-8'}</td><td>{$p.title|escape:'html':'UTF-8'}</td><td>{if $p.price > 0}{displayPrice price=$p.price}{else}free{/if}</td><td>{$p.status|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td><em>Nothing played</em></td></tr>{/foreach}</tbody></table></div>
<div class="col-md-6"><h4>Room control activity</h4><table class="table table-condensed"><tbody>
{foreach $control_log as $l}<tr class="{if $l.result=='failed'}danger{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M"}</td><td>{$l.room_num|escape:'html':'UTF-8'}</td><td>{$l.code|escape:'html':'UTF-8'}</td><td>{$l.action|escape:'html':'UTF-8'} {$l.value|escape:'html':'UTF-8'}</td><td>{$l.result|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td><em>No room-control activity</em></td></tr>{/foreach}</tbody></table></div></div></div>

</div></div></div>
