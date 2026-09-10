<div class="pulse-pr"><div class="panel"><h3><i class="icon-clock-o"></i> Casual &amp; weekly pay</h3>
<p class="text-muted">Banqueting extras, laundry casuals and the porters brought in for a wedding are paid weekly, usually in cash, from a sheet a supervisor signs. Open the week, pull the roster, key the days, approve, print, pay.</p>
{if !$ta}<div class="alert alert-warning">Pulse Time is not installed, so days and hours are keyed in on the batch screen rather than pulled from an approved timesheet.</div>{/if}
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#cw-list">Weeks</a></li><li><a data-toggle="tab" href="#cw-new">Open a week</a></li></ul>
<div class="tab-content">
<div class="tab-pane active" id="cw-list">
<table class="table table-condensed"><thead><tr><th>Batch</th><th>Week</th><th>Dept</th><th>Method</th><th>Pay date</th><th>People</th><th>Gross</th><th>Deducted</th><th>Net</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $batches as $b}<tr class="{if $b.status=='draft'}warning{elseif $b.status=='posted'}success{/if}">
<td><a href="{$self_url}&id_batch={$b.id_pulse_pr_casual_batch}">{$b.batch_no}</a></td><td>{$b.week_start} → {$b.week_end}</td><td>{$b.department|default:'all'}</td>
<td>{$b.pay_method}</td><td>{$b.pay_date}</td><td>{$b.headcount}</td><td>{displayPrice price=$b.total_gross}</td><td>{displayPrice price=$b.total_gross-$b.total_net}</td>
<td><strong>{displayPrice price=$b.total_net}</strong></td><td>{$b.status}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&id_batch={$b.id_pulse_pr_casual_batch}">Open</a></td></tr>
{foreachelse}<tr><td colspan="11"><em>No weeks yet.</em></td></tr>{/foreach}
</tbody></table>
</div>
<div class="tab-pane" id="cw-new">
<form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Week starting</label><div class="col-sm-6"><input type="date" name="week_start" class="form-control" value="{$week_start}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Week ending</label><div class="col-sm-6"><input type="date" name="week_end" class="form-control"><span class="help-block">Leave blank for six days after the start.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Department</label><div class="col-sm-6"><select name="department" class="form-control"><option value="">All</option>{foreach $departments as $d}<option value="{$d.department}">{$d.department}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Paid by</label><div class="col-sm-6"><select name="pay_method" class="form-control"><option value="cash">cash</option><option value="bank">bank transfer</option><option value="mixed">mixed</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay date</label><div class="col-sm-6"><input type="date" name="pay_date" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-6"><input name="note" class="form-control"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-6"><button name="createBatch" class="btn btn-primary btn-lg">Open the week</button></div></div>
</div></div></form>
</div>
</div></div></div>
