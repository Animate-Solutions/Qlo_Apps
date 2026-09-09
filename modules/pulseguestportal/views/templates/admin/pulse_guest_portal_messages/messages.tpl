<div class="pulse-gp"><div class="row">
<div class="col-md-5"><div class="panel"><h3><i class="icon-inbox"></i> Inbox {if $unread}<span class="badge" style="background:#c1272d">{$unread|escape:'html':'UTF-8'}</span>{/if}</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseGuestPortalMessages"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="only" class="form-control"><option value="">All threads</option><option value="unread" {if $only=='unread'}selected{/if}>Unread only</option></select> <button class="btn btn-default btn-sm">Show</button></form>
<table class="table table-condensed"><tbody>
{foreach $inbox as $i}<tr class="{if $i.unread}warning{/if}{if $i.id_htl_booking==$id_htl_booking} info{/if}"><td><a href="{$self_url}&amp;id_htl_booking={$i.id_htl_booking|escape:'html':'UTF-8'}"><strong>{$i.room_num|escape:'html':'UTF-8'}</strong> {$i.guest|escape:'html':'UTF-8'}</a><br><small class="text-muted">{$i.last_body|truncate:60|escape:'html':'UTF-8'}</small></td><td class="text-right">{if $i.unread}<span class="badge">{$i.unread|escape:'html':'UTF-8'}</span><br>{/if}<small>{$i.last_at|date_format:"%d/%m %H:%M"}</small></td></tr>
{foreachelse}<tr><td><em>No conversations yet</em></td></tr>{/foreach}</tbody></table></div>

<div class="panel"><h3>Broadcast</h3><form method="post">
<textarea name="bbody" class="form-control" rows="2" placeholder="Sent to every occupied room with a paired screen" required></textarea>
<button name="broadcast" class="btn btn-default" style="margin-top:6px" data-gp-confirm="Send this to every occupied room?">Send to all rooms</button></form></div></div>

<div class="col-md-7"><div class="panel">
{if $id_htl_booking}<h3>{if $stay}Room {$stay.room_num|escape:'html':'UTF-8'} — {$stay.guest|escape:'html':'UTF-8'}{else}Conversation{/if} <small class="text-muted">{if $stay}{$stay.date_from|escape:'html':'UTF-8'} → {$stay.date_to|escape:'html':'UTF-8'}{/if}</small></h3>
<div class="gp-chat">{foreach $thread as $m}<div class="gp-b {if $m.direction=='guest'}guest{else}desk{/if}">{$m.body|escape|nl2br nofilter}<br><small>{if $m.staff}{$m.staff|escape:'html':'UTF-8'} · {/if}{$m.date_add|date_format:"%d/%m %H:%M"}</small></div><div class="gp-clear"></div>{foreachelse}<em>Nothing said yet</em>{/foreach}</div>
<form method="post" style="margin-top:10px"><input type="hidden" name="id_htl_booking" value="{$id_htl_booking|escape:'html':'UTF-8'}">
<textarea name="body" class="form-control" rows="3" placeholder="Reply to the guest — it appears on the TV within one heartbeat" required></textarea>
<button name="reply" class="btn btn-primary" style="margin-top:6px">Send reply</button>
<button name="markRead" class="btn btn-default" style="margin-top:6px">Mark read</button></form>
{else}<h3>Pick a conversation</h3><p class="help-block">Choose a room on the left, or start one with an in-house guest:</p>
<table class="table table-condensed"><tbody>{foreach $in_house as $b}<tr><td><a href="{$self_url}&amp;id_htl_booking={$b.id|escape:'html':'UTF-8'}">{$b.room_num|escape:'html':'UTF-8'} — {$b.guest|escape:'html':'UTF-8'}</a></td></tr>{foreachelse}<tr><td><em>Nobody is checked in</em></td></tr>{/foreach}</tbody></table>{/if}
</div></div></div></div>
