<div class="pulse-pr"><div class="panel"><h3><i class="icon-users"></i> Payroll employees</h3>
{if !$hr}<div class="alert alert-info">Pulse HR is not installed, so this roster is the master employee record for payroll. Install Pulse HR and press <em>Synchronise from Pulse HR</em> to make it a mirror instead.</div>
{else}<form method="post" class="pull-right"><button name="syncHr" class="btn btn-default btn-sm">Synchronise from Pulse HR</button></form>{/if}
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#pe-list">Roster ({$employees|count})</a></li><li><a data-toggle="tab" href="#pe-new">Add an employee</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="pe-list">
<form method="get" class="form-inline pr-filter"><input type="hidden" name="controller" value="AdminPulsePrEmployees"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<input name="q" class="form-control input-sm" placeholder="staff number or name" value="{$filters.q|escape:'html':'UTF-8'}">
<select name="department" class="form-control input-sm"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.department}" {if $filters.department==$d.department}selected{/if}>{$d.department} ({$d.n})</option>{/foreach}</select>
<select name="status" class="form-control input-sm">
<option value="active,probation,on_leave,suspended" {if $filters.status=='active,probation,on_leave,suspended'}selected{/if}>Current staff</option>
<option value="active" {if $filters.status=='active'}selected{/if}>Active only</option>
<option value="exited" {if $filters.status=='exited'}selected{/if}>Leavers</option></select>
<button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Position</th><th>Grade</th><th>Type</th><th>Basis</th><th>Package</th><th>Bank</th><th>RSA PIN</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $employees as $e}<tr class="{if $e.on_hold}warning{elseif $e.pay_rate <= 0}danger{/if}">
<td>{$e.staff_no}</td><td><a href="{$self_url}&id_employee_pr={$e.id_pulse_pr_employee}">{$e.firstname} {$e.lastname}</a></td>
<td>{$e.department}</td><td>{$e.position}</td><td>{$e.grade}</td><td>{$e.employment_type|replace:'_':' '}</td><td>{$e.pay_basis|replace:'_':' '}</td>
<td>{if $e.pay_rate > 0}{displayPrice price=$e.pay_rate}{else}<span class="text-danger">not set</span>{/if}</td>
<td>{if $e.pay_method=='bank'}{if $e.account_no}{$e.bank_name} {$e.account_no}{else}<span class="text-danger">no account</span>{/if}{else}{$e.pay_method}{/if}</td>
<td>{if $e.rsa_pin}{$e.rsa_pin}{else}<span class="text-warning">—</span>{/if}</td>
<td>{$e.status|replace:'_':' '}{if $e.on_hold} <span class="label label-warning" title="{$e.hold_reason|escape:'html':'UTF-8'}">on hold</span>{/if}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&id_employee_pr={$e.id_pulse_pr_employee}">Open</a></td></tr>
{foreachelse}<tr><td colspan="12"><em>Nobody on the roster yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="pe-new">
<form method="post" class="form-horizontal"><div class="row">
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Staff number</label><div class="col-sm-7"><input name="staff_no" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">First name</label><div class="col-sm-7"><input name="firstname" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Last name</label><div class="col-sm-7"><input name="lastname" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Department</label><div class="col-sm-7"><input name="department" class="form-control" list="pr-depts" value="general"><datalist id="pr-depts">{foreach $departments as $d}<option value="{$d.department}">{/foreach}<option value="rooms"><option value="fnb"><option value="housekeeping"><option value="laundry"><option value="maintenance"><option value="security"><option value="admin"><option value="sales"><option value="accounts"><option value="management"></datalist></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Position</label><div class="col-sm-7"><input name="position" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Grade</label><div class="col-sm-7"><input name="grade" class="form-control" value="DEFAULT"><span class="help-block">The grade whose default pay structure this employee inherits.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Employment type</label><div class="col-sm-7"><select name="employment_type" class="form-control"><option value="permanent">permanent</option><option value="fixed_term">fixed term</option><option value="contract">contract</option><option value="casual">casual</option><option value="service">service</option><option value="intern">intern</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay basis</label><div class="col-sm-7"><select name="pay_basis" class="form-control"><option value="monthly">monthly</option><option value="daily">daily</option><option value="hourly">hourly</option><option value="per_shift">per shift</option></select><span class="help-block">Only monthly staff are in a monthly run; the rest are paid on the weekly casual cycle.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Contractual package</label><div class="col-sm-7"><input name="pay_rate" type="number" step="0.01" class="form-control" required><span class="help-block">Monthly gross, or the daily/hourly/per-shift rate.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Hire date</label><div class="col-sm-7"><input type="date" name="hire_date" class="form-control"></div></div>
</div>
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Country pack</label><div class="col-sm-7"><select name="country" class="form-control">{foreach $countries as $c}<option value="{$c.code}">{$c.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">TIN</label><div class="col-sm-7"><input name="tin" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Tax authority</label><div class="col-sm-7"><input name="tax_state" class="form-control" value="Rivers"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">RSA PIN</label><div class="col-sm-7"><input name="rsa_pin" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">PFA</label><div class="col-sm-7"><input name="pfa" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Pay method</label><div class="col-sm-7"><select name="pay_method" class="form-control"><option value="bank">bank transfer</option><option value="cash">cash</option><option value="cheque">cheque</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Bank</label><div class="col-sm-7"><select name="bank_name" class="form-control" id="pr-bank-pick"><option value="">—</option>{foreach $banks as $b}<option value="{$b.name}" data-code="{$b.nibss_code}">{$b.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Bank code</label><div class="col-sm-7"><input name="bank_code" id="pr-bank-code" class="form-control"><span class="help-block">The NIBSS institution code that goes in the payment file.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Account number</label><div class="col-sm-7"><input name="account_no" class="form-control" pattern="[0-9]{ldelim}8,20{rdelim}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Email / phone</label><div class="col-sm-7"><input name="email" class="form-control" placeholder="email for the payslip link"><input name="phone" class="form-control" placeholder="phone"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-7"><button name="saveEmployee" class="btn btn-primary btn-lg">Add employee</button></div></div>
</div></div></form>
</div>

</div></div></div>
