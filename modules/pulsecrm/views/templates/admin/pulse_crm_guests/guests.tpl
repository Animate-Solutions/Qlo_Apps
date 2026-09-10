<div class="pulse-crm"><div class="panel"><h3><i class="icon-user"></i> Guests</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmGuests"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input name="q" value="{$q|escape:'html'}" class="form-control" placeholder="Name, email, phone or member number" size="40">
  <button class="btn btn-primary">Search</button>
  <a class="btn btn-default" href="{$self_url}&dupes=1">Find duplicate profiles</a>
</form>

{if $duplicates}
<h4>Possible duplicates</h4>
<p class="text-muted">Merging repoints bookings, folios, tickets and comms onto the kept profile and sums the stay history. It cannot be undone.</p>
<table class="table table-condensed"><thead><tr><th>Matched on</th><th>Keep</th><th>Merge</th><th></th></tr></thead><tbody>
{foreach $duplicates as $d}<tr><td>{$d.reason|escape:'html':'UTF-8'}</td><td>{$d.name_a|escape:'html':'UTF-8'}<br><small>{$d.email_a|escape:'html':'UTF-8'}</small></td><td>{$d.name_b|escape:'html':'UTF-8'}<br><small>{$d.email_b|escape:'html':'UTF-8'}</small></td>
<td><form method="post" class="inline"><input type="hidden" name="id_keep" value="{$d.a|escape:'html':'UTF-8'}"><input type="hidden" name="id_merge" value="{$d.b|escape:'html':'UTF-8'}"><button name="mergeGuest" class="btn btn-xs btn-warning" onclick="return confirm('Merge {$d.name_b|escape:'javascript'} into {$d.name_a|escape:'javascript'}?')">Merge B into A</button></form></td></tr>{/foreach}
</tbody></table>
{/if}

<table class="table table-condensed"><thead><tr><th>Guest</th><th>Email</th><th>Stays</th><th>Nights</th><th>Lifetime</th><th>Last stay</th><th>Tier</th><th>NPS</th><th>Source</th><th></th></tr></thead><tbody>
{foreach $rows as $r}<tr class="{if $r.blacklisted}danger{elseif $r.forgotten}warning{/if}">
  <td><a href="{$self_url}&id_customer={$r.id_customer|escape:'html':'UTF-8'}">{$r.firstname|escape:'html':'UTF-8'} {$r.lastname|escape:'html':'UTF-8'}</a>{if $r.vip_level > 0} <span class="badge crm-vip">VIP {$r.vip_level|escape:'html':'UTF-8'}</span>{/if}{if $r.forgotten} <span class="badge">erased</span>{/if}</td>
  <td>{$r.email|escape:'html':'UTF-8'}</td><td>{$r.stays|escape:'html':'UTF-8'}</td><td>{$r.nights|escape:'html':'UTF-8'}</td><td>{displayPrice price=$r.lifetime_revenue}</td><td>{$r.last_stay|escape:'html':'UTF-8'}</td>
  <td>{if $r.tier}{$r.tier|escape:'html':'UTF-8'}<br><small class="text-muted">{$r.member_no|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{if $r.nps_band == 'detractor'}<span class="badge crm-det">detractor</span>{elseif $r.nps_band == 'promoter'}<span class="badge crm-pro">promoter</span>{else}{$r.nps_band|escape:'html':'UTF-8'}{/if}</td>
  <td>{$r.source_of_business|escape:'html':'UTF-8'}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_customer={$r.id_customer|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>No guests match that search.</em></td></tr>{/foreach}
</tbody></table>

<h4>Segments</h4><table class="table table-condensed"><tbody>
{foreach $segments as $s}<tr><td><b>{$s.name|escape:'html':'UTF-8'}</b></td><td>{$s.description|escape:'html':'UTF-8'}</td><td>{$s.member_count|escape:'html':'UTF-8'} guests</td><td><small class="text-muted">refreshed {$s.last_refresh|escape:'html':'UTF-8'}</small></td></tr>{/foreach}
</tbody></table>
</div></div>
