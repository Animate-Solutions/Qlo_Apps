<div class="pulse-acc"><div class="panel"><h3><i class="icon-truck"></i> Payables</h3>
{if !$inv}<div class="alert alert-info">Pulse Inventory is not installed, so there are no GRNs to bill. Standalone bills, ageing and payment runs still work.</div>{/if}
<div class="row">
<div class="col-md-2"><div class="tile"><span class="k">Current</span><span class="v">{displayPrice price=$summary.b_current}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">1–30</span><span class="v">{displayPrice price=$summary.b_30}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">31–60</span><span class="v">{displayPrice price=$summary.b_60}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">61–90</span><span class="v">{displayPrice price=$summary.b_90}</span></div></div>
<div class="col-md-2"><div class="tile {if $summary.b_over > 0}bad{/if}"><span class="k">Over 90</span><span class="v">{displayPrice price=$summary.b_over}</span></div></div>
<div class="col-md-2"><div class="tile ok"><span class="k">Total owed</span><span class="v">{displayPrice price=$summary.total}</span></div></div>
</div>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#p-age">Ageing</a></li><li><a data-toggle="tab" href="#p-run">Payment run ({$due|count} due)</a></li>
<li><a data-toggle="tab" href="#p-grn">Unbilled GRNs ({$unbilled|count})</a></li><li><a data-toggle="tab" href="#p-new">New bill</a></li>
<li><a data-toggle="tab" href="#p-bills">Bills</a></li><li><a data-toggle="tab" href="#p-pay">Payments</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="p-age">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAp"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
As at <input type="date" name="as_of" value="{$as_of}" class="form-control"> <button class="btn btn-default">Show</button> <a class="btn btn-default" href="{$self_url}&amp;as_of={$as_of}&amp;export=ageing">Export CSV</a></form>
<table class="table table-condensed"><thead><tr><th>Supplier</th><th class="num">Bills</th><th class="num">Current</th><th class="num">1–30</th><th class="num">31–60</th><th class="num">61–90</th><th class="num">91–120</th><th class="num">120+</th><th class="num">Total</th></tr></thead><tbody>
{foreach $ageing as $a}<tr class="{if $a.b_over > 0}danger{elseif $a.b_120 > 0}warning{/if}"><td>{$a.supplier_name|escape}</td><td class="num">{$a.bills}</td>
<td class="num">{displayPrice price=$a.b_current}</td><td class="num">{displayPrice price=$a.b_30}</td><td class="num">{displayPrice price=$a.b_60}</td>
<td class="num">{displayPrice price=$a.b_90}</td><td class="num">{displayPrice price=$a.b_120}</td><td class="num">{displayPrice price=$a.b_over}</td>
<td class="num"><strong>{displayPrice price=$a.total}</strong></td></tr>
{foreachelse}<tr><td colspan="9"><em>Nothing owed</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="p-run">
<form method="post">
<div class="form-inline noprint" style="margin-bottom:8px">
Due by <input type="date" name="due_by_view" value="{$due_by}" class="form-control" disabled>
Pay on <input type="date" name="payment_date" value="{$business_date}" class="form-control">
<select name="method" class="form-control">{foreach ['transfer','cheque','cash','card','petty_cash'] as $m}<option value="{$m}">{$m}</option>{/foreach}</select>
from <select name="id_bank" class="form-control">{foreach $banks as $b}<option value="{$b.id_pulse_acc_bank_account}">{$b.name|escape}</option>{/foreach}</select>
<input name="reference" class="form-control" placeholder="batch reference">
<button name="payRun" class="btn btn-primary" onclick="return confirm('Post payments for every ticked bill?')">Pay ticked bills</button>
</div>
<table class="table table-condensed"><thead><tr><th><input type="checkbox" class="acc-check-all" data-target="#due-bills"></th><th>Bill</th><th>Supplier invoice</th><th>Supplier</th><th>Date</th><th>Due</th><th class="num">Overdue</th><th class="num">Balance</th></tr></thead><tbody id="due-bills">
{foreach $due as $b}<tr class="{if $b.days_overdue > 0}warning{/if}"><td><input type="checkbox" name="pay[{$b.id_pulse_acc_bill}]" value="1"></td>
<td><a href="{$self_url}&amp;id_bill={$b.id_pulse_acc_bill}">{$b.bill_no}</a></td><td>{$b.supplier_invoice_no|escape}</td><td>{$b.supplier_name|escape}</td>
<td>{$b.bill_date}</td><td>{$b.due_date}</td><td class="num">{if $b.days_overdue > 0}{$b.days_overdue}d{/if}</td><td class="num">{displayPrice price=$b.balance}</td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing falls due in this window</em></td></tr>{/foreach}
</tbody></table>
</form>
</div>

<div class="tab-pane" id="p-grn">
<p class="help-block">Goods received but not yet invoiced sit in 2120 GRN accrual. Billing them moves the accrual to trade payables, brings the input VAT in and withholds tax where the supply attracts it.</p>
<table class="table table-condensed"><thead><tr><th>GRN</th><th>Date</th><th>Supplier</th><th class="num">Value</th><th>Their invoice</th><th>Bill date</th><th>WHT</th><th></th></tr></thead><tbody>
{foreach $unbilled as $g}<tr>
<td>{$g.grn_no}</td><td>{$g.business_date}</td><td>{$g.supplier_name|escape}</td>
<td class="num">{displayPrice price=$g.total}</td>
<td><input form="grn-{$g.id_pulse_inv_grn}" name="supplier_invoice_no" class="form-control input-sm" value="{$g.invoice_no|escape}"></td>
<td><input form="grn-{$g.id_pulse_inv_grn}" type="date" name="bill_date" class="form-control input-sm" value="{$g.invoice_date|default:$g.business_date}"></td>
<td><input form="grn-{$g.id_pulse_inv_grn}" name="wht_rate_pct" type="number" step="0.1" class="form-control input-sm num" value="0" style="width:60px">
<select form="grn-{$g.id_pulse_inv_grn}" name="wht_type" class="input-sm">{foreach $wht_rules as $w}<option value="{$w.key_value}">{$w.key_value}</option>{/foreach}</select></td>
<td><button form="grn-{$g.id_pulse_inv_grn}" name="billGrn" class="btn btn-xs btn-primary">Bill it</button></td>
</tr>
{foreachelse}<tr><td colspan="8"><em>Every GRN has been billed</em></td></tr>{/foreach}
</tbody></table>
{foreach $unbilled as $g}<form method="post" id="grn-{$g.id_pulse_inv_grn}"><input type="hidden" name="id_grn" value="{$g.id_pulse_inv_grn}"></form>{/foreach}
</div>

<div class="tab-pane" id="p-new">
<form method="post" class="form-horizontal">
<div class="row"><div class="col-md-5">
<div class="form-group"><label class="col-sm-4 control-label">Supplier</label><div class="col-sm-8"><select name="id_supplier_s" class="form-control"><option value="">— not on file —</option>{foreach $suppliers as $s}<option value="{$s.id_pulse_inv_supplier}">{$s.name|escape}</option>{/foreach}</select>
<input name="supplier_name" class="form-control" placeholder="or type a name"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">TIN</label><div class="col-sm-8"><input name="tin" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Their invoice no</label><div class="col-sm-8"><input name="supplier_invoice_no" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Bill date</label><div class="col-sm-8"><input type="date" name="bill_date" value="{$business_date}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Terms (days)</label><div class="col-sm-8"><input name="terms_days" type="number" class="form-control" value="30"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">WHT</label><div class="col-sm-4"><input name="wht_rate_pct" type="number" step="0.1" class="form-control num" value="0"></div>
<div class="col-sm-4"><select name="wht_type" class="form-control">{foreach $wht_rules as $w}<option value="{$w.key_value}" title="{$w.note|escape}">{$w.key_value} ({$w.wht_rate_pct}%)</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-8"><input name="note" class="form-control"></div></div>
</div>
<div class="col-md-7">
<table class="table table-condensed"><thead><tr><th>Description</th><th>Account</th><th>Dept</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">VAT %</th></tr></thead><tbody>
{section name=b loop=5}<tr>
<td><input name="b_desc[]" class="form-control input-sm"></td>
<td><select name="b_account[]" class="form-control input-sm">{foreach $accounts as $a}{if $a.type == 'expense' || $a.subtype == 'fixed_asset'}<option value="{$a.code}">{$a.code} {$a.name|escape}</option>{/if}{/foreach}</select></td>
<td><select name="b_dept[]" class="form-control input-sm">{foreach $departments as $d}<option value="{$d.key_value}">{$d.key_value}</option>{/foreach}</select></td>
<td><input name="b_qty[]" type="number" step="0.01" value="1" class="form-control input-sm num"></td>
<td><input name="b_price[]" type="number" step="0.01" class="form-control input-sm num"></td>
<td><input name="b_tax[]" type="number" step="0.001" value="{$vat_pct}" class="form-control input-sm num"></td>
</tr>{/section}</tbody></table>
<button name="addBill" class="btn btn-primary">Post bill</button>
</div></div>
</form>
</div>

<div class="tab-pane" id="p-bills">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAp"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_supplier" class="form-control"><option value="">every supplier</option>{foreach $suppliers as $s}<option value="{$s.id_pulse_inv_supplier}" {if $smarty.get.id_supplier == $s.id_pulse_inv_supplier}selected{/if}>{$s.name|escape}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">any status</option>{foreach ['approved','part_paid','paid','disputed','cancelled'] as $s}<option value="{$s}" {if $smarty.get.status == $s}selected{/if}>{$s}</option>{/foreach}</select>
<input name="q" class="form-control" placeholder="bill or invoice number" value="{$smarty.get.q|escape}"><button class="btn btn-default">Search</button>
<a class="btn btn-default" href="{$self_url}&amp;export=bills">Export CSV</a></form>
<table class="table table-condensed"><thead><tr><th>Bill</th><th>Supplier invoice</th><th>Supplier</th><th>Date</th><th>Due</th><th class="num">Total</th><th class="num">WHT</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
{foreach $bills as $b}<tr><td><a href="{$self_url}&amp;id_bill={$b.id_pulse_acc_bill}">{$b.bill_no}</a></td><td>{$b.supplier_invoice_no|escape}</td><td>{$b.supplier_name|escape}</td>
<td>{$b.bill_date}</td><td>{$b.due_date}</td><td class="num">{displayPrice price=$b.total}</td><td class="num">{if $b.wht_amount > 0}{displayPrice price=$b.wht_amount}{/if}</td>
<td class="num">{displayPrice price=$b.balance}</td><td>{$b.status}</td></tr>
{foreachelse}<tr><td colspan="9"><em>No bills</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="p-pay">
<table class="table table-condensed"><thead><tr><th>Payment</th><th>Run</th><th>Date</th><th>Supplier</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead><tbody>
{foreach $payments as $p}<tr><td>{$p.payment_no}</td><td>{$p.run_no}</td><td>{$p.payment_date}</td><td>{$p.supplier_name|escape}</td><td>{$p.method}</td><td>{$p.reference|escape}</td>
<td class="num">{displayPrice price=$p.amount}</td><td><a class="btn btn-xs btn-default" href="{$self_url}&amp;run_no={$p.run_no|escape:'url'}">Remittance</a></td></tr>
{foreachelse}<tr><td colspan="8"><em>No payments yet</em></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
