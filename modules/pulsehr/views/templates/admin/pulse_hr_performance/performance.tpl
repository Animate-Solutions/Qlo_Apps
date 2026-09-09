<div class="pulse-hr"><div class="panel"><h3><i class="icon-legal"></i> Discipline &amp; appraisal</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#p-cases">Open cases ({$cases|count})</a></li><li><a data-toggle="tab" href="#p-new">Raise a case</a></li><li><a data-toggle="tab" href="#p-app">Appraisals</a></li><li><a data-toggle="tab" href="#p-train">Training</a></li><li><a data-toggle="tab" href="#p-closed">Closed</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="p-cases">
<table class="table table-condensed"><thead><tr><th>No</th><th>Issued</th><th>Who</th><th>Type</th><th>Subject</th><th>Acknowledged</th><th>Status</th><th class="hr-actions">Close</th></tr></thead><tbody>
{foreach $cases as $c}<tr class="{if $c.type=='final_warning' || $c.type=='suspension'}danger{elseif $c.type=='commendation'}success{/if}">
<td>{$c.case_no}</td><td>{$c.issued_on}</td><td><a href="{$employee_url}&id_employee_hr={$c.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$c.employee_name}</a><div class="muted">{$c.dept_name}</div></td>
<td>{$c.type|replace:'_':' '}</td><td>{$c.subject|escape:'html':'UTF-8'}{if $c.description}<div class="muted">{$c.description|escape:'html':'UTF-8'|truncate:120:'…'}</div>{/if}
{if $c.response}<div><em>Their reply:</em> {$c.response|escape:'html':'UTF-8'|truncate:160:'…'}</div>{/if}</td>
<td>{if $c.acknowledged_at}{$c.acknowledged_at|date_format:"%d/%m %H:%M"}<div class="muted flag">{$c.ack_ip|escape:'html':'UTF-8'}</div>{else}<span class="label label-warning">waiting on the portal</span>{/if}</td>
<td>{$c.status}</td>
<td class="hr-actions"><form method="post" class="form-inline"><input type="hidden" name="id_case" value="{$c.id_pulse_hr_case}"><input name="outcome" class="input-sm" placeholder="Outcome" style="width:140px">
<select name="case_status" class="input-sm"><option value="closed">closed</option><option value="withdrawn">withdrawn</option></select>
<button name="closeCase" class="btn btn-xs btn-default">Close</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em class="muted">No open cases.</em></td></tr>{/foreach}
</tbody></table>
<p class="muted">A case appears on the member of staff's portal the moment it is raised; their acknowledgement records the time and the address it came from, and they may add a written reply.</p></div>

<div class="tab-pane" id="p-new"><form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-3">Employee</label><div class="col-sm-9"><select name="id_employee_hr" class="form-control" required>{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name} ({$s.staff_no}) — {$s.dept_name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3">Type</label><div class="col-sm-9"><select name="type" class="form-control">{foreach $case_types as $k=>$v}<option value="{$k}">{$v}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3">Subject</label><div class="col-sm-9"><input name="subject" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-3">Incident date</label><div class="col-sm-9"><input type="date" name="incident_date" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-3">Issued on</label><div class="col-sm-9"><input type="date" name="issued_on" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}"></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-3">Detail</label><div class="col-sm-9"><textarea name="description" class="form-control" rows="6" placeholder="What happened, when, who saw it, and what the employee is being asked to answer."></textarea></div></div>
<div class="form-group"><label class="col-sm-3">Suspension</label><div class="col-sm-9"><input type="date" name="suspension_from" class="form-control"> <input type="date" name="suspension_to" class="form-control">
<label class="checkbox-inline"><input type="checkbox" name="unpaid" value="1"> unpaid</label></div></div>
<button name="saveCase" class="btn btn-primary">Raise case</button>
<p class="help-block">A warning stops counting against someone after the life set in HR Settings. A suspension dated from today also sets their status to suspended and ends their portal sessions.</p>
</div></div></form></div>

<div class="tab-pane" id="p-app">
<form method="post" class="form-inline"><input type="hidden" name="id_cycle_save">
<input name="code" class="form-control" placeholder="2026-H1" style="width:120px" required> <input name="name" class="form-control" placeholder="Cycle name" required>
<input type="date" name="period_from" class="form-control" required> <input type="date" name="period_to" class="form-control" required> <input type="date" name="due_on" class="form-control">
<select name="cstatus" class="form-control"><option value="open">open</option><option value="in_progress">in progress</option><option value="closed">closed</option></select>
<button name="saveCycle" class="btn btn-default">Save cycle</button></form>
<table class="table table-condensed" style="margin-top:8px"><tbody>{foreach $cycles as $c}<tr><td><a href="{$self_url}&id_cycle={$c.id_pulse_hr_appraisal_cycle}">{$c.name}</a></td><td>{$c.period_from} – {$c.period_to}</td><td>due {$c.due_on}</td><td>{$c.status}</td></tr>{foreachelse}<tr><td><em class="muted">No cycles yet.</em></td></tr>{/foreach}</tbody></table>
{if $id_cycle}
<form method="post" class="form-inline"><input type="hidden" name="id_cycle" value="{$id_cycle}">
<select name="department" class="form-control"><option value="">Everyone</option>{foreach $departments as $d}<option value="{$d.code}">{$d.name}</option>{/foreach}</select>
<button name="openCycle" class="btn btn-default">Open appraisals for this cycle</button></form>
<table class="table table-condensed" style="margin-top:8px"><thead><tr><th>Who</th><th>Department</th><th>Reviewer</th><th class="text-right">Rating</th><th>Status</th><th>Recommendation</th><th></th></tr></thead><tbody>
{foreach $appraisals as $a}<tr><td>{$a.employee_name}</td><td>{$a.dept_name}</td><td>{$a.reviewer_name}</td><td class="text-right">{$a.overall_rating}</td><td>{$a.status|replace:'_':' '}</td><td>{$a.recommendation|replace:'_':' '}</td>
<td><a class="btn btn-xs btn-primary" href="{$self_url}&id_appraisal={$a.id_pulse_hr_appraisal}">Open</a></td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">No appraisals in this cycle yet — open them above.</em></td></tr>{/foreach}
</tbody></table>{/if}</div>

<div class="tab-pane" id="p-train">
{if $training_expiring}<div class="alert alert-warning"><strong>Expiring training:</strong> {foreach $training_expiring as $t}{$t.employee_name} — {$t.course} ({$t.expires_on}){if !$t@last}; {/if}{/foreach}</div>{/if}
<table class="table table-condensed"><thead><tr><th>Who</th><th>Course</th><th>Type</th><th>Provider</th><th>Completed</th><th>Expires</th><th class="text-right">Cost</th></tr></thead><tbody>
{foreach $training as $t}<tr class="{if $t.expires_on && $t.expires_on < $smarty.now|date_format:'%Y-%m-%d'}danger{/if}"><td>{$t.employee_name}</td><td>{$t.course}</td><td>{$t.type|replace:'_':' '}</td><td>{$t.provider}</td><td>{$t.completed_on}</td><td>{$t.expires_on}</td><td class="text-right">{displayPrice price=$t.cost}</td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">Nothing recorded.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_training">
<select name="id_employee_hr" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name}</option>{/foreach}</select>
<input name="course" class="form-control" placeholder="Course" required> <input name="provider" class="form-control" placeholder="Provider">
<select name="training_type" class="form-control">{foreach ['induction','safety','food_hygiene','fire','first_aid','service','technical','compliance','other'] as $tt}<option value="{$tt}">{$tt|replace:'_':' '}</option>{/foreach}</select>
<input type="date" name="completed_on" class="form-control"> <input type="date" name="expires_on" class="form-control">
<input name="cost" type="number" step="0.01" class="form-control" placeholder="₦" style="width:110px">
<input name="certificate_no" class="form-control" placeholder="Cert no" style="width:120px">
<button name="saveTraining" class="btn btn-default">Record training</button></form></div>

<div class="tab-pane" id="p-closed">
<table class="table table-condensed"><tbody>{foreach $closed as $c}<tr><td>{$c.case_no}</td><td>{$c.issued_on}</td><td>{$c.employee_name}</td><td>{$c.type|replace:'_':' '}</td><td>{$c.subject}</td><td>{$c.outcome}</td><td>{$c.status}</td></tr>{foreachelse}<tr><td><em class="muted">None.</em></td></tr>{/foreach}</tbody></table></div>

</div></div></div>
