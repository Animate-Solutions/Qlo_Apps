<div class="pulse-hr"><div class="panel">
<h3>{$c.type|ucfirst} — {$c.employee_name} <small class="muted">{$c.staff_no} · {$c.dept_name}</small>
<span class="label label-{if $c.status=='completed'}success{else}warning{/if}">{$c.status}</span>
<a class="btn btn-default btn-sm pull-right" href="{$self_url}">Back</a>
<a class="btn btn-default btn-sm pull-right" href="{$employee_url}&id_employee_hr={$c.id_pulse_hr_employee}&token={$smarty.get.token|escape:'html':'UTF-8'}" style="margin-right:6px">Employee file</a></h3>
<p class="muted">Opened {$c.opened_on} · due {$c.due_on}{if $c.completed_on} · completed {$c.completed_on}{/if}</p>

<table class="table table-condensed"><thead><tr><th style="width:30px">#</th><th>Task</th><th>Owner</th><th>Due</th><th>Action</th><th>Status</th><th class="hr-actions" style="width:340px">Do it</th></tr></thead><tbody>
{foreach $c.tasks as $t}
<tr class="{if $t.status=='done'}success{elseif $t.status=='failed'}danger{elseif $t.due_on && $t.due_on < $smarty.now|date_format:'%Y-%m-%d' && $t.status=='pending'}warning{/if}">
<td>{$t.sort}</td><td>{$t.title}{if $t.mandatory} <span class="muted">*</span>{/if}</td><td>{$t.owner_department}</td><td>{$t.due_on}</td>
<td>{if $t.action!='none'}<span class="label label-info">{$t.action|replace:'_':' '}</span>{else}<span class="muted">manual</span>{/if}</td>
<td>{$t.status}{if $t.action_ref} <span class="muted flag">{$t.action_ref}</span>{/if}{if $t.note}<div class="muted">{$t.note}</div>{/if}{if $t.done_at}<div class="muted">{$t.done_at|date_format:"%d/%m %H:%M"}</div>{/if}</td>
<td class="hr-actions">{if $t.status=='pending' || $t.status=='failed'}
<form method="post" class="form-inline"><input type="hidden" name="id_task" value="{$t.id_pulse_hr_checklist_task}"><input type="hidden" name="id_checklist" value="{$c.id_pulse_hr_checklist}">
{if $t.action=='keycard_issue'}
  {if $kc}<select name="id_group" class="input-sm"><option value="">access group…</option>{foreach $kc_groups as $g}<option value="{$g.id_pulse_kc_staff_group}">{$g.name} ({$g.department})</option>{/foreach}</select>
  {else}<span class="muted">Key Card module absent — cut the card on the encoder and record the number:</span> <input name="task_ref" class="input-sm" placeholder="card no">{/if}
{elseif $t.action=='pos_pin'}
  {if $pos}<input name="task_pin" class="input-sm" placeholder="POS PIN" inputmode="numeric" style="width:90px"><select name="pos_role" class="input-sm"><option value="waiter">waiter</option><option value="cashier">cashier</option><option value="bartender">bartender</option><option value="supervisor">supervisor</option><option value="manager">manager</option><option value="kitchen">kitchen</option></select>
  {else}<span class="muted">POS module absent</span>{/if}
{elseif $t.action=='ess_pin'}
  <input name="task_pin" class="input-sm" placeholder="portal PIN" inputmode="numeric" style="width:100px">
{/if}
<input name="task_note" class="input-sm" placeholder="note / reference" style="width:140px">
<button name="doTask" class="btn btn-xs btn-success">Done</button>
<button name="skipTask" class="btn btn-xs btn-default" data-hr-confirm="Mark this task not applicable?">N/A</button>
</form>{/if}</td></tr>
{/foreach}
</tbody></table>
<p class="muted">A task with an action does the work through the module that owns it. If that module is not installed, or the encoder is down, the task fails with the reason instead of pretending it worked — tick it manually once you have done it by hand.</p>
</div></div>
