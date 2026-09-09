<div class="pulse-pr"><div class="panel"><h3><i class="icon-list"></i> Pay elements &amp; grade structures</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#el-list">Elements ({$elements|count})</a></li><li><a data-toggle="tab" href="#el-edit">Add / edit an element</a></li><li><a data-toggle="tab" href="#el-grade">Grade structures</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="el-list">
<p class="text-muted">The named bases every element, relief and contribution refers to are defined once and reused, so a jurisdiction change does not ripple through the elements: <strong>{foreach $bases as $b}{$b}{if !$b@last} · {/if}{/foreach}</strong>.</p>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Calculation</th><th>Taxable</th><th>Pensionable</th><th>NSITF</th><th>In basic</th><th>Prorated</th><th>Recurring</th><th>GL</th><th>Seq</th><th></th></tr></thead><tbody>
{foreach $elements as $el}<tr class="{if !$el.active}text-muted{/if}">
<td><strong>{$el.code}</strong></td><td>{$el.name}</td><td>{$el.type}</td>
<td>{$el.calc}{if $el.percent_of} of {$el.percent_of}{/if}{if $el.statutory_code} → {$el.statutory_code}{/if}</td>
<td>{if $el.taxable}✓{/if}</td><td>{if $el.pensionable}✓{/if}</td><td>{if $el.nsitfable}✓{/if}</td><td>{if $el.in_basic}✓{/if}</td>
<td>{if $el.proratable}✓{/if}</td><td>{if $el.recurring}✓{else}<small>one-off</small>{/if}</td><td>{$el.gl_account}</td><td>{$el.sequence}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&code={$el.code}#el-edit">Edit</a></td></tr>{/foreach}
</tbody></table>
<p class="text-muted"><strong>Recurring</strong> matters for tax: a recurring element is projected across the whole annualisation window, a one-off (bonus, service charge, back pay) is added once and never multiplied by twelve.</p>
</div>

<div class="tab-pane {if $edit}active{/if}" id="el-edit">
<form method="post" class="form-horizontal"><div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Code</label><div class="col-sm-7"><input name="code" class="form-control" value="{if $edit}{$edit.code}{/if}" {if $edit}readonly{/if} required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Name</label><div class="col-sm-7"><input name="name" class="form-control" value="{if $edit}{$edit.name|escape:'html':'UTF-8'}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Type</label><div class="col-sm-7"><select name="type" class="form-control">{foreach ['earning','deduction','employer','information'] as $t}<option value="{$t}" {if $edit && $edit.type==$t}selected{/if}>{$t}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Calculation</label><div class="col-sm-7"><select name="calc" class="form-control">{foreach ['fixed','percent','rate_units','formula','statutory'] as $t}<option value="{$t}" {if $edit && $edit.calc==$t}selected{/if}>{$t|replace:'_':' × '}</option>{/foreach}</select>
<span class="help-block">The assignment on an employee or a grade overrides this: a percentage on the line wins, then a fixed amount on the line.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Percent of</label><div class="col-sm-7"><select name="percent_of" class="form-control"><option value="">—</option>{foreach $bases as $b}<option value="{$b}" {if $edit && $edit.percent_of==$b}selected{/if}>{$b}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Default value</label><div class="col-sm-7"><input name="default_value" type="number" step="0.000001" class="form-control" value="{if $edit}{$edit.default_value|floatval}{/if}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Formula</label><div class="col-sm-7"><input name="formula" class="form-control" value="{if $edit}{$edit.formula|escape:'html':'UTF-8'}{/if}" placeholder="(BASIC + HOUSING) * 0.05">
<span class="help-block">Names, numbers, + − × ÷ and parentheses only. No functions, and never evaluated as PHP.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Statutory scheme</label><div class="col-sm-7"><select name="statutory_code" class="form-control"><option value="">—</option><option value="PAYE" {if $edit && $edit.statutory_code=='PAYE'}selected{/if}>PAYE</option>
{foreach $contributions as $c}<option value="{$c.code}" {if $edit && $edit.statutory_code==$c.code}selected{/if}>{$c.code} — {$c.name}</option>{/foreach}
<option value="LOAN" {if $edit && $edit.statutory_code=='LOAN'}selected{/if}>LOAN — automatic recovery</option>
<option value="ARREARS" {if $edit && $edit.statutory_code=='ARREARS'}selected{/if}>ARREARS — parked shortfall recovery</option></select></div></div>
</div><div class="col-md-6">
{foreach ['taxable'=>'Taxable','pensionable'=>'Pensionable (part of the BHT base)','nsitfable'=>'Counts towards NSITF','in_basic'=>'Counts as basic salary','proratable'=>'Prorated for a part period','recurring'=>'Recurring (projected across the tax year)','show_on_payslip'=>'Show on the payslip','active'=>'Active'] as $k => $lbl}
<div class="form-group"><label class="col-sm-6 control-label">{$lbl}</label><div class="col-sm-5"><input type="checkbox" name="{$k}" value="1" {if !$edit || $edit[$k]}checked{/if}></div></div>
{/foreach}
<div class="form-group"><label class="col-sm-6 control-label">GL account</label><div class="col-sm-5"><input name="gl_account" class="form-control" value="{if $edit}{$edit.gl_account}{/if}"></div></div>
<div class="form-group"><label class="col-sm-6 control-label">Department override</label><div class="col-sm-5"><input name="department" class="form-control" value="{if $edit}{$edit.department}{/if}"></div></div>
<div class="form-group"><label class="col-sm-6 control-label">Sequence</label><div class="col-sm-5"><input name="sequence" type="number" class="form-control" value="{if $edit}{$edit.sequence}{else}100{/if}"></div></div>
<div class="form-group"><label class="col-sm-6 control-label">Note</label><div class="col-sm-5"><input name="note" class="form-control" value="{if $edit}{$edit.note|escape:'html':'UTF-8'}{/if}"></div></div>
<div class="form-group"><div class="col-sm-offset-6 col-sm-5"><button name="saveElement" class="btn btn-primary btn-lg">Save element</button></div></div>
</div></div></form>
</div>

<div class="tab-pane" id="el-grade">
<form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulsePrElements"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<label>Grade</label> <input name="grade" class="form-control input-sm" value="{$grade|escape:'html':'UTF-8'}" list="pr-grades">
<datalist id="pr-grades">{foreach $grades as $g}<option value="{$g.grade}">{/foreach}</datalist>
<button class="btn btn-default btn-sm">Show</button></form>
<form method="post" class="form-inline"><input type="hidden" name="grade" value="{$grade|escape:'html':'UTF-8'}"><button name="checkGrade" class="btn btn-default btn-sm">Check this grade adds up</button></form>
<table class="table table-condensed"><thead><tr><th>Element</th><th>Type</th><th>Percent of package</th><th>Fixed amount</th><th>Units</th><th>From</th><th>To</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $grade_rows as $g}<tr><td><strong>{$g.element_code}</strong> {$g.name}</td><td>{$g.type}</td><td>{if $g.percent}{$g.percent|floatval}%{/if}</td><td>{if $g.amount}{displayPrice price=$g.amount}{/if}</td>
<td>{if $g.units}{$g.units|floatval}{/if}</td><td>{$g.effective_from}</td><td>{$g.effective_to|default:'—'}</td><td><small>{$g.note}</small></td>
<td><form method="post" class="inline"><input type="hidden" name="grade" value="{$grade|escape:'html':'UTF-8'}"><input type="hidden" name="id_structure" value="{$g.id_pulse_pr_employee_element}"><button name="deleteGradeLine" class="btn btn-xs btn-link" onclick="return confirm('Remove this line?')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em>This grade has no default structure. Employees on it fall back to the DEFAULT grade.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="grade" value="{$grade|escape:'html':'UTF-8'}"><input type="hidden" name="grade_code" value="{$grade|escape:'html':'UTF-8'}">
<select name="element_code" class="form-control input-sm">{foreach $elements as $el}{if $el.active}<option value="{$el.code}">{$el.code} — {$el.name}</option>{/if}{/foreach}</select>
<input name="percent" type="number" step="0.0001" class="form-control input-sm" placeholder="% of package" style="width:130px">
<input name="amount" type="number" step="0.01" class="form-control input-sm" placeholder="fixed amount" style="width:130px">
<input name="units" type="number" step="0.001" class="form-control input-sm" placeholder="units" style="width:90px">
<input type="date" name="effective_from" class="form-control input-sm" value="{$smarty.now|date_format:'%Y-%m-01'}">
<input type="date" name="effective_to" class="form-control input-sm">
<input name="note" class="form-control input-sm" placeholder="note">
<button name="saveGradeLine" class="btn btn-primary btn-sm">Add line</button></form>
<p class="text-muted">The shipped DEFAULT grade is the Nigerian hotel convention: basic 40%, housing 25%, transport 15%, meal 10%, utility 10% of the contractual package. Basic + housing + transport is the pension base, so 80% of the package is pensionable and the meal and utility allowances are not.</p>
</div>

</div></div></div>
