<div class="pulse-crm"><div class="panel"><h3><i class="icon-medkit"></i> Service recovery</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmCases"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control">
  <select name="dept" class="form-control"><option value="">All departments</option>
    <option value="frontdesk" {if $dept == 'frontdesk'}selected{/if}>frontdesk</option><option value="housekeeping" {if $dept == 'housekeeping'}selected{/if}>housekeeping</option>
    <option value="engineering" {if $dept == 'engineering'}selected{/if}>engineering</option><option value="fnb" {if $dept == 'fnb'}selected{/if}>fnb</option>
    <option value="security" {if $dept == 'security'}selected{/if}>security</option><option value="management" {if $dept == 'management'}selected{/if}>management</option></select>
  <button class="btn btn-primary">Run</button></form>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#r-open">Open ({$open|count})</a></li>
  <li><a data-toggle="tab" href="#r-cost">Cost of recovery</a></li>
  <li><a data-toggle="tab" href="#r-closed">Closed ({$closed|count})</a></li>
  <li><a data-toggle="tab" href="#r-new">Log a glitch</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="r-open">
{if $overdue}<div class="alert alert-danger">{$overdue|count} case(s) are past their SLA. A recovery that arrives late is not a recovery.</div>{/if}
<table class="table table-condensed"><thead><tr><th>Case</th><th>Opened</th><th>Source</th><th>Severity</th><th>Department</th><th>Guest</th><th>Room</th><th>Title</th><th>Owner</th><th>SLA</th><th>Action</th><th>Cost</th><th></th></tr></thead><tbody>
{foreach $open as $c}<tr class="{if $c.overdue}danger{elseif $c.severity == 'critical' || $c.severity == 'high'}warning{/if}">
  <td>{$c.case_no|escape:'html':'UTF-8'}</td><td>{$c.opened_at|date_format:"%d/%m %H:%M"}</td><td>{$c.source|escape:'html':'UTF-8'}</td><td>{$c.severity|escape:'html':'UTF-8'}</td><td>{$c.department|escape:'html':'UTF-8'}</td>
  <td>{$c.guest|escape:'html':'UTF-8'}</td><td>{$c.room_num|escape:'html':'UTF-8'}</td><td>{$c.title|truncate:50|escape:'html':'UTF-8'}</td><td>{$c.owner_name|escape:'html':'UTF-8'}</td>
  <td>{if $c.overdue}<b class="text-danger">overdue</b>{else}{$c.sla_due|date_format:"%d/%m %H:%M"}{/if}</td>
  <td>{$c.recovery_action|escape:'html':'UTF-8'}</td><td>{displayPrice price=$c.recovery_cost}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_case={$c.id_pulse_crm_case|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="13"><em>Nothing open.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="r-cost">
<table class="table table-condensed"><thead><tr><th>Department</th><th>Cases</th><th>Closed</th><th>Open</th><th>Total cost</th><th>Average</th><th>Avg hours to close</th><th>Comps</th><th>Discounts</th><th>Upgrades</th><th>Gifts</th></tr></thead><tbody>
{foreach $cost as $c}<tr><td><b>{$c.department|escape:'html':'UTF-8'}</b></td><td>{$c.cases|escape:'html':'UTF-8'}</td><td>{$c.closed|escape:'html':'UTF-8'}</td><td>{$c.open|escape:'html':'UTF-8'}</td><td><b>{displayPrice price=$c.cost}</b></td><td>{displayPrice price=$c.avg_cost}</td><td>{$c.avg_hours|escape:'html':'UTF-8'}</td>
  <td>{$c.comps|escape:'html':'UTF-8'}</td><td>{$c.discounts|escape:'html':'UTF-8'}</td><td>{$c.upgrades|escape:'html':'UTF-8'}</td><td>{$c.gifts|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="11"><em>No cases in this window.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">This is the table to put on the wall at the morning stand-up. A department that costs the hotel three hundred thousand naira a month in comps has a process problem, not a people problem.</p>
<h4>Root causes</h4>
<table class="table table-condensed"><thead><tr><th>Root cause</th><th>Cases</th><th>Cost</th></tr></thead><tbody>
{foreach $causes as $c}<tr><td>{$c.root_cause|escape:'html':'UTF-8'}</td><td>{$c.cases|escape:'html':'UTF-8'}</td><td>{displayPrice price=$c.cost}</td></tr>
{foreachelse}<tr><td colspan="3"><em>No root causes recorded yet — a case cannot be closed without one, so this fills up on its own.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="r-closed">
<table class="table table-condensed"><thead><tr><th>Case</th><th>Closed</th><th>Department</th><th>Guest</th><th>Root cause</th><th>Recovery</th><th>Cost</th><th></th></tr></thead><tbody>
{foreach $closed as $c}<tr><td>{$c.case_no|escape:'html':'UTF-8'}</td><td>{$c.closed_at|date_format:"%d/%m/%Y"}</td><td>{$c.department|escape:'html':'UTF-8'}</td><td>{$c.guest|escape:'html':'UTF-8'}</td><td>{$c.root_cause|truncate:50|escape:'html':'UTF-8'}</td>
  <td>{$c.recovery_action|escape:'html':'UTF-8'} {if $c.recovery_detail}<small class="text-muted">{$c.recovery_detail|truncate:40|escape:'html':'UTF-8'}</small>{/if}</td><td>{displayPrice price=$c.recovery_cost}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_case={$c.id_pulse_crm_case|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing closed in this window.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="r-new">
<form method="post" class="form-horizontal">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-3">Title</label><div class="col-sm-9"><input name="title" class="form-control" required placeholder="What went wrong, in one line"></div></div>
  <div class="form-group"><label class="col-sm-3">Guest email</label><div class="col-sm-9"><input name="guest_email" class="form-control" placeholder="Optional — links the case to a profile"></div></div>
  <div class="form-group"><label class="col-sm-3">Room</label><div class="col-sm-9"><input type="number" name="id_room" class="form-control" placeholder="Room ID"></div></div>
  <div class="form-group"><label class="col-sm-3">Severity</label><div class="col-sm-9"><select name="severity" class="form-control"><option value="low">low</option><option value="medium" selected>medium</option><option value="high">high</option><option value="critical">critical</option></select></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-3">Department</label><div class="col-sm-9"><select name="department" class="form-control">
    <option value="frontdesk">frontdesk</option><option value="housekeeping">housekeeping</option><option value="engineering">engineering</option><option value="fnb">fnb</option><option value="security">security</option><option value="management">management</option><option value="other">other</option></select></div></div>
  <div class="form-group"><label class="col-sm-3">Owner</label><div class="col-sm-9"><select name="owner" class="form-control"><option value="">—</option>{foreach $employees as $e}<option value="{$e.id_employee|escape:'html':'UTF-8'}">{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">What happened</label><div class="col-sm-9"><textarea name="description" class="form-control" rows="4"></textarea></div></div>
</div></div>
<button name="openCase" class="btn btn-primary btn-lg">Open the case</button>
</form>
</div>

</div></div></div>
