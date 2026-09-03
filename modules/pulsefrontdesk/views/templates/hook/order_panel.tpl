<div class="panel"><div class="panel-heading"><i class="icon-bed"></i> Pulse Front Desk</div>
<table class="table"><thead><tr><th>Room</th><th>Type</th><th>Stay</th><th>Status</th><th>Folio</th><th class="text-right">Balance</th></tr></thead><tbody>
{foreach $rooms as $r}<tr><td><strong>{$r.room_num}</strong></td><td>{$r.room_type_name}</td><td>{$r.date_from} → {$r.date_to}</td><td>{if $r.id_status==1}<span class="label label-info">Reserved</span>{elseif $r.id_status==2}<span class="label label-success">In house</span>{else}<span class="label label-default">Departed</span>{/if}</td>
<td>{if $r.folio_no}<a href="{$link_folio}&id_pulse_folio={$r.id_pulse_folio}&viewpulse_folio">{$r.folio_no}</a>{else}—{/if}</td><td class="text-right">{if $r.balance}{displayPrice price=$r.balance}{/if}</td></tr>{/foreach}</tbody></table>
<a class="btn btn-default btn-sm" href="{$link_arrivals}"><i class="icon-exchange"></i> Open Arrivals &amp; Departures</a></div>
