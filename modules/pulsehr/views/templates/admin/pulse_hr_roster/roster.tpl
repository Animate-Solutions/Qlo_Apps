<div class="pulse-hr"><div class="panel"><h3><i class="icon-table"></i> Roster — week of {$week|date_format:"%d %b %Y"}
<span class="label label-{if $published>0}success{else}default{/if}">{$published} published</span> <span class="label label-warning">{$planned} planned</span></h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseHrRoster"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<a class="btn btn-default" href="{$self_url}&week={$prev_week}{if $department}&department={$department}{/if}">&laquo; Previous</a>
<input type="date" name="week" value="{$week}" class="form-control">
<select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.code}"{if $department==$d.code} selected{/if}>{$d.name}</option>{/foreach}</select>
<button class="btn btn-default">Show</button>
<a class="btn btn-default" href="{$self_url}&week={$next_week}{if $department}&department={$department}{/if}">Next &raquo;</a>
<a class="btn btn-default" href="javascript:window.print()"><i class="icon-print"></i> Print</a>
</form>

<form method="post">
<input type="hidden" name="week" value="{$week}"><input type="hidden" name="department" value="{$department}">
<div style="overflow-x:auto"><table class="table table-condensed table-bordered roster" style="margin-top:10px">
<thead><tr><th style="width:190px">Staff</th>{foreach $grid.dates as $d}<th class="{if $d==$business_date}info{/if}">{$d|date_format:"%a"}<br>{$d|date_format:"%d %b"}</th>{/foreach}<th style="width:110px" class="noprint">Fill week</th></tr></thead>
<tbody>
{foreach $grid.staff as $s}<tr>
<td class="name"><a href="{$employee_url}&id_employee_hr={$s.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$s.full_name}</a><div class="muted">{$s.position_title|default:$s.dept_name}</div></td>
{foreach $grid.dates as $d}
{if isset($grid.leave[$s.id_pulse_hr_employee][$d])}<td class="leave">{$grid.leave[$s.id_pulse_hr_employee][$d].type}<br><span class="muted">leave</span></td>
{else}<td{if isset($grid.cells[$s.id_pulse_hr_employee][$d]) && $grid.cells[$s.id_pulse_hr_employee][$d].colour} style="background:{$grid.cells[$s.id_pulse_hr_employee][$d].colour}22"{/if}>
<select name="cell[{$s.id_pulse_hr_employee}][{$d}]" data-hr-row="{$s.id_pulse_hr_employee}">
<option value="">—</option>
<option value="off"{if isset($grid.cells[$s.id_pulse_hr_employee][$d]) && $grid.cells[$s.id_pulse_hr_employee][$d].is_off} selected{/if}>Off</option>
{foreach $shifts as $sh}<option value="{$sh.id_pulse_hr_shift}"{if isset($grid.cells[$s.id_pulse_hr_employee][$d]) && $grid.cells[$s.id_pulse_hr_employee][$d].id_pulse_hr_shift==$sh.id_pulse_hr_shift} selected{/if}>{$sh.code} {$sh.start_time|truncate:5:''}</option>{/foreach}
</select></td>{/if}
{/foreach}
<td class="noprint"><select data-hr-fill="{$s.id_pulse_hr_employee}" class="input-sm"><option value="">fill…</option><option value="off">Off</option>{foreach $shifts as $sh}<option value="{$sh.id_pulse_hr_shift}">{$sh.code}</option>{/foreach}</select></td>
</tr>
{foreachelse}<tr><td colspan="9"><em class="muted">Nobody in that department.</em></td></tr>{/foreach}
</tbody>
<tfoot>
{foreach $departments as $dep}{if !$department || $department==$dep.code}
<tr><td class="name muted">{$dep.name} coverage</td>
{foreach $grid.dates as $d}<td class="cover {foreach $cover[$d] as $c}{if $c.code==$dep.code}{if $c.short>0}short{elseif $c.room_driven}ok{/if}{/if}{/foreach}">
{foreach $cover[$d] as $c}{if $c.code==$dep.code}{$c.rostered}{if $c.room_driven}/{$c.needed}{/if}{if $c.on_leave}<br><span class="muted">{$c.on_leave} off</span>{/if}{/if}{/foreach}</td>{/foreach}
<td class="noprint"></td></tr>
{/if}{/foreach}
</tfoot></table></div>

<div class="noprint" style="margin-top:10px">
<button name="saveGrid" class="btn btn-primary">Save the grid</button>
<button name="copyWeek" class="btn btn-default">Copy last week forward</button>
<button name="publishWeek" class="btn btn-success" data-hr-confirm="Publish this week? Staff will see it on the portal.">Publish to staff</button>
<button name="clearWeek" class="btn btn-default" data-hr-confirm="Clear every unpublished shift in this week?">Clear planned</button>
<span class="muted" style="margin-left:10px">Rostered / needed. Needed comes from occupancy × the department's credit minutes per room{if !$fd} — with Front Desk absent it is worked out from bookings{/if}.</span>
</div>
</form>
</div>

<div class="panel noprint"><h3>Swap requests</h3>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Shift</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $swaps as $sw}<tr><td>{$sw.roster_date}</td><td>{$sw.shift_name}</td><td>{$sw.from_name} <span class="muted">{$sw.from_staff_no}</span></td><td>{$sw.to_name} <span class="muted">{$sw.to_staff_no}</span></td>
<td>{$sw.reason|escape:'html':'UTF-8'}</td><td>{$sw.status|escape:'html':'UTF-8'}</td>
<td class="hr-actions"><form method="post" class="form-inline"><input type="hidden" name="id_swap" value="{$sw.id_pulse_hr_roster_swap}"><input type="hidden" name="week" value="{$week}"><input name="swap_note" class="input-sm" placeholder="note">
<button name="decideSwap" class="btn btn-xs btn-success" formaction="{$self_url}&decision=approved">Approve</button>
<button name="decideSwap" class="btn btn-xs btn-default" formaction="{$self_url}&decision=rejected">Reject</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">No swaps waiting. Staff ask for one from the portal; a supervisor decides here.</em></td></tr>{/foreach}
</tbody></table></div>
</div>
