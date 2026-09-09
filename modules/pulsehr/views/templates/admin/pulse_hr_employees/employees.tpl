<div class="pulse-hr"><div class="panel"><h3><i class="icon-user"></i> Employees</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#e-list">On strength ({$rows|count})</a></li><li><a data-toggle="tab" href="#e-new">Add someone</a></li><li><a data-toggle="tab" href="#e-changes">Detail changes ({$change_requests|count})</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="e-list">
<form method="get" class="form-inline" style="margin-bottom:8px">
<input type="hidden" name="controller" value="AdminPulseHrEmployees"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<input name="q" class="form-control" placeholder="Name, staff number, phone, NIN" value="{$f.q|escape:'html':'UTF-8'}">
<select name="department" class="form-control"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.code}"{if $f.department==$d.code} selected{/if}>{$d.name}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">Any status</option>{foreach ['probation','active','on_leave','suspended','exited'] as $s}<option value="{$s}"{if $f.status==$s} selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select>
<label class="checkbox-inline"><input type="checkbox" name="include_exited" value="1"{if $f.include_exited} checked{/if}> include leavers</label>
<button class="btn btn-default">Search</button></form>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Department</th><th>Position</th><th>Grade</th><th>Manager</th><th>Joined</th><th>Status</th><th>Portal</th></tr></thead><tbody>
{foreach $rows as $r}<tr data-hr-href="{$self_url}&id_employee_hr={$r.id_pulse_hr_employee}" class="{if $r.status=='exited'}text-muted{elseif $r.status=='suspended'}danger{elseif $r.status=='probation'}warning{/if}">
<td>{$r.staff_no}</td><td><a href="{$self_url}&id_employee_hr={$r.id_pulse_hr_employee}">{$r.full_name}</a>{if $r.bo_user} <span class="label label-default" title="Linked back-office user">BO</span>{/if}</td>
<td>{$r.dept_name}{if $r.section_name} <span class="muted">/ {$r.section_name}</span>{/if}</td><td>{$r.position_title}</td><td>{$r.grade_code}</td><td>{$r.manager_name}</td><td>{$r.hire_date}</td>
<td><span class="label label-{if $r.status=='active'}success{elseif $r.status=='exited'}default{elseif $r.status=='suspended'}danger{else}warning{/if}">{$r.status|replace:'_':' '}</span></td>
<td>{if $r.pin_hash}{if $r.ess_enabled}<span class="label label-success">on</span>{else}<span class="label label-default">off</span>{/if}{else}<span class="muted">no PIN</span>{/if}</td></tr>
{foreachelse}<tr><td colspan="9"><em class="muted">Nobody matches.</em></td></tr>{/foreach}
</tbody></table></div>

<div class="tab-pane" id="e-new"><form method="post" class="form-horizontal"><div class="row">
<div class="col-md-4">
<h4>Person</h4>
<div class="form-group"><label class="col-sm-4">Staff number</label><div class="col-sm-8"><input name="staff_no" class="form-control" placeholder="leave blank to auto-number"></div></div>
<div class="form-group"><label class="col-sm-4">First name</label><div class="col-sm-8"><input name="firstname" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4">Surname</label><div class="col-sm-8"><input name="lastname" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4">Other names</label><div class="col-sm-8"><input name="othernames" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Gender</label><div class="col-sm-8"><select name="gender" class="form-control"><option value="m">Male</option><option value="f">Female</option><option value="x">Other</option></select></div></div>
<div class="form-group"><label class="col-sm-4">Date of birth</label><div class="col-sm-8"><input type="date" name="dob" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Phone</label><div class="col-sm-8"><input name="phone" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Email</label><div class="col-sm-8"><input name="email" class="form-control"></div></div>
</div>
<div class="col-md-4">
<h4>Post</h4>
<div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><select name="id_pulse_hr_department" class="form-control">{foreach $departments as $d}<option value="{$d.id_pulse_hr_department}">{$d.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Section</label><div class="col-sm-8"><select name="id_pulse_hr_section" class="form-control"><option value="">—</option>{foreach $sections as $s}<option value="{$s.id_pulse_hr_section}">{$s.dept_code} / {$s.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Position</label><div class="col-sm-8"><select name="id_pulse_hr_position" class="form-control"><option value="">—</option>{foreach $positions as $p}<option value="{$p.id_pulse_hr_position}">{$p.title} ({$p.dept_code})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Grade</label><div class="col-sm-8"><select name="id_pulse_hr_grade" class="form-control"><option value="">—</option>{foreach $grades as $g}<option value="{$g.id_pulse_hr_grade}">{$g.code} {$g.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Reports to</label><div class="col-sm-8"><select name="id_manager" class="form-control"><option value="">—</option>{foreach $managers as $m}<option value="{$m.id_pulse_hr_employee}">{$m.full_name} ({$m.staff_no})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Hire date</label><div class="col-sm-8"><input type="date" name="hire_date" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}"></div></div>
<div class="form-group"><label class="col-sm-4">Probation (months)</label><div class="col-sm-8"><input name="probation_months" type="number" min="0" max="24" class="form-control" value="6"></div></div>
</div>
<div class="col-md-4">
<h4>First contract</h4>
<div class="form-group"><label class="col-sm-4">Type</label><div class="col-sm-8"><select name="contract_type" class="form-control"><option value="permanent">Permanent</option><option value="fixed_term">Fixed term</option><option value="contract">Contract</option><option value="casual">Casual</option><option value="service">Service</option><option value="intern">Intern</option></select></div></div>
<div class="form-group"><label class="col-sm-4">Pay basis</label><div class="col-sm-8"><select name="pay_basis" class="form-control"><option value="monthly">Monthly</option><option value="daily">Daily</option><option value="hourly">Hourly</option><option value="per_shift">Per shift</option></select></div></div>
<div class="form-group"><label class="col-sm-4">Rate (₦)</label><div class="col-sm-8"><input name="pay_rate" type="number" step="0.01" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Contract ends</label><div class="col-sm-8"><input type="date" name="end_date" class="form-control"> <span class="help-block">Required for fixed term, contract and intern.</span></div></div>
<div class="form-group"><label class="col-sm-4">Night shift</label><div class="col-sm-8"><label><input type="checkbox" name="night_shift" value="1"> works nights (payroll allowance)</label></div></div>
<div class="form-group"><label class="col-sm-4">Portal PIN</label><div class="col-sm-8"><input name="pin" type="text" inputmode="numeric" class="form-control" placeholder="digits only"></div></div>
<button name="saveEmployee" class="btn btn-primary btn-lg">Create employee and open onboarding</button>
</div></div></form></div>

<div class="tab-pane" id="e-changes">
<p class="muted">Staff can ask for a detail to be corrected from the portal. Nothing changes on the record until somebody here approves it.</p>
<table class="table table-condensed"><thead><tr><th>Asked</th><th>Who</th><th>Detail</th><th>From</th><th>To</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $change_requests as $c}<tr><td>{$c.date_add|date_format:"%d/%m %H:%M"}</td><td><a href="{$self_url}&id_employee_hr={$c.id_pulse_hr_employee}">{$c.employee_name}</a> <span class="muted">{$c.staff_no}</span></td>
<td>{$c.field|replace:'_':' '}</td><td class="muted">{$c.old_value|escape:'html':'UTF-8'}</td><td><strong>{$c.new_value|escape:'html':'UTF-8'}</strong></td>
<td class="hr-actions"><form method="post" class="form-inline"><input type="hidden" name="id_change" value="{$c.id_pulse_hr_change_request}"><input name="change_note" class="input-sm" placeholder="note">
<button name="decideChange" class="btn btn-xs btn-success" formaction="{$self_url}&approve=1">Approve</button>
<button name="decideChange" class="btn btn-xs btn-default" formaction="{$self_url}&approve=0">Reject</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em class="muted">Nothing waiting.</em></td></tr>{/foreach}
</tbody></table></div>

</div></div></div>
