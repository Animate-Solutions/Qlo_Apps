<div class="pulse-pr"><div class="panel">
<h3><i class="icon-cutlery"></i> {$pool.pool_no} &mdash; service charge {$pool.period}
<span class="pr-status pr-{$pool.status} pull-right">{$pool.status}</span></h3>
<a class="btn btn-xs btn-default" href="{$self_url}">Back to the pools</a>
{if $pool.lines}<a class="btn btn-xs btn-default" href="{$self_url}&id_pool={$pool.id_pulse_pr_tronc_pool}&export=1">Export the statement (CSV)</a>{/if}

<div class="row pr-tiles">
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$pool.gross_pool}</span><span class="pr-l">collected</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$pool.admin_amount}</span><span class="pr-l">administration ({$pool.admin_pct|floatval}%)</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$pool.breakage_amount}</span><span class="pr-l">breakage retained</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$pool.distributable}</span><span class="pr-l">distributable</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$pool.distributed}</span><span class="pr-l">distributed to {$pool.participants}</span></div></div>
<div class="col-md-2"><div class="pr-tile {if $pool.management_capped_amount > 0}warning{/if}"><span class="pr-n">{displayPrice price=$pool.management_capped_amount}</span><span class="pr-l">redistributed by the {$pool.management_cap_pct|floatval}% cap</span></div></div>
</div>
{if $pool.rounding_residue}<p class="text-muted">Rounding residue of {displayPrice price=$pool.rounding_residue} was placed on the largest share, so the shares sum exactly to the distributable pool.</p>{/if}
<p class="text-muted">{$pool.source_note}</p>

{if $pool.status=='draft' || $pool.status=='distributed'}
<form method="post" class="form-inline pr-actions"><input type="hidden" name="id_pool_a" value="{$pool.id_pulse_pr_tronc_pool}"><input type="hidden" name="id_pool" value="{$pool.id_pulse_pr_tronc_pool}">
<label>F&amp;B</label> <input name="collected_fnb" type="number" step="0.01" class="form-control input-sm" value="{$pool.collected_fnb|floatval}" style="width:130px">
<label>Rooms</label> <input name="collected_rooms" type="number" step="0.01" class="form-control input-sm" value="{$pool.collected_rooms|floatval}" style="width:130px">
<label>Manual</label> <input name="collected_manual" type="number" step="0.01" class="form-control input-sm" value="{$pool.collected_manual|floatval}" style="width:130px">
<label>Admin %</label> <input name="admin_pct" type="number" step="0.001" class="form-control input-sm" value="{$pool.admin_pct|floatval}" style="width:90px">
<label>Breakage</label> <input name="breakage_amount" type="number" step="0.01" class="form-control input-sm" value="{$pool.breakage_amount|floatval}" style="width:110px">
<label>Mgmt cap %</label> <input name="management_cap_pct" type="number" step="0.001" class="form-control input-sm" value="{$pool.management_cap_pct|floatval}" style="width:90px">
<select name="basis" class="form-control input-sm"><option value="points" {if $pool.basis=='points'}selected{/if}>points</option><option value="hours" {if $pool.basis=='hours'}selected{/if}>hours</option><option value="equal" {if $pool.basis=='equal'}selected{/if}>equal</option></select>
<button name="updatePool" class="btn btn-default btn-sm">Update pool</button></form>
{/if}
</div>

<div class="panel">
<ul class="nav nav-tabs"><li class="{if $pool.lines}active{/if}"><a data-toggle="tab" href="#tp-lines">Shares ({$pool.lines|count})</a></li><li class="{if !$pool.lines}active{/if}"><a data-toggle="tab" href="#tp-dist">Distribute</a></li></ul>
<div class="tab-content">

<div class="tab-pane {if $pool.lines}active{/if}" id="tp-lines">
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Department</th><th>Mgmt</th><th>Points</th><th>Hours</th><th>Weight</th><th>Weighted units</th><th>Share</th><th class="text-right">Amount</th><th>Note</th></tr></thead><tbody>
{foreach $pool.lines as $l}<tr class="{if $l.capped}warning{/if}">
<td>{$l.staff_no}</td><td>{$l.employee_name}</td><td>{$l.department}</td><td>{if $l.is_management}✓{/if}</td>
<td>{$l.points|floatval}</td><td>{$l.hours|floatval}</td><td>{$l.dept_weight|floatval}</td><td>{$l.weighted_units|floatval}</td>
<td>{$l.share_pct}%</td><td class="text-right"><strong>{displayPrice price=$l.amount}</strong></td><td><small>{$l.note}</small></td></tr>
{foreachelse}<tr><td colspan="11"><em>Not distributed yet.</em></td></tr>{/foreach}
</tbody></table>
{if $pool.status=='distributed'}
<form method="post"><input type="hidden" name="id_pool_a" value="{$pool.id_pulse_pr_tronc_pool}"><input type="hidden" name="id_pool" value="{$pool.id_pulse_pr_tronc_pool}">
<button name="approvePool" class="btn btn-success" onclick="return confirm('Approve this distribution? The shares will be picked up as taxable pay by the payroll run for {$pool.period}.')">Approve the distribution</button></form>
{elseif $pool.status=='approved'}<div class="alert alert-success">Approved. The shares will be added to the {$pool.period} payroll run as the TRONC element &mdash; taxable, not pensionable.</div>{/if}
</div>

<div class="tab-pane {if !$pool.lines}active{/if}" id="tp-dist">
{if $pool.status=='approved' || $pool.status=='paid'}<div class="alert alert-info">This pool is {$pool.status} and can no longer be redistributed.</div>
{else}
<form method="post"><input type="hidden" name="id_pool_a" value="{$pool.id_pulse_pr_tronc_pool}"><input type="hidden" name="id_pool" value="{$pool.id_pulse_pr_tronc_pool}">
<p class="text-muted">Leave a person's points blank to use their department default. Tick <em>out</em> to leave somebody out of this pool entirely.</p>
<table class="table table-condensed"><thead><tr><th>Out</th><th>Staff no</th><th>Name</th><th>Department</th><th>Weight</th><th>Points</th><th>Hours</th></tr></thead><tbody>
{foreach $employees as $e}
{assign var=w value=$weights[$e.department]}
<tr><td><input type="checkbox" name="exclude[{$e.id_pulse_pr_employee}]" value="1"></td>
<td>{$e.staff_no}</td><td>{$e.firstname} {$e.lastname}</td><td>{$e.department}{if $w && $w.is_management} <span class="label label-default">management</span>{/if}</td>
<td>{if $w}{$w.weight|floatval}{else}1{/if}</td>
<td><input name="points[{$e.id_pulse_pr_employee}]" type="number" step="0.001" class="form-control input-sm" placeholder="{if $w}{$w.default_points|floatval}{else}10{/if}" style="width:100px"></td>
<td><input name="hours[{$e.id_pulse_pr_employee}]" type="number" step="0.001" class="form-control input-sm" placeholder="from the timesheet" style="width:140px"></td></tr>
{/foreach}
</tbody></table>
<button name="distributePool" class="btn btn-primary btn-lg">Distribute</button></form>
{/if}
</div>

</div></div></div>
