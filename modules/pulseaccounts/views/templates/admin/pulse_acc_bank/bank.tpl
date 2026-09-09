<div class="pulse-acc"><div class="panel"><h3><i class="icon-university"></i> Banking</h3>
<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#b-acc">Accounts ({$banks|count})</a></li><li><a data-toggle="tab" href="#b-imp">Import a statement</a></li>
<li><a data-toggle="tab" href="#b-stmt">Statements ({$statements|count})</a></li><li><a data-toggle="tab" href="#b-unrec">Unreconciled ({$unreconciled|count})</a></li>
<li><a data-toggle="tab" href="#b-petty">Petty cash</a></li>{if $fd}<li><a data-toggle="tab" href="#b-sess">Cashier floats</a></li>{/if}
</ul>
<div class="tab-content">

<div class="tab-pane active" id="b-acc">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Bank</th><th>Account no</th><th>GL</th><th class="num">Opening</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $banks as $b}<tr class="{if !$b.active}muted{/if}"><td>{$b.code}</td><td>{$b.name|escape}</td><td>{$b.type}</td><td>{$b.bank_name|escape}</td><td>{$b.account_no|escape}</td>
<td class="acc-code">{$b.account_code}</td><td class="num">{displayPrice price=$b.opening_balance}</td><td>{if $b.active}yes{else}no{/if}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_bank_edit={$b.id_pulse_acc_bank_account}">Edit</a></td></tr>{/foreach}
</tbody></table>
<h4>{if $edit}Edit {$edit.code}{else}Add a bank, cash box or card settlement account{/if}</h4>
<form method="post" class="form-horizontal" style="max-width:760px">
<input type="hidden" name="id_pulse_acc_bank_account" value="{if $edit}{$edit.id_pulse_acc_bank_account}{/if}">
<div class="form-group"><label class="col-sm-3 control-label">Code / name</label><div class="col-sm-3"><input name="code" class="form-control" value="{if $edit}{$edit.code|escape}{/if}" required></div>
<div class="col-sm-6"><input name="name" class="form-control" value="{if $edit}{$edit.name|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Type</label><div class="col-sm-4"><select name="type" class="form-control">{foreach ['bank','cash','petty_cash','mobile_money','card_settlement'] as $t}<option value="{$t}" {if $edit && $edit.type == $t}selected{/if}>{$t}</option>{/foreach}</select></div>
<label class="col-sm-2 control-label">Currency</label><div class="col-sm-3"><input name="currency" class="form-control" value="{if $edit}{$edit.currency}{else}NGN{/if}" maxlength="3"></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Bank / branch</label><div class="col-sm-5"><input name="bank_name" class="form-control" value="{if $edit}{$edit.bank_name|escape}{/if}" placeholder="Zenith Bank"></div>
<div class="col-sm-4"><input name="branch" class="form-control" value="{if $edit}{$edit.branch|escape}{/if}" placeholder="Port Harcourt"></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Account no / name</label><div class="col-sm-4"><input name="account_no" class="form-control" value="{if $edit}{$edit.account_no|escape}{/if}"></div>
<div class="col-sm-5"><input name="account_name" class="form-control" value="{if $edit}{$edit.account_name|escape}{/if}"></div></div>
<div class="form-group"><label class="col-sm-3 control-label">GL account</label><div class="col-sm-9"><select name="account_code" class="form-control" required>
{foreach $accounts as $a}{if $a.subtype == 'cash'}<option value="{$a.code}" {if $edit && $edit.account_code == $a.code}selected{/if}>{$a.code} — {$a.name|escape}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Opening balance</label><div class="col-sm-3"><input name="opening_balance" type="number" step="0.01" class="form-control num" value="{if $edit}{$edit.opening_balance}{else}0{/if}"></div>
<div class="col-sm-3"><input type="date" name="opening_date" class="form-control" value="{if $edit}{$edit.opening_date}{/if}"></div>
<div class="col-sm-3"><input name="imprest_float" type="number" step="0.01" class="form-control num" placeholder="imprest float" value="{if $edit}{$edit.imprest_float}{/if}"></div></div>
<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><label><input type="checkbox" name="active" value="1" {if !$edit || $edit.active}checked{/if}> Active</label>
<button name="saveBank" class="btn btn-primary">Save</button> {if $edit}<a class="btn btn-default" href="{$self_url}">New</a>{/if}</div></div>
</form>
</div>

<div class="tab-pane" id="b-imp">
<form method="post" enctype="multipart/form-data" class="form-horizontal" style="max-width:700px">
<div class="form-group"><label class="col-sm-3 control-label">Account</label><div class="col-sm-9"><select name="id_bank_s" class="form-control" required>{foreach $banks as $b}{if $b.active}<option value="{$b.id_pulse_acc_bank_account}">{$b.name|escape} ({$b.account_code})</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-3 control-label">Statement CSV</label><div class="col-sm-9"><input type="file" name="statement" accept=".csv,text/csv" required>
<span class="help-block">Column names are matched by alias, so a Zenith, GTBank, Access, UBA, First Bank or Moniepoint export works as downloaded. Debit/credit columns or a single signed amount column are both understood, and dd/mm/yyyy is read the Nigerian way round.</span></div></div>
<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button name="importStatement" class="btn btn-primary">Import and match</button></div></div>
</form>
<form method="post" class="form-inline"><select name="id_bank_s" class="form-control">{foreach $banks as $b}<option value="{$b.id_pulse_acc_bank_account}">{$b.name|escape}</option>{/foreach}</select>
<input name="path" class="form-control" style="width:420px" value="{$sample_csv}" placeholder="server path to a CSV">
<button name="importPath" class="btn btn-default">Import from a path</button>
<span class="help-block" style="display:inline-block;margin-left:10px">The demo statement that ships with the module is already in the box — import it to try the matcher.</span></form>
</div>

<div class="tab-pane" id="b-stmt">
<table class="table table-condensed"><thead><tr><th>Imported</th><th>Account</th><th>File</th><th>Period</th><th class="num">Rows</th><th class="num">Matched</th><th class="num">Unmatched</th><th class="num">In</th><th class="num">Out</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $statements as $s}<tr class="{if $s.rows_unmatched > 0}warning{else}success{/if}">
<td>{$s.date_add|truncate:10:''}</td><td>{$s.bank_name|escape}</td><td>{$s.filename|escape|truncate:32}</td><td>{$s.period_from} → {$s.period_to}</td>
<td class="num">{$s.rows_total}</td><td class="num">{$s.rows_matched}</td><td class="num">{$s.rows_unmatched}</td>
<td class="num">{displayPrice price=$s.total_in}</td><td class="num">{displayPrice price=$s.total_out}</td><td>{$s.status}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_statement={$s.id_pulse_acc_bank_statement}">Open</a></td></tr>
{foreachelse}<tr><td colspan="11"><em>No statements imported yet</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-unrec">
<a class="btn btn-default btn-xs noprint" href="{$self_url}&amp;export=1">Export CSV</a>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Account</th><th>Narration</th><th>Reference</th><th class="num">In</th><th class="num">Out</th><th>Statement</th><th></th></tr></thead><tbody>
{foreach $unreconciled as $l}<tr><td>{$l.txn_date}</td><td>{$l.bank_code}</td><td>{$l.description|escape|truncate:70}</td><td>{$l.reference|escape}</td>
<td class="num">{if $l.money_in > 0}{displayPrice price=$l.money_in}{/if}</td><td class="num">{if $l.money_out > 0}{displayPrice price=$l.money_out}{/if}</td>
<td class="muted">{$l.filename|escape|truncate:24}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_statement={$l.id_pulse_acc_bank_statement}&amp;match_state=unmatched">Reconcile</a></td></tr>
{foreachelse}<tr><td colspan="8"><em>Every imported statement line is matched</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="b-petty">
{if !$petty_account}<div class="alert alert-info">No petty-cash account exists yet. Add one on the Accounts tab with type <code>petty_cash</code> pointing at 1115.</div>{else}
<div class="row"><div class="col-md-3"><div class="tile"><span class="k">{$petty_account.name|escape}</span><span class="v">{displayPrice price=$petty_balance}</span><small class="muted">float {displayPrice price=$petty_account.imprest_float}</small></div></div></div>
<form method="post" class="form-inline noprint" style="margin-bottom:8px">
<input type="hidden" name="id_petty" value="{$petty_id}">
<select name="type" class="form-control"><option value="expense">expense out</option><option value="float_in">top up from bank</option><option value="reimburse">reimburse the box</option><option value="return">return to bank</option><option value="variance">cash variance</option></select>
<input name="amount" type="number" step="0.01" class="form-control num" placeholder="amount" required>
<input name="description" class="form-control" style="width:260px" placeholder="what it was for" required>
<select name="account_code" class="form-control"><option value="">default</option>{foreach $accounts as $a}{if $a.type == 'expense' || $a.subtype == 'cash'}<option value="{$a.code}">{$a.code} {$a.name|escape}</option>{/if}{/foreach}</select>
<input name="cost_centre" class="form-control" placeholder="cost centre" style="width:120px">
<input type="date" name="business_date" class="form-control" value="{$business_date}">
<input name="reference" class="form-control" placeholder="voucher no" style="width:110px">
<button name="pettyMove" class="btn btn-primary">Post</button></form>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Reference</th><th class="num">Amount</th><th class="num">Balance</th><th>By</th></tr></thead><tbody>
{foreach $petty as $p}<tr><td>{$p.business_date}</td><td>{$p.type}</td><td>{$p.description|escape}</td><td>{$p.reference|escape}</td>
<td class="num {if $p.amount < 0}neg{/if}">{displayPrice price=$p.amount}</td><td class="num">{displayPrice price=$p.balance_after}</td><td class="muted">{$p.who|escape}</td></tr>
{foreachelse}<tr><td colspan="7"><em>The imprest book is empty for this range</em></td></tr>{/foreach}
</tbody></table>
{/if}
</div>

{if $fd}<div class="tab-pane" id="b-sess">
<p class="help-block">Front-office cashier sessions and how the drawer counted. A short or over drawer can be carried into the imprest book so it lands in the ledger instead of quietly disappearing.</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Cashier</th><th class="num">Float</th><th class="num">Expected</th><th class="num">Counted</th><th class="num">Variance</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $sessions as $s}<tr class="{if $s.variance < -0.009}danger{elseif $s.variance > 0.009}warning{/if}">
<td>{$s.business_date}</td><td>{$s.cashier|escape}</td><td class="num">{displayPrice price=$s.opening_float}</td>
<td class="num">{displayPrice price=$s.expected_cash}</td><td class="num">{displayPrice price=$s.counted_cash}</td>
<td class="num {if $s.variance < 0}neg{/if}">{displayPrice price=$s.variance}</td><td>{$s.status}</td>
<td>{if $s.in_book}<span class="muted">in the book</span>{elseif $s.variance < -0.009 || $s.variance > 0.009}
<form method="post" class="inline"><input type="hidden" name="id_session" value="{$s.id_pulse_cashier_session}"><input type="hidden" name="id_petty" value="{$petty_id}">
<button name="postVariance" class="btn btn-xs btn-default">Post variance</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No cashier sessions in this range</em></td></tr>{/foreach}
</tbody></table>
</div>{/if}

</div></div></div>
