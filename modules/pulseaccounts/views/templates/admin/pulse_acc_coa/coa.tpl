<div class="pulse-acc"><div class="panel"><h3><i class="icon-sitemap"></i> Chart of accounts</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#c-tree">Accounts ({$accounts|count})</a></li><li><a data-toggle="tab" href="#c-edit">{if $edit}Edit {$edit.code}{else}New account{/if}</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="c-tree">
<form method="get" class="form-inline noprint" style="margin-bottom:8px">
<input type="hidden" name="controller" value="AdminPulseAccCoa"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
Movement between <input type="date" name="from" value="{$from}" class="form-control"> and <input type="date" name="to" value="{$to}" class="form-control">
<button class="btn btn-default">Show</button> <a class="btn btn-default" href="{$self_url}&amp;export=1">Export CSV</a>
</form>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Account</th><th>Type</th><th>USALI</th><th>Control</th><th class="num">Movement</th><th class="num">Balance</th><th></th></tr></thead><tbody>
{function name=accrow level=1}
  {foreach $nodes as $a}
  <tr class="lvl{$level}{if !$a.active} muted{/if}">
    <td class="acc-code">{$a.code}</td>
    <td>{$a.name|escape}{if $a.is_header} <small class="muted">(heading)</small>{/if}{if $a.is_contra} <small class="muted">(contra)</small>{/if}</td>
    <td>{$a.type}{if $a.subtype && $a.subtype != 'header'} <small class="muted">{$a.subtype}</small>{/if}</td>
    <td>{if $a.usali_dept != 'balance_sheet'}{$a.usali_dept}{/if}</td>
    <td>{if $a.is_control}<span class="badge">{$a.control_of}</span>{/if}</td>
    <td class="num">{if isset($balances[$a.code])}{displayPrice price=$balances[$a.code]['period_movement']}{/if}</td>
    <td class="num">{if isset($balances[$a.code])}{displayPrice price=$balances[$a.code]['balance']}{/if}</td>
    <td class="noprint">{if !$a.is_header}<a class="btn btn-xs btn-default" href="{$link_gl}&amp;r=gl&amp;account_code={$a.code|escape:'url'}&amp;from={$from}&amp;to={$to}">Ledger</a>{/if}
      <a class="btn btn-xs btn-default" href="{$self_url}&amp;id_account={$a.id_pulse_acc_account}">Edit</a></td>
  </tr>
  {if $a.children}{accrow nodes=$a.children level=$level+1}{/if}
  {/foreach}
{/function}
{accrow nodes=$tree level=1}
</tbody></table>
</div>

<div class="tab-pane" id="c-edit">
<form method="post" class="form-horizontal" style="max-width:760px">
<input type="hidden" name="id_pulse_acc_account" value="{if $edit}{$edit.id_pulse_acc_account}{/if}">
<div class="form-group"><label class="col-sm-3 control-label">Code</label><div class="col-sm-4"><input name="code" class="form-control" value="{if $edit}{$edit.code|escape}{/if}" required></div>
<div class="col-sm-5"><p class="form-control-static muted">1xxx assets · 2xxx liabilities · 3xxx equity · 4xxx revenue · 5–8xxx costs</p></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Name</label><div class="col-sm-9"><input name="name" class="form-control" value="{if $edit}{$edit.name|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Type</label><div class="col-sm-4"><select name="type" class="form-control">{foreach $types as $k => $v}<option value="{$k}" {if $edit && $edit.type == $k}selected{/if}>{$v}</option>{/foreach}</select></div>
<div class="col-sm-5"><input name="subtype" class="form-control" placeholder="subtype: cash, receivable, payroll…" value="{if $edit}{$edit.subtype|escape}{/if}"></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Parent</label><div class="col-sm-9"><select name="parent_code" class="form-control"><option value="">— none —</option>
{foreach $accounts as $a}{if $a.is_header}<option value="{$a.code}" {if $edit && $edit.parent_code == $a.code}selected{/if}>{$a.code} — {$a.name|escape}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3 control-label">USALI department</label><div class="col-sm-4"><select name="usali_dept" class="form-control">{foreach $depts as $k => $v}<option value="{$k}" {if $edit && $edit.usali_dept == $k}selected{/if}>{$v}</option>{/foreach}</select></div>
<label class="col-sm-2 control-label">Cash flow</label><div class="col-sm-3"><select name="cashflow" class="form-control">{foreach $flows as $k => $v}<option value="{$k}" {if $edit && $edit.cashflow == $k}selected{/if}>{$v}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Control account for</label><div class="col-sm-4"><select name="control_of" class="form-control">{foreach $controls as $k => $v}<option value="{$k}" {if $edit && $edit.control_of == $k}selected{/if}>{$v}</option>{/foreach}</select></div>
<div class="col-sm-5"><label><input type="checkbox" name="is_control" value="1" {if $edit && $edit.is_control}checked{/if}> Control</label>
<label><input type="checkbox" name="is_header" value="1" {if $edit && $edit.is_header}checked{/if}> Heading (nothing posts to it)</label>
<label><input type="checkbox" name="is_contra" value="1" {if $edit && $edit.is_contra}checked{/if}> Contra</label></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Sort / active</label><div class="col-sm-3"><input name="sort" type="number" class="form-control" value="{if $edit}{$edit.sort}{else}0{/if}"></div>
<div class="col-sm-6"><label><input type="checkbox" name="active" value="1" {if !$edit || $edit.active}checked{/if}> Active</label></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Note</label><div class="col-sm-9"><input name="note" class="form-control" value="{if $edit}{$edit.note|escape}{/if}"></div></div>
<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button name="saveAccount" class="btn btn-primary">Save account</button>
{if $edit}<a class="btn btn-default" href="{$self_url}">New account</a>
<button name="removeAccount" class="btn btn-link" onclick="return confirm('Remove this account? If it carries postings it is deactivated instead.')" formmethod="post">Remove</button>
<input type="hidden" name="id_account_s" value="{$edit.id_pulse_acc_account}">{/if}</div></div>
</form>
</div>

</div></div></div>
