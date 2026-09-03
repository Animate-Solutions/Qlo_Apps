<div class="pulse-fd">
<div class="panel"><h3><i class="icon-dashboard"></i> Front Desk &mdash; business date <strong>{$business_date}</strong></h3>
  <div class="row pulse-kpis">
    <div class="col-md-2 kpi"><span>{$kpi.occupancy_pct}%</span>Occupancy</div>
    <div class="col-md-2 kpi"><span>{$kpi.occupied}</span>In house</div>
    <div class="col-md-2 kpi"><span>{$kpi.due_in}</span>Arrivals due</div>
    <div class="col-md-2 kpi"><span>{$kpi.due_out}</span>Departures due</div>
    <div class="col-md-2 kpi"><span>{$kpi.vacant_clean}</span>Vacant clean</div>
    <div class="col-md-2 kpi kpi-warn"><span>{$kpi.vacant_dirty}</span>Vacant dirty &middot; {$kpi.ooo} OOO</div>
  </div>
  <p>
    <a class="btn btn-default" href="{$links.AdminPulseRoomBoard}"><i class="icon-th"></i> Room board</a>
    <a class="btn btn-default" href="{$links.AdminPulseArrivals}"><i class="icon-exchange"></i> Arrivals &amp; departures</a>
    <a class="btn btn-default" href="{$links.AdminPulseFolio}"><i class="icon-file-text"></i> Folios</a>
    <a class="btn btn-default" href="{$links.AdminPulseHousekeeping}"><i class="icon-bed"></i> Housekeeping ({$hk_open} open)</a>
    <a class="btn btn-default" href="{$links.AdminPulseNightAudit}"><i class="icon-moon"></i> Night audit</a>
    <a class="btn btn-default" href="{$links.AdminPulseFdReports}"><i class="icon-bar-chart"></i> Reports</a>
  </p>
</div>
<div class="row">
  <div class="col-md-6"><div class="panel"><h3>Arrivals today ({$arrivals|count})</h3>
    <table class="table"><thead><tr><th>Guest</th><th>Room</th><th>Type</th><th>Nights</th><th>Paid</th><th></th></tr></thead><tbody>
    {foreach $arrivals as $a}<tr><td>{if $a.vip_level}<span class="badge badge-warning">VIP{$a.vip_level}</span> {/if}{$a.guest}{if $a.company_name}<br><small>{$a.company_name}</small>{/if}</td><td>{$a.room_num}</td><td>{$a.room_type_name}</td><td>{$a.nights}</td><td>{displayPrice price=$a.total_paid_real}</td><td><a class="btn btn-xs btn-success" href="{$links.AdminPulseArrivals}">Check in</a></td></tr>
    {foreachelse}<tr><td colspan="6"><em>No arrivals</em></td></tr>{/foreach}</tbody></table></div></div>
  <div class="col-md-6"><div class="panel"><h3>Departures today ({$departures|count})</h3>
    <table class="table"><thead><tr><th>Guest</th><th>Room</th><th>Folio</th><th>Balance</th><th></th></tr></thead><tbody>
    {foreach $departures as $d}<tr><td>{$d.guest}</td><td>{$d.room_num}</td><td>{$d.folio_no}</td><td class="{if $d.balance > 0}text-danger{/if}">{displayPrice price=$d.balance}</td><td><a class="btn btn-xs btn-primary" href="{$links.AdminPulseArrivals}">Check out</a></td></tr>
    {foreachelse}<tr><td colspan="5"><em>No departures</em></td></tr>{/foreach}</tbody></table></div></div>
</div>
<div class="row">
  <div class="col-md-4"><div class="panel"><h3>Traces &amp; wake-ups due (24h)</h3><ul class="list-unstyled">{foreach $traces as $t}<li><span class="label label-default">{$t.type}</span> <strong>{$t.due_at|date_format:"%H:%M"}</strong> {if $t.room_num}Rm {$t.room_num} &middot; {/if}{$t.text}</li>{foreachelse}<li><em>Nothing due</em></li>{/foreach}</ul></div></div>
  <div class="col-md-4"><div class="panel"><h3>7-day forecast</h3><table class="table table-condensed"><thead><tr><th>Date</th><th>Occ</th><th>Arr</th><th>Dep</th><th>%</th></tr></thead><tbody>{foreach $forecast as $f}<tr><td>{$f.date|date_format:"%a %d %b"}</td><td>{$f.occupied}</td><td>{$f.arrivals}</td><td>{$f.departures}</td><td>{$f.occupancy_pct}%</td></tr>{/foreach}</tbody></table></div></div>
  <div class="col-md-4"><div class="panel"><h3>Guest ledger &mdash; open balances</h3><table class="table table-condensed"><tbody>{foreach $ledger as $l}<tr><td>{$l.folio_no}</td><td>{if $l.room_num}Rm {$l.room_num}{else}{$l.company}{/if}</td><td class="text-right">{displayPrice price=$l.balance}</td></tr>{foreachelse}<tr><td><em>All settled</em></td></tr>{/foreach}</tbody></table></div></div>
</div>
</div>
