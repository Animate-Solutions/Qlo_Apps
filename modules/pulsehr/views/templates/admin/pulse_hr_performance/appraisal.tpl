<div class="pulse-hr"><div class="panel">
<h3>{$a.employee_name} — {$a.cycle_name} <small class="muted">{$a.period_from} to {$a.period_to}</small>
<span class="label label-default">{$a.status|replace:'_':' '}</span> <span class="label label-info">rating {$a.overall_rating}</span>
<a class="btn btn-default btn-sm pull-right" href="{$self_url}">Back</a>
<a class="btn btn-default btn-sm pull-right" href="javascript:window.print()" style="margin-right:6px"><i class="icon-print"></i> Print</a></h3>

<table class="table table-condensed"><thead><tr><th style="width:30px">#</th><th>Objective</th><th>Target</th><th>Result</th><th class="text-right">Weight</th><th class="text-right">Rating (1–5)</th><th>Comment</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $a.objectives as $o}<tr>
<td>{$o.sort}</td>
<td><form method="post" class="form-inline"><input type="hidden" name="id_appraisal" value="{$a.id_pulse_hr_appraisal}"><input type="hidden" name="id_objective" value="{$o.id_pulse_hr_appraisal_objective}">
<input name="sort" type="hidden" value="{$o.sort}"><input name="title" class="input-sm" value="{$o.title|escape:'html':'UTF-8'}" style="width:100%"></td>
<td><input name="target" class="input-sm" value="{$o.target|escape:'html':'UTF-8'}"></td>
<td><input name="result" class="input-sm" value="{$o.result|escape:'html':'UTF-8'}"></td>
<td class="text-right"><input name="weight" type="number" step="0.5" class="input-sm" value="{$o.weight}" style="width:70px"></td>
<td class="text-right"><input name="rating" type="number" step="0.5" min="0" max="5" class="input-sm" value="{$o.rating}" style="width:70px"></td>
<td><input name="comment" class="input-sm" value="{$o.comment|escape:'html':'UTF-8'}"></td>
<td class="hr-actions"><button name="saveObjective" class="btn btn-xs btn-default">Save</button></form>
<form method="post" class="inline"><input type="hidden" name="id_appraisal" value="{$a.id_pulse_hr_appraisal}"><input type="hidden" name="id_objective" value="{$o.id_pulse_hr_appraisal_objective}"><button name="removeObjective" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em class="muted">No objectives set. Add the three or four things this job is actually judged on.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_appraisal" value="{$a.id_pulse_hr_appraisal}"><input type="hidden" name="id_objective">
<input name="sort" type="number" class="form-control" placeholder="#" style="width:70px">
<input name="title" class="form-control" placeholder="Objective" required style="width:260px">
<input name="target" class="form-control" placeholder="Target" style="width:160px">
<input name="weight" type="number" step="0.5" class="form-control" placeholder="weight" style="width:90px">
<input name="rating" type="number" step="0.5" min="0" max="5" class="form-control" placeholder="rating" style="width:90px">
<button name="saveObjective" class="btn btn-default">Add objective</button></form>
<p class="muted">The overall rating is the weighted average. Weights that do not add to 100 are normalised rather than left quietly wrong.</p>

<form method="post" style="margin-top:14px"><input type="hidden" name="id_appraisal" value="{$a.id_pulse_hr_appraisal}">
<div class="row"><div class="col-md-6">
<label>Reviewer's comment</label><textarea name="reviewer_comment" class="form-control" rows="4">{$a.reviewer_comment|escape:'html':'UTF-8'}</textarea>
<label>Reviewer</label><select name="id_reviewer" class="form-control"><option value="">—</option>{foreach $staff as $s}<option value="{$s.id_pulse_hr_employee}"{if $a.id_reviewer==$s.id_pulse_hr_employee} selected{/if}>{$s.full_name}</option>{/foreach}</select>
<label>Recommendation</label><select name="recommendation" class="form-control">{foreach ['none','confirm','promote','increment','training','pip','exit'] as $r}<option value="{$r}"{if $a.recommendation==$r} selected{/if}>{$r|replace:'_':' '}</option>{/foreach}</select>
</div><div class="col-md-6">
<label>Employee's comment</label><textarea name="employee_comment" class="form-control" rows="4">{$a.employee_comment|escape:'html':'UTF-8'}</textarea>
<label>Status</label><select name="status" class="form-control">{foreach ['draft','self_review','reviewer','signed','closed'] as $s}<option value="{$s}"{if $a.status==$s} selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select>
<label><input type="checkbox" name="sign_reviewer" value="1"> reviewer signs now{if $a.reviewer_signed_at} <span class="muted">(signed {$a.reviewer_signed_at})</span>{/if}</label>
<label><input type="checkbox" name="sign_employee" value="1"> employee signs now{if $a.employee_signed_at} <span class="muted">(signed {$a.employee_signed_at})</span>{/if}</label>
</div></div>
<button name="saveAppraisal" class="btn btn-primary" style="margin-top:10px">Save appraisal</button>
</form>
</div></div>
