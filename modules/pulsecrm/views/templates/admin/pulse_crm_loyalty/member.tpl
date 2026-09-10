<div class="pulse-crm"><div class="panel">
<h3><i class="icon-star"></i> {$m.guest|escape:'html':'UTF-8'} — {$m.member_no|escape:'html':'UTF-8'} <small class="pull-right"><a href="{$self_url}">&larr; all members</a></small></h3>
<p>{$m.program_name|escape:'html':'UTF-8'} · tier <b>{$m.tier_name|escape:'html':'UTF-8'}</b> since {$m.tier_since|escape:'html':'UTF-8'} · card {$m.card_no|escape:'html':'UTF-8'} · joined {$m.join_date|escape:'html':'UTF-8'} via {$m.enrol_source|escape:'html':'UTF-8'} · status {$m.status|escape:'html':'UTF-8'}</p>
<div class="row crm-kpis">
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$m.points_balance|number_format:0|escape:'html':'UTF-8'}</span><span class="l">Points</span><small>worth {displayPrice price=$m.points_balance*$m.point_value}</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$m.qualifying_nights|escape:'html':'UTF-8'}</span><span class="l">Qualifying nights</span><small>{$m.qualifying_stays|escape:'html':'UTF-8'} stays</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{displayPrice price=$m.qualifying_spend}</span><span class="l">Qualifying spend</span><small>review {$m.tier_review_date|escape:'html':'UTF-8'}</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$m.points_earned_life|number_format:0|escape:'html':'UTF-8'}</span><span class="l">Earned lifetime</span><small>{$m.points_redeemed_life|number_format:0|escape:'html':'UTF-8'} redeemed, {$m.points_expired_life|number_format:0|escape:'html':'UTF-8'} expired</small></div></div>
</div>

<div class="row"><div class="col-md-4">
<h4>Redeem</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_member_a" value="{$m.id_pulse_crm_member|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-4">Points</label><div class="col-sm-8"><input type="number" name="points" class="form-control" min="{$m.min_redeem_points|escape:'html':'UTF-8'}" step="100" value="{$m.min_redeem_points|escape:'html':'UTF-8'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Onto stay</label><div class="col-sm-8"><select name="id_htl_booking" class="form-control"><option value="">—</option>
    {foreach $stays as $s}<option value="{$s.id|escape:'html':'UTF-8'}">{$s.date_from|escape:'html':'UTF-8'} → {$s.date_to|escape:'html':'UTF-8'} room {$s.room_num|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Note</label><div class="col-sm-8"><input name="reason" class="form-control"></div></div>
  <button name="redeemPoints" class="btn btn-primary" {if !$fd}disabled title="Front Desk is not installed"{/if}>Redeem to the folio</button>
</form>
<p class="help-block">Redemption posts a negative <code>LOYR</code> line to the open folio, so the guest's bill actually falls and the give-away lands in the ledger with everything else. Minimum {$m.min_redeem_points|escape:'html':'UTF-8'} points.</p>

<h4>Adjust</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_member_a" value="{$m.id_pulse_crm_member|escape:'html':'UTF-8'}">
  <input type="number" name="points" class="form-control" placeholder="± points" style="width:110px"> <input name="reason" class="form-control" placeholder="Reason (required)" size="30">
  <button name="adjustPoints" class="btn btn-default">Adjust</button></form>
<form method="post" class="form-inline" style="margin-top:8px"><input type="hidden" name="id_member_a" value="{$m.id_pulse_crm_member|escape:'html':'UTF-8'}">
  <button name="recalcTier" class="btn btn-default btn-sm">Recalculate tier</button>
  <select name="status" class="form-control input-sm"><option value="active">active</option><option value="suspended">suspended</option><option value="closed">closed</option></select>
  <button name="setMemberStatus" class="btn btn-default btn-sm">Set status</button></form>
</div>

<div class="col-md-8">
<h4>Points ledger</h4>
<table class="table table-condensed"><thead><tr><th>When</th><th>Type</th><th>Points</th><th>Left in lot</th><th>Balance</th><th>Source</th><th>Description</th><th>Expires</th></tr></thead><tbody>
{foreach $txns as $t}<tr><td>{$t.date_add|date_format:"%d/%m/%Y %H:%M"}</td><td>{$t.type|escape:'html':'UTF-8'}</td>
  <td class="{if $t.points < 0}text-danger{else}text-success{/if}">{$t.points|escape:'html':'UTF-8'}</td><td>{if $t.points_remaining > 0}{$t.points_remaining|escape:'html':'UTF-8'}{/if}</td>
  <td>{$t.balance_after|escape:'html':'UTF-8'}</td><td>{$t.source|escape:'html':'UTF-8'}</td><td><small>{$t.description|escape:'html':'UTF-8'}</small></td><td>{$t.expires_on|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No transactions yet.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">Points are consumed oldest-lot-first, so &ldquo;left in lot&rdquo; is what expiry will actually take if the guest does not spend it.</p>
</div></div>
</div></div>
