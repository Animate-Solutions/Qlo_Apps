<div class="pulse-crm"><div class="panel"><h3><i class="icon-star"></i> Loyalty</h3>
{if !$fd}<div class="alert alert-info">Front Desk is not installed. Points can still be earned by hand and adjusted, but nothing posts to a folio and redemption has no bill to reduce.</div>{/if}
<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#l-members">Members ({$members|count})</a></li>
  <li><a data-toggle="tab" href="#l-program">Programme &amp; tiers</a></li>
  <li><a data-toggle="tab" href="#l-ledger">Points ledger</a></li>
  <li><a data-toggle="tab" href="#l-liability">Liability &amp; expiry</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="l-members">
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmLoyalty"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input name="q" value="{$q|escape:'html'}" class="form-control" placeholder="Name, email, member or card number" size="35"> <button class="btn btn-primary">Search</button></form>
<form method="post" class="form-inline" style="margin:8px 0"><input name="guest_email" class="form-control" placeholder="Enrol a guest by email" size="35">
  <button name="enrolGuest" class="btn btn-default">Enrol at the desk</button></form>
<table class="table table-condensed"><thead><tr><th>Member</th><th>Guest</th><th>Tier</th><th>Points</th><th>Qualifying</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $members as $m}<tr class="{if $m.status != 'active'}text-muted{/if}">
  <td>{$m.member_no|escape:'html':'UTF-8'}<br><small class="text-muted">{$m.card_no|escape:'html':'UTF-8'}</small></td><td>{$m.guest|escape:'html':'UTF-8'}<br><small>{$m.email|escape:'html':'UTF-8'}</small></td>
  <td><span class="badge crm-tag-{$m.tier_colour|escape:'html':'UTF-8'}">{$m.tier_name|escape:'html':'UTF-8'}</span></td><td>{$m.points_balance|number_format:0|escape:'html':'UTF-8'}</td>
  <td>{$m.qualifying_nights|escape:'html':'UTF-8'} nights · {$m.qualifying_stays|escape:'html':'UTF-8'} stays · {displayPrice price=$m.qualifying_spend}</td>
  <td>{$m.join_date|escape:'html':'UTF-8'}</td><td>{$m.status|escape:'html':'UTF-8'}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_member={$m.id_pulse_crm_member|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="8"><em>Nobody is enrolled yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="l-program">
{if $p}
<form method="post" class="form-horizontal"><input type="hidden" name="id_program" value="{$p.id_pulse_crm_loyalty_program|escape:'html':'UTF-8'}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="name" class="form-control" value="{$p.name|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Code</label><div class="col-sm-8"><input name="code" class="form-control" value="{$p.code|escape:'html'}" readonly></div></div>
  <div class="form-group"><label class="col-sm-4">Naira per point</label><div class="col-sm-8"><input name="point_value" class="form-control" value="{$p.point_value|floatval}"><span class="help-block">What one point is worth when it comes off a bill.</span></div></div>
  <div class="form-group"><label class="col-sm-4">Minimum redemption</label><div class="col-sm-8"><input type="number" name="min_redeem_points" class="form-control" value="{$p.min_redeem_points|escape:'html':'UTF-8'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Points expire after</label><div class="col-sm-8"><input type="number" name="expiry_months" class="form-control" value="{$p.expiry_months|escape:'html':'UTF-8'}"> months</div></div>
  <div class="form-group"><label class="col-sm-4">Tier window</label><div class="col-sm-8"><input type="number" name="qualify_window_months" class="form-control" value="{$p.qualify_window_months|escape:'html':'UTF-8'}"> months</div></div>
  <div class="form-group"><label class="col-sm-4">Enrolment bonus</label><div class="col-sm-8"><input type="number" name="enrol_bonus" class="form-control" value="{$p.enrol_bonus|escape:'html':'UTF-8'}"> points</div></div>
  <div class="form-group"><label class="col-sm-4">Terms</label><div class="col-sm-8"><textarea name="terms" class="form-control" rows="4">{$p.terms|escape:'html'}</textarea></div></div>
</div><div class="col-md-6">
  <h4>Earning — points per ₦1,000 of net spend</h4>
  <table class="table table-condensed"><tbody>
  {foreach $departments as $d}<tr><td>{$d|replace:'_':' '|escape:'html':'UTF-8'}</td><td><input name="rate[{$d|escape:'html':'UTF-8'}]" class="form-control input-sm" value="{$rates[$d]|default:0|escape:'html':'UTF-8'}" style="width:100px"></td></tr>{/foreach}
  </tbody></table>
  <p class="help-block">Net spend means before VAT and consumption tax — the hotel should not be paying loyalty on money it collects for the FIRS. The tier multiplier is applied on top.</p>
</div></div>
<button name="saveProgram" class="btn btn-primary">Save programme</button>
</form>

<h4>Tiers</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Tier</th><th>Nights</th><th>Stays</th><th>Spend</th><th>Earn ×</th><th>Benefits</th></tr></thead><tbody>
{foreach $tiers as $t}<tr><td>{$t.sort|escape:'html':'UTF-8'}</td><td><span class="badge crm-tag-{$t.colour|escape:'html':'UTF-8'}">{$t.name|escape:'html':'UTF-8'}</span> <small class="text-muted">{$t.code|escape:'html':'UTF-8'}</small></td><td>{$t.min_nights|escape:'html':'UTF-8'}</td><td>{$t.min_stays|escape:'html':'UTF-8'}</td><td>{displayPrice price=$t.min_spend}</td><td>{$t.earn_multiplier|escape:'html':'UTF-8'}</td><td><small>{$t.benefits|escape:'html':'UTF-8'}</small></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_program" value="{$p.id_pulse_crm_loyalty_program|escape:'html':'UTF-8'}"><input type="hidden" name="id_tier" value="{(int)$smarty.get.id_tier}">
  <input name="tname" class="form-control" placeholder="Tier name" required> <input name="tcode" class="form-control" placeholder="CODE" style="width:90px">
  <input type="number" name="tsort" class="form-control" placeholder="#" style="width:60px"> <input type="number" name="min_nights" class="form-control" placeholder="nights" style="width:80px">
  <input type="number" name="min_stays" class="form-control" placeholder="stays" style="width:80px"> <input name="min_spend" class="form-control" placeholder="spend" style="width:110px">
  <input name="earn_multiplier" class="form-control" placeholder="1.0" style="width:70px">
  <select name="colour" class="form-control"><option value="default">grey</option><option value="info">blue</option><option value="success">green</option><option value="warning">gold</option><option value="danger">red</option></select>
  <input name="benefits" class="form-control" placeholder="Benefits" size="40">
  <button name="saveTier" class="btn btn-primary">Save tier</button></form>
<form method="post" class="form-inline" style="margin-top:8px"><button name="recalcAll" class="btn btn-default btn-sm">Re-tier everyone due</button>
  <button name="expireNow" class="btn btn-default btn-sm">Run point expiry now</button></form>
{else}<div class="alert alert-warning">No programme exists. Reinstall the module, or add one from the database — the installer creates Pulse Rewards with three tiers.</div>{/if}
</div>

<div class="tab-pane" id="l-ledger">
<table class="table table-condensed"><thead><tr><th>When</th><th>Member</th><th>Guest</th><th>Type</th><th>Points</th><th>Balance</th><th>Department</th><th>Basis</th><th>Description</th><th>Expires</th></tr></thead><tbody>
{foreach $recent as $t}<tr><td>{$t.date_add|date_format:"%d/%m %H:%M"}</td><td>{$t.member_no|escape:'html':'UTF-8'}</td><td>{$t.guest|escape:'html':'UTF-8'}</td><td>{$t.type|escape:'html':'UTF-8'}</td>
  <td class="{if $t.points < 0}text-danger{else}text-success{/if}">{$t.points|escape:'html':'UTF-8'}</td><td>{$t.balance_after|escape:'html':'UTF-8'}</td><td>{$t.department|escape:'html':'UTF-8'}</td><td>{if $t.amount_basis > 0}{displayPrice price=$t.amount_basis}{/if}</td>
  <td><small>{$t.description|escape:'html':'UTF-8'}</small></td><td>{$t.expires_on|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="10"><em>No points have moved yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="l-liability">
<table class="table table-condensed"><thead><tr><th>Tier</th><th>Members</th><th>Points outstanding</th><th>Liability</th></tr></thead><tbody>
{foreach $liability as $l}<tr><td>{$l.tier|escape:'html':'UTF-8'}</td><td>{$l.members|escape:'html':'UTF-8'}</td><td>{$l.points|number_format:0|escape:'html':'UTF-8'}</td><td>{displayPrice price=$l.value}</td></tr>{/foreach}
</tbody></table>
<h4>Points expiring in the next 90 days</h4>
<table class="table table-condensed"><thead><tr><th>Member</th><th>Guest</th><th>Points</th><th>First expiry</th></tr></thead><tbody>
{foreach $expiring as $e}<tr><td>{$e.member_no|escape:'html':'UTF-8'}</td><td>{$e.guest|escape:'html':'UTF-8'}</td><td>{$e.points|number_format:0|escape:'html':'UTF-8'}</td><td>{$e.first_expiry|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="4"><em>Nothing expires in the next quarter.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">The cron warns each of these guests once a month; a warned guest who books is worth more than the point liability you just cleared.</p>
</div>

</div></div></div>
