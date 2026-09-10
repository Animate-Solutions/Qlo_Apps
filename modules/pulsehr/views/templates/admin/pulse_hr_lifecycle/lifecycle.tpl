<div class="pulse-hr"><div class="panel"><h3><i class="icon-check-sign"></i> Onboarding &amp; exit</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#c-open">Open ({$open|count})</a></li><li><a data-toggle="tab" href="#c-due">Tasks due ({$tasks_due|count})</a></li><li><a data-toggle="tab" href="#c-new">Start one</a></li><li><a data-toggle="tab" href="#c-tpl">Templates</a></li><li><a data-toggle="tab" href="#c-done">Completed</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="c-open">
<table class="table table-condensed"><thead><tr><th>Who</th><th>Department</th><th>Kind</th><th>Opened</th><th>Due</th><th>Progress</th><th></th></tr></thead><tbody>
{foreach $open as $c}<tr class="{if $c.due_on && $c.due_on < $smarty.now|date_format:'%Y-%m-%d'}danger{/if}">
<td><a href="{$employee_url}&id_employee_hr={$c.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}">{$c.employee_name}</a> <span class="muted">{$c.staff_no}</span></td>
<td>{$c.dept_name}</td><td>{$c.type}</td><td>{$c.opened_on}</td><td>{$c.due_on}</td><td>{$c.done} / {$c.tasks}</td>
<td><a class="btn btn-xs btn-primary" href="{$self_url}&id_checklist={$c.id_pulse_hr_checklist}">Open</a></td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">Nothing open.</em></td></tr>{/foreach}
</tbody></table></div>

<div class="tab-pane" id="c-due">
<table class="table table-condensed"><thead><tr><th>Due</th><th>Who</th><th>Task</th><th>Owner</th><th>Kind</th><th>Action</th></tr></thead><tbody>
{foreach $tasks_due as $t}<tr class="{if $t.due_on && $t.due_on < $smarty.now|date_format:'%Y-%m-%d'}danger{/if}"><td>{$t.due_on}</td><td>{$t.employee_name} <span class="muted">{$t.staff_no}</span></td><td>{$t.title}</td><td>{$t.owner_department}</td><td>{$t.type}</td>
<td>{if $t.action!='none'}<span class="label label-info">{$t.action|replace:'_':' '}</span>{/if}{if $t.status=='failed'} <span class="label label-danger">failed: {$t.note}</span>{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em class="muted">Nothing outstanding.</em></td></tr>{/foreach}
</tbody></table></div>

<div class="tab-pane" id="c-new"><form method="post" class="form-inline">
<select name="id_employee_hr" class="form-control">{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}">{$s.full_name} ({$s.staff_no})</option>{/foreach}</select>
<select name="type" class="form-control"><option value="onboarding">Onboarding</option><option value="offboarding">Offboarding / clearance</option></select>
<select name="id_template" class="form-control"><option value="">Best matching template</option>{foreach $templates as $t}<option value="{$t.id_pulse_hr_checklist_template}">{$t.name} ({$t.type}{if $t.department} · {$t.department}{/if})</option>{/foreach}</select>
<button name="openChecklist" class="btn btn-primary">Start checklist</button></form>
<p class="muted" style="margin-top:8px">An onboarding checklist opens by itself when you create an employee, and a clearance checklist opens by itself when you record an exit. Use this to start an extra one.</p></div>

<div class="tab-pane" id="c-tpl">
<div class="row"><div class="col-md-4">
<table class="table table-condensed"><tbody>{foreach $templates as $t}<tr><td><a href="{$self_url}&id_template={$t.id_pulse_hr_checklist_template}">{$t.name}</a></td><td>{$t.type}</td><td class="muted">{$t.department|default:'all'}</td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_template" value="{if $tpl}{$tpl.id_pulse_hr_checklist_template}{/if}">
<input name="code" class="form-control" placeholder="code" style="width:110px" value="{if $tpl}{$tpl.code}{/if}" required>
<input name="name" class="form-control" placeholder="Template name" value="{if $tpl}{$tpl.name}{/if}" required>
<select name="type" class="form-control"><option value="onboarding"{if $tpl && $tpl.type=='onboarding'} selected{/if}>onboarding</option><option value="offboarding"{if $tpl && $tpl.type=='offboarding'} selected{/if}>offboarding</option></select>
<select name="department" class="form-control"><option value="">Any department</option>{foreach $departments as $d}<option value="{$d.code}"{if $tpl && $tpl.department==$d.code} selected{/if}>{$d.name}</option>{/foreach}</select>
<button name="saveTemplate" class="btn btn-default">Save template</button></form>
</div>
<div class="col-md-8">
{if $tpl}<h4>{$tpl.name} <span class="muted">{$tpl.type}</span></h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Task</th><th>Owner</th><th>Due (days)</th><th>Action</th><th>Must</th><th></th></tr></thead><tbody>
{foreach $tpl_tasks as $t}<tr><td>{$t.sort}</td><td>{$t.title}</td><td>{$t.owner_department}</td><td>{$t.due_offset_days}</td><td>{if $t.action!='none'}<span class="label label-info">{$actions[$t.action]}</span>{else}<span class="muted">manual</span>{/if}</td><td>{if $t.mandatory}yes{/if}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_template" value="{$tpl.id_pulse_hr_checklist_template}"><input type="hidden" name="id_task_template" value="{$t.id_pulse_hr_checklist_task_template}"><button name="removeTemplateTask" class="btn btn-xs btn-link">remove</button></form></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_template" value="{$tpl.id_pulse_hr_checklist_template}"><input type="hidden" name="id_task_template">
<input name="sort" type="number" class="form-control" placeholder="#" style="width:70px">
<input name="title" class="form-control" placeholder="Task" required style="width:280px">
<select name="owner_department" class="form-control">{foreach $departments as $d}<option value="{$d.code}">{$d.name}</option>{/foreach}</select>
<input name="due_offset_days" type="number" class="form-control" placeholder="due +d" style="width:90px" value="0">
<select name="action" class="form-control">{foreach $actions as $k=>$v}<option value="{$k}">{$v}</option>{/foreach}</select>
<label class="checkbox-inline"><input type="checkbox" name="mandatory" value="1" checked> mandatory</label>
<button name="saveTemplateTask" class="btn btn-default">Add task</button></form>
{else}<p class="muted">Pick a template on the left to edit its tasks. The shipped templates cover the two that matter: standard onboarding (letter, NIN, medical, food handler, RSA, key card, POS PIN, portal PIN, uniform, locker, induction) and standard clearance (letter, revoke cards, disable POS and portal, return uniform and keys, handover, clearance signatures, exit interview, final entitlements).</p>{/if}
</div></div></div>

<div class="tab-pane" id="c-done">
<table class="table table-condensed"><tbody>{foreach $done as $c}<tr><td>{$c.employee_name}</td><td>{$c.type}</td><td>{$c.completed_on}</td><td>{$c.done} / {$c.tasks}</td><td><a class="btn btn-xs btn-default" href="{$self_url}&id_checklist={$c.id_pulse_hr_checklist}">View</a></td></tr>{foreachelse}<tr><td><em class="muted">None yet.</em></td></tr>{/foreach}</tbody></table></div>

</div></div></div>
