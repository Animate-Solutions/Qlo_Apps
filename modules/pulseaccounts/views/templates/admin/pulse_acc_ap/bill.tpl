<div class="pulse-acc">
{if !$bill}<div class="alert alert-danger">That bill does not exist. <a href="{$self_url}">Back to payables</a></div>{else}
<div class="panel"><h3><i class="icon-file-text-alt"></i> {$bill.bill_no} — {$bill.supplier_name|escape}
<span class="badge" style="background:{if $bill.status == 'paid'}#27ae60{elseif $bill.status == 'disputed'}#c0392b{elseif $bill.balance > 0}#f39c12{else}#7f8c8d{/if}">{$bill.status}</span></h3>
<table class="table table-condensed" style="max-width:760px"><tbody>
<tr><th>Supplier invoice</th><td>{$bill.supplier_invoice_no|escape|default:'—'}</td><th>TIN</th><td>{$bill.tin|escape}</td></tr>
<tr><th>Bill date</th><td>{$bill.bill_date}</td><th>Due</th><td>{$bill.due_date} ({$bill.terms_days} days)</td></tr>
<tr><th>From GRN</th><td>{$bill.grn_no|default:'—'}</td><th>Period</th><td>{$bill.period}</td></tr>
</tbody></table>
<table class="table table-condensed"><thead><tr><th>Description</th><th>Account</th><th>Dept</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">VAT</th><th class="num">Total</th></tr></thead><tbody>
{foreach $bill.lines as $l}<tr><td>{$l.description|escape}{if $l.is_accrual_clear} <small class="muted">(clears the GRN accrual)</small>{/if}</td>
<td class="acc-code">{$l.account_code}</td><td>{$l.department}</td><td class="num">{$l.qty}</td><td class="num">{displayPrice price=$l.unit_price}</td>
<td class="num">{displayPrice price=$l.tax_amount}</td><td class="num">{displayPrice price=$l.line_total}</td></tr>{/foreach}
</tbody><tfoot>
<tr><th colspan="6" class="num">Net</th><td class="num">{displayPrice price=$bill.subtotal}</td></tr>
<tr><th colspan="6" class="num">Input VAT</th><td class="num">{displayPrice price=$bill.vat_amount}</td></tr>
<tr><th colspan="6" class="num">Invoice total</th><td class="num">{displayPrice price=$bill.total}</td></tr>
{if $bill.wht_amount > 0}<tr><th colspan="6" class="num">WHT withheld at {$bill.wht_rate_pct}%</th><td class="num">-{displayPrice price=$bill.wht_amount}</td></tr>{/if}
<tr><th colspan="6" class="num">Paid</th><td class="num">{displayPrice price=$bill.paid}</td></tr>
<tr class="pl-total"><th colspan="6" class="num">Balance payable</th><td class="num">{displayPrice price=$bill.balance}</td></tr>
</tfoot></table>
{if $bill.payments}<h4>Payments</h4><table class="table table-condensed" style="max-width:700px"><thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead><tbody>
{foreach $bill.payments as $p}<tr><td><a href="{$self_url}&amp;run_no={$p.payment_no|escape:'url'}">{$p.payment_no}</a></td><td>{$p.payment_date}</td><td>{$p.method}</td><td>{$p.reference|escape}</td><td class="num">{displayPrice price=$p.amount}</td></tr>{/foreach}
</tbody></table>{/if}
{if $bill.note}<p><em>{$bill.note|escape}</em></p>{/if}
<div class="noprint">
<form method="post" class="form-inline"><input type="hidden" name="id_bill_s" value="{$bill.id_pulse_acc_bill}">
{if $bill.balance > 0}<input name="note" class="form-control input-sm" placeholder="dispute note">
<button name="disputeBill" class="btn btn-xs btn-warning">Mark disputed</button>{/if}
{if $bill.paid <= 0}<input name="reason" class="form-control input-sm" placeholder="cancellation reason">
<button name="cancelBill" class="btn btn-xs btn-link" onclick="return confirm('Cancel this bill and reverse its journal?')">Cancel</button>{/if}
</form>
<a class="btn btn-default" href="javascript:window.print()">Print</a> <a class="btn btn-default" href="{$self_url}">Back</a>
</div>
</div>
{/if}</div>
