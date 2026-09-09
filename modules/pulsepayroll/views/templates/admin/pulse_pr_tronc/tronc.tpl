<div class="pulse-pr"><div class="panel"><h3><i class="icon-cutlery"></i> Service charge (tronc)</h3>
<p class="text-muted">A service-charge share is <strong>taxable</strong> pay but it is <strong>not pensionable</strong> &mdash; it never enters the basic + housing + transport base. That treatment is carried by the TRONC pay element, so it is right on every payslip without anyone remembering it.</p>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#tr-pools">Pools</a></li><li><a data-toggle="tab" href="#tr-new">Open a pool</a></li><li><a data-toggle="tab" href="#tr-weights">Department weightings</a></li><li><a data-toggle="tab" href="#tr-hist">This year</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="tr-pools">
<table class="table table-condensed"><thead><tr><th>Pool</th><th>Period</th><th>Basis</th><th>Collected</th><th>Admin</th><th>Breakage</th><th>Distributable</th><th>Distributed</th><th>Participants</th><th>Mgmt capped</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $pools as $p}<tr class="{if $p.status=='paid'}success{elseif $p.status=='draft'}warning{/if}">
<td><a href="{$self_url}&id_pool={$p.id_pulse_pr_tronc_pool}">{$p.pool_no}</a></td><td>{$p.period}</td><td>{$p.basis}</td>
<td>{displayPrice price=$p.gross_pool}</td><td>{displayPrice price=$p.admin_amount}</td><td>{displayPrice price=$p.breakage_amount}</td>
<td>{displayPrice price=$p.distributable}</td><td>{displayPrice price=$p.distributed}</td><td>{$p.participants}</td>
<td>{if $p.management_capped_amount > 0}{displayPrice price=$p.management_capped_amount}{else}—{/if}</td><td>{$p.status}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&id_pool={$p.id_pulse_pr_tronc_pool}">Open</a></td></tr>
{foreachelse}<tr><td colspan="12"><em>No pools yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="tr-new">
<form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Period</label><div class="col-sm-6"><input name="period" class="form-control" value="{$period}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Distribute by</label><div class="col-sm-6"><select name="basis" class="form-control">
<option value="points" {if $default_basis=='points'}selected{/if}>points (grade / department default, overridable per person)</option>
<option value="hours" {if $default_basis=='hours'}selected{/if}>hours actually worked</option>
<option value="equal" {if $default_basis=='equal'}selected{/if}>one share each</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Collected manually</label><div class="col-sm-6"><input name="collected_manual" type="number" step="0.01" class="form-control" value="0">
<span class="help-block">Anything not readable from POS or the folios &mdash; a banqueting event billed outside the system, for instance.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Administration %</label><div class="col-sm-6"><input name="admin_pct" type="number" step="0.001" class="form-control" value="{$default_admin}"><span class="help-block">The share the property retains to cover card charges and breakage administration, if the staff agreement allows it.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Breakage retained</label><div class="col-sm-6"><input name="breakage_amount" type="number" step="0.01" class="form-control" value="0"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Management cap %</label><div class="col-sm-6"><input name="management_cap_pct" type="number" step="0.001" class="form-control" value="{$default_cap}">
<span class="help-block">The most the people flagged as management may take between them. Anything above it is redistributed to everyone else in proportion to their own units.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-6"><input name="note" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-6"><button name="createPool" class="btn btn-primary btn-lg">Open the pool</button></div></div>
</div><div class="col-md-6">
<div class="panel panel-default"><div class="panel-heading">What we can see for {$period}</div><div class="panel-body">
<table class="table table-condensed"><tbody>
<tr><td>Service charge on POS checks</td><td class="text-right">{if $pos}{displayPrice price=$collected.fnb}{else}<em class="text-muted">Pulse POS not installed</em>{/if}</td></tr>
<tr><td>Service charge on rooms folios</td><td class="text-right">{if $fd}{displayPrice price=$collected.rooms}{else}<em class="text-muted">Front Desk not installed</em>{/if}</td></tr>
<tr class="active"><td><strong>Total readable</strong></td><td class="text-right"><strong>{displayPrice price=$collected.total}</strong></td></tr>
</tbody></table>
<p class="text-muted">These figures are read when the pool is opened and can be corrected on the pool screen. With neither module present, key the whole pool in as the manual figure.</p>
</div></div>
</div></div></form>
</div>

<div class="tab-pane" id="tr-weights">
<p class="text-muted">A weighting scales a department's units before the shares are worked out: front-of-house F&amp;B typically carries more than back office. Flag a department as management and its people fall under the cap.</p>
<table class="table table-condensed"><thead><tr><th>Department</th><th>Weight</th><th>Default points</th><th>Management</th></tr></thead><tbody>
{foreach $weights as $d => $w}<tr><td>{$d}</td><td>{$w.weight|floatval}</td><td>{$w.default_points|floatval}</td><td>{if $w.is_management}✓{/if}</td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline">
<input name="wdepartment" class="form-control input-sm" placeholder="department" list="tr-depts">
<datalist id="tr-depts">{foreach $departments as $d}<option value="{$d.department}">{/foreach}</datalist>
<input name="weight" type="number" step="0.001" class="form-control input-sm" placeholder="weight" value="1" style="width:100px">
<input name="default_points" type="number" step="0.001" class="form-control input-sm" placeholder="points" value="10" style="width:100px">
<label><input type="checkbox" name="is_management" value="1"> management</label>
<button name="saveWeight" class="btn btn-primary btn-sm">Save weighting</button></form>
</div>

<div class="tab-pane" id="tr-hist">
<table class="table table-condensed"><thead><tr><th>Period</th><th>Pool</th><th>Basis</th><th>Gross</th><th>Admin</th><th>Breakage</th><th>Distributable</th><th>Distributed</th><th>Participants</th><th>Status</th></tr></thead><tbody>
{foreach $history as $h}<tr><td>{$h.period}</td><td>{$h.pool_no}</td><td>{$h.basis}</td><td>{displayPrice price=$h.gross_pool}</td><td>{displayPrice price=$h.admin_amount}</td>
<td>{displayPrice price=$h.breakage_amount}</td><td>{displayPrice price=$h.distributable}</td><td>{displayPrice price=$h.distributed}</td><td>{$h.participants}</td><td>{$h.status}</td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
