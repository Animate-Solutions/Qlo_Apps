<div class="pulse-crm"><div class="panel">
<h3><i class="icon-briefcase"></i> {$a.name|escape:'html':'UTF-8'} <small class="pull-right"><a href="{$self_url}">&larr; all accounts</a></small></h3>
<p class="text-muted">{$a.segment|replace:'_':' '|escape:'html':'UTF-8'}{if $a.industry} · {$a.industry|escape:'html':'UTF-8'}{/if} · {$a.status|escape:'html':'UTF-8'}
{if $a.company_name} · ledger {displayPrice price=$a.ledger_balance} of {displayPrice price=$a.credit_limit} limit{if $a.discount_pct} · {$a.discount_pct|escape:'html':'UTF-8'}% contracted discount{/if}{/if}
{if $a.tin} · TIN {$a.tin|escape:'html':'UTF-8'}{/if}{if $a.next_review} · next review {$a.next_review|escape:'html':'UTF-8'}{/if}</p>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#a-activity">Activity</a></li>
  <li><a data-toggle="tab" href="#a-contacts">Contacts ({$a.contacts|count})</a></li>
  <li><a data-toggle="tab" href="#a-opps">Opportunities ({$a.opportunities|count})</a></li>
  <li><a data-toggle="tab" href="#a-rates">Contracted rates</a></li>
  <li><a data-toggle="tab" href="#a-production">Production</a></li>
  <li><a data-toggle="tab" href="#a-edit">Account</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="a-activity">
<form method="post" class="form-horizontal"><input type="hidden" name="id_account_a" value="{$a.id_pulse_crm_account|escape:'html':'UTF-8'}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-3">Type</label><div class="col-sm-9"><select name="atype" class="form-control"><option value="call">call</option><option value="visit">visit</option><option value="email">email</option><option value="meeting">meeting</option><option value="proposal">proposal</option><option value="entertainment">entertainment</option><option value="note">note</option></select></div></div>
  <div class="form-group"><label class="col-sm-3">Contact</label><div class="col-sm-9"><select name="id_contact" class="form-control"><option value="">—</option>{foreach $a.contacts as $c}<option value="{$c.id_pulse_crm_contact|escape:'html':'UTF-8'}">{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">Subject</label><div class="col-sm-9"><input name="asubject" class="form-control" required></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-3">When</label><div class="col-sm-9"><input type="datetime-local" name="activity_date" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-3">Follow up</label><div class="col-sm-9"><input type="datetime-local" name="follow_up_at" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-3">Outcome</label><div class="col-sm-9"><input name="aoutcome" class="form-control" placeholder="What they said"></div></div>
</div></div>
  <div class="form-group"><label class="col-sm-2">Notes</label><div class="col-sm-10"><textarea name="anotes" class="form-control" rows="3"></textarea></div></div>
  <button name="logActivity" class="btn btn-primary">Log it</button>
</form>
<table class="table table-condensed"><thead><tr><th>When</th><th>Type</th><th>Contact</th><th>Subject</th><th>Outcome</th><th>Who</th><th>Follow-up</th></tr></thead><tbody>
{foreach $a.activities as $ac}<tr class="{if !$ac.follow_up_done && $ac.follow_up_at}warning{/if}">
  <td>{$ac.activity_date|date_format:"%d/%m/%Y %H:%M"}</td><td>{$ac.type|escape:'html':'UTF-8'}</td><td>{$ac.contact_name|escape:'html':'UTF-8'}</td><td>{$ac.subject|escape:'html':'UTF-8'}<br><small class="text-muted">{$ac.notes|truncate:100|escape:'html':'UTF-8'}</small></td>
  <td>{$ac.outcome|escape:'html':'UTF-8'}</td><td>{$ac.who|escape:'html':'UTF-8'}</td>
  <td>{if $ac.follow_up_at}{$ac.follow_up_at|date_format:"%d/%m"} {if $ac.follow_up_done}<span class="badge">done</span>{else}<form method="post" class="inline"><input type="hidden" name="id_activity" value="{$ac.id_pulse_crm_activity|escape:'html':'UTF-8'}"><button name="doneFollowUp" class="btn btn-xs btn-default">Done</button></form>{/if}{/if}</td></tr>
{foreachelse}<tr><td colspan="7"><em>Nothing logged yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-contacts">
<table class="table table-condensed"><thead><tr><th>Name</th><th>Title</th><th>Role</th><th>Email</th><th>Phone</th><th>Primary</th></tr></thead><tbody>
{foreach $a.contacts as $c}<tr><td>{$c.name|escape:'html':'UTF-8'}</td><td>{$c.title|escape:'html':'UTF-8'}</td><td>{$c.decision_role|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$c.email|escape:'html':'UTF-8'}</td><td>{$c.phone|escape:'html':'UTF-8'}</td><td>{if $c.is_primary}★{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No contacts. An account with no named person is a logo, not a customer.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_account_a" value="{$a.id_pulse_crm_account|escape:'html':'UTF-8'}"><input type="hidden" name="id_contact" value="{(int)$smarty.get.id_contact}">
  <input name="cname" class="form-control" placeholder="Name" required> <input name="ctitle" class="form-control" placeholder="Title">
  <input name="cemail" class="form-control" placeholder="Email"> <input name="cphone" class="form-control" placeholder="Phone">
  <select name="decision_role" class="form-control"><option value="booker">booker</option><option value="decision_maker">decision maker</option><option value="influencer">influencer</option><option value="finance">finance</option><option value="other">other</option></select>
  <label class="checkbox-inline"><input type="checkbox" name="is_primary" value="1"> primary</label>
  <button name="saveContact" class="btn btn-primary">Save contact</button></form>
</div>

<div class="tab-pane" id="a-opps">
<table class="table table-condensed"><thead><tr><th>Opportunity</th><th>Stage</th><th>Nights</th><th>Value</th><th>Probability</th><th>Close</th><th>Owner</th><th>Lost because</th></tr></thead><tbody>
{foreach $a.opportunities as $o}<tr class="{if $o.stage == 'won'}success{elseif $o.stage == 'lost'}text-muted{/if}">
  <td>{$o.name|escape:'html':'UTF-8'}<br><small class="text-muted">{$o.notes|truncate:80|escape:'html':'UTF-8'}</small></td><td>{$o.stage|escape:'html':'UTF-8'}</td><td>{$o.expected_nights|escape:'html':'UTF-8'}</td><td>{displayPrice price=$o.expected_value}</td>
  <td>{$o.probability|escape:'html':'UTF-8'}%</td><td>{$o.close_date|escape:'html':'UTF-8'}</td><td>{$o.owner_name|escape:'html':'UTF-8'}</td><td>{$o.lost_reason|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No opportunities.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_account_a" value="{$a.id_pulse_crm_account|escape:'html':'UTF-8'}"><input type="hidden" name="id_opportunity" value="{(int)$smarty.get.id_opportunity}">
  <input name="oname" class="form-control" placeholder="Opportunity" size="30" required>
  <select name="stage" class="form-control"><option value="lead">lead</option><option value="qualified">qualified</option><option value="proposal">proposal</option><option value="negotiation">negotiation</option><option value="won">won</option><option value="lost">lost</option></select>
  <input type="number" name="expected_nights" class="form-control" placeholder="nights" style="width:90px">
  <input name="expected_value" class="form-control" placeholder="value" style="width:120px">
  <input type="number" name="probability" class="form-control" placeholder="%" style="width:70px" value="20">
  <input type="date" name="close_date" class="form-control">
  <input name="lost_reason" class="form-control" placeholder="If lost, why">
  <button name="saveOpportunity" class="btn btn-primary">Save</button></form>
</div>

<div class="tab-pane" id="a-rates">
<table class="table table-condensed"><thead><tr><th>Room type</th><th>Rate (excl. tax)</th><th>Breakfast</th><th>From</th><th>To</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $a.rates as $rt}<tr class="{if $rt.valid_to < $smarty.now|date_format:'%Y-%m-%d'}text-muted{/if}">
  <td>{$rt.product_name|default:$rt.room_type_name|default:'Any room type'|escape:'html':'UTF-8'}</td><td>{displayPrice price=$rt.rate_tax_excl}</td><td>{if $rt.includes_breakfast}included{/if}</td>
  <td>{$rt.valid_from|escape:'html':'UTF-8'}</td><td>{$rt.valid_to|escape:'html':'UTF-8'}</td><td>{$rt.note|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_rate" value="{$rt.id_pulse_crm_account_rate|escape:'html':'UTF-8'}"><button name="delRate" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em>No contracted rates. Reception is quoting rack to this account.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_account_a" value="{$a.id_pulse_crm_account|escape:'html':'UTF-8'}"><input type="hidden" name="id_rate" value="{(int)$smarty.get.id_rate}">
  <select name="id_product" class="form-control"><option value="0">Any room type</option>{foreach $room_types as $rt}<option value="{$rt.id_product|escape:'html':'UTF-8'}">{$rt.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input name="rate_tax_excl" class="form-control" placeholder="Rate excl. tax" style="width:130px" required>
  <label class="checkbox-inline"><input type="checkbox" name="includes_breakfast" value="1"> breakfast</label>
  <input type="date" name="valid_from" class="form-control" required> <input type="date" name="valid_to" class="form-control" required>
  <input name="rnote" class="form-control" placeholder="Note">
  <button name="saveRate" class="btn btn-primary">Save rate</button></form>
</div>

<div class="tab-pane" id="a-production">
<table class="table table-condensed"><thead><tr><th>Month</th><th>Stays</th><th>Room nights</th><th>Revenue</th><th>ADR</th></tr></thead><tbody>
{foreach $a.production as $p}<tr><td>{$p.ym|escape:'html':'UTF-8'}</td><td>{$p.stays|escape:'html':'UTF-8'}</td><td>{$p.nights|escape:'html':'UTF-8'}</td><td>{displayPrice price=$p.revenue}</td><td>{displayPrice price=$p.adr}</td></tr>
{foreachelse}<tr><td colspan="5"><em>No production yet — attach travellers to the company on their Front Desk profile and this fills in.</em></td></tr>{/foreach}
</tbody></table>
<h4>Travellers</h4>
<table class="table table-condensed"><thead><tr><th>Guest</th><th>Email</th><th>Stays</th><th>Nights</th><th>Lifetime</th><th>Last stay</th></tr></thead><tbody>
{foreach $a.travellers as $t}<tr><td>{$t.guest|escape:'html':'UTF-8'}</td><td>{$t.email|escape:'html':'UTF-8'}</td><td>{$t.stays|escape:'html':'UTF-8'}</td><td>{$t.nights|escape:'html':'UTF-8'}</td><td>{displayPrice price=$t.lifetime_revenue}</td><td>{$t.last_stay|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="6"><em>Nobody attached.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-edit">
<form method="post" class="form-horizontal"><input type="hidden" name="id_account_a" value="{$a.id_pulse_crm_account|escape:'html':'UTF-8'}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="name" class="form-control" value="{$a.name|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Company</label><div class="col-sm-8"><input type="number" name="id_pulse_company" class="form-control" value="{$a.id_pulse_company|intval}"></div></div>
  <div class="form-group"><label class="col-sm-4">Segment</label><div class="col-sm-8"><select name="segment" class="form-control">
    {foreach ['corporate','government','ngo','travel_agent','airline','embassy','other'] as $sg}<option value="{$sg|escape:'html':'UTF-8'}" {if $a.segment == $sg}selected{/if}>{$sg|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Industry</label><div class="col-sm-8"><input name="industry" class="form-control" value="{$a.industry|escape:'html'}"></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Manager</label><div class="col-sm-8"><select name="account_manager" class="form-control"><option value="">—</option>
    {foreach $employees as $e}<option value="{$e.id_employee|escape:'html':'UTF-8'}" {if $a.account_manager == $e.id_employee}selected{/if}>{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Status</label><div class="col-sm-8"><select name="status_a" class="form-control">
    {foreach ['prospect','active','dormant','lost'] as $st}<option value="{$st|escape:'html':'UTF-8'}" {if $a.status == $st}selected{/if}>{$st|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Potential</label><div class="col-sm-8"><input type="number" name="potential_nights" class="form-control" value="{$a.potential_nights|escape:'html':'UTF-8'}"> <input name="potential_value" class="form-control" value="{$a.potential_value|floatval}"></div></div>
  <div class="form-group"><label class="col-sm-4">Next review</label><div class="col-sm-8"><input type="date" name="next_review" class="form-control" value="{$a.next_review|escape:'html':'UTF-8'}"></div></div>
</div></div>
  <div class="form-group"><label class="col-sm-2">Notes</label><div class="col-sm-10"><textarea name="notes" class="form-control" rows="4">{$a.notes|escape:'html'}</textarea></div></div>
  <button name="saveAccount" class="btn btn-primary">Save</button>
</form>
</div>

</div></div></div>
