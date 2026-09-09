<div class="pulse-crm"><div class="panel"><h3><i class="icon-briefcase"></i> Corporate</h3>
<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#b-accounts">Accounts ({$accounts|count})</a></li>
  <li><a data-toggle="tab" href="#b-pipeline">Pipeline</a></li>
  <li><a data-toggle="tab" href="#b-production">Production</a></li>
  <li><a data-toggle="tab" href="#b-todo">Follow-ups &amp; stale accounts</a></li>
  <li><a data-toggle="tab" href="#b-new">New account</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="b-accounts">
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmCorporate"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input name="q" value="{$q|escape:'html'}" class="form-control" placeholder="Account or company">
  <select name="status" class="form-control"><option value="">All</option>
    <option value="prospect" {if $status == 'prospect'}selected{/if}>prospect</option><option value="active" {if $status == 'active'}selected{/if}>active</option>
    <option value="dormant" {if $status == 'dormant'}selected{/if}>dormant</option><option value="lost" {if $status == 'lost'}selected{/if}>lost</option></select>
  <button class="btn btn-primary">Search</button></form>
<table class="table table-condensed"><thead><tr><th>Account</th><th>Segment</th><th>Manager</th><th>Status</th><th>Contacts</th><th>Open deals</th><th>Ledger</th><th>Potential</th><th>Last contact</th><th></th></tr></thead><tbody>
{foreach $accounts as $a}<tr class="{if $a.status == 'lost'}text-muted{elseif $a.status == 'dormant'}warning{/if}">
  <td><a href="{$self_url}&id_account={$a.id_pulse_crm_account|escape:'html':'UTF-8'}">{$a.name|escape:'html':'UTF-8'}</a>{if $a.company_name && $a.company_name != $a.name}<br><small class="text-muted">{$a.company_name|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{$a.segment|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$a.manager|escape:'html':'UTF-8'}</td><td>{$a.status|escape:'html':'UTF-8'}</td><td>{$a.contacts|escape:'html':'UTF-8'}</td><td>{$a.open_opps|escape:'html':'UTF-8'}</td>
  <td>{if $a.ledger_balance}{displayPrice price=$a.ledger_balance}{/if}</td>
  <td>{$a.potential_nights|escape:'html':'UTF-8'} nights · {displayPrice price=$a.potential_value}</td>
  <td>{$a.last_touch|date_format:"%d/%m/%Y"}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_account={$a.id_pulse_crm_account|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>No accounts yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-pipeline">
<table class="table table-condensed"><thead><tr><th>Stage</th><th>Deals</th><th>Room nights</th><th>Value</th><th>Weighted</th></tr></thead><tbody>
{foreach $pipeline as $p}<tr><td><b>{$p.stage|escape:'html':'UTF-8'}</b></td><td>{$p.deals|escape:'html':'UTF-8'}</td><td>{$p.nights|escape:'html':'UTF-8'}</td><td>{displayPrice price=$p.value}</td><td>{displayPrice price=$p.weighted}</td></tr>
{foreachelse}<tr><td colspan="5"><em>Nothing in the pipeline.</em></td></tr>{/foreach}
</tbody></table>
<h4>Open opportunities</h4>
<table class="table table-condensed"><thead><tr><th>Account</th><th>Opportunity</th><th>Stage</th><th>Nights</th><th>Value</th><th>Probability</th><th>Close</th><th>Owner</th></tr></thead><tbody>
{foreach $opportunities as $o}<tr class="{if $o.close_date && $o.close_date < $smarty.now|date_format:'%Y-%m-%d'}warning{/if}">
  <td><a href="{$self_url}&id_account={$o.id_pulse_crm_account|escape:'html':'UTF-8'}">{$o.account|escape:'html':'UTF-8'}</a></td><td>{$o.name|escape:'html':'UTF-8'}</td><td>{$o.stage|escape:'html':'UTF-8'}</td><td>{$o.expected_nights|escape:'html':'UTF-8'}</td>
  <td>{displayPrice price=$o.expected_value}</td><td>{$o.probability|escape:'html':'UTF-8'}%</td><td>{$o.close_date|escape:'html':'UTF-8'}</td><td>{$o.owner_name|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing open.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-production">
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmCorporate"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input type="number" name="year" value="{$year|escape:'html':'UTF-8'}" class="form-control" style="width:100px"> <button class="btn btn-default">Run</button></form>
<table class="table table-condensed"><thead><tr><th>Company</th><th>Status</th><th>Nights {$year|escape:'html':'UTF-8'}</th><th>Nights {$year-1}</th><th>±</th><th>Revenue {$year|escape:'html':'UTF-8'}</th><th>Revenue {$year-1}</th><th>±</th><th>±%</th><th>ADR</th></tr></thead><tbody>
{foreach $production as $p}<tr class="{if $p.revenue_var < 0}danger{elseif $p.revenue_var > 0}success{/if}">
  <td>{if $p.id_pulse_crm_account}<a href="{$self_url}&id_account={$p.id_pulse_crm_account|escape:'html':'UTF-8'}">{$p.name|escape:'html':'UTF-8'}</a>{else}{$p.name|escape:'html':'UTF-8'}{/if}</td>
  <td>{$p.status|default:'—'|escape:'html':'UTF-8'}</td><td>{$p.nights_ty|escape:'html':'UTF-8'}</td><td>{$p.nights_ly|escape:'html':'UTF-8'}</td><td>{$p.nights_var|escape:'html':'UTF-8'}</td>
  <td>{displayPrice price=$p.revenue_ty}</td><td>{displayPrice price=$p.revenue_ly}</td><td>{displayPrice price=$p.revenue_var}</td>
  <td>{if $p.revenue_var_pct !== null}{$p.revenue_var_pct|escape:'html':'UTF-8'}%{else}new{/if}</td><td>{displayPrice price=$p.adr_ty}</td></tr>
{foreachelse}<tr><td colspan="10"><em>No corporate production recorded. Guests are attached to a company on their Front Desk profile.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-todo">
<h4>Follow-ups</h4>
<table class="table table-condensed"><thead><tr><th>Due</th><th>Account</th><th>Type</th><th>Subject</th><th>Who</th><th></th></tr></thead><tbody>
{foreach $follow_ups as $f}<tr class="{if $f.follow_up_at < $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'}danger{/if}">
  <td>{$f.follow_up_at|date_format:"%d/%m %H:%M"}</td><td><a href="{$self_url}&id_account={$f.id_pulse_crm_account|escape:'html':'UTF-8'}">{$f.account|escape:'html':'UTF-8'}</a></td><td>{$f.type|escape:'html':'UTF-8'}</td><td>{$f.subject|escape:'html':'UTF-8'}</td><td>{$f.who|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_activity" value="{$f.id_pulse_crm_activity|escape:'html':'UTF-8'}"><button name="doneFollowUp" class="btn btn-xs btn-default">Done</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>Nothing due.</em></td></tr>{/foreach}
</tbody></table>
<h4>Nobody has called these in 60 days</h4>
<table class="table table-condensed"><tbody>
{foreach $stale as $s}<tr><td><a href="{$self_url}&id_account={$s.id_pulse_crm_account|escape:'html':'UTF-8'}">{$s.name|escape:'html':'UTF-8'}</a></td><td>{$s.status|escape:'html':'UTF-8'}</td><td>last contact {$s.last_touch|default:'never'|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td><em>Every account has been touched recently.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-new">
<form method="post" class="form-horizontal">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Account name</label><div class="col-sm-8"><input name="name" class="form-control" required></div></div>
  <div class="form-group"><label class="col-sm-4">Company (ledger)</label><div class="col-sm-8"><select name="id_pulse_company" class="form-control"><option value="0">— not linked —</option>
    {foreach $companies as $co}<option value="{$co.id_pulse_company|escape:'html':'UTF-8'}">{$co.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
    <span class="help-block">Linking to a Front Desk company gives you the city ledger and the production report.</span></div></div>
  <div class="form-group"><label class="col-sm-4">Segment</label><div class="col-sm-8"><select name="segment" class="form-control">
    <option value="corporate">corporate</option><option value="government">government</option><option value="ngo">NGO</option><option value="travel_agent">travel agent</option><option value="airline">airline</option><option value="embassy">embassy</option><option value="other">other</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Industry</label><div class="col-sm-8"><input name="industry" class="form-control" placeholder="oil &amp; gas, banking, telecoms…"></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Manager</label><div class="col-sm-8"><select name="account_manager" class="form-control"><option value="">—</option>{foreach $employees as $e}<option value="{$e.id_employee|escape:'html':'UTF-8'}">{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Status</label><div class="col-sm-8"><select name="status_a" class="form-control"><option value="prospect">prospect</option><option value="active">active</option><option value="dormant">dormant</option><option value="lost">lost</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Potential</label><div class="col-sm-8"><input type="number" name="potential_nights" class="form-control" placeholder="room nights a year"> <input name="potential_value" class="form-control" placeholder="naira a year"></div></div>
  <div class="form-group"><label class="col-sm-4">Next review</label><div class="col-sm-8"><input type="date" name="next_review" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-4">Notes</label><div class="col-sm-8"><textarea name="notes" class="form-control" rows="3"></textarea></div></div>
</div></div>
<button name="saveAccount" class="btn btn-primary btn-lg">Create account</button>
</form>
</div>

</div></div></div>
