<div class="pulse-acc"><div class="panel"><h3><i class="icon-money"></i> Remittance advice — run {$rem.run_no}</h3>
<div class="letter">
<h4>{$rem.hotel|escape}</h4><p>{$rem.address|nl2br}<br>TIN {$rem.tin|escape}</p>
<p>Payment date {$rem.date} &middot; Run {$rem.run_no}</p>
{foreach $rem.suppliers as $s}
<h4>{$s.payment.supplier_name|escape} — {$s.payment.payment_no}</h4>
<p>{$s.payment.method}{if $s.payment.reference} &middot; reference {$s.payment.reference|escape}{/if}</p>
<table class="table table-condensed"><thead><tr><th>Our ref</th><th>Your invoice</th><th>Date</th><th>Due</th><th class="num">Invoice total</th><th class="num">WHT</th><th class="num">Paid</th></tr></thead><tbody>
{foreach $s.bills as $b}<tr><td>{$b.bill_no}</td><td>{$b.supplier_invoice_no|escape}</td><td>{$b.bill_date}</td><td>{$b.due_date}</td>
<td class="num">{displayPrice price=$b.total}</td><td class="num">{if $b.wht_amount > 0}{displayPrice price=$b.wht_amount}{/if}</td><td class="num">{displayPrice price=$b.amount}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="6">Paid to {$s.payment.supplier_name|escape}</td><td class="num">{displayPrice price=$s.payment.amount}</td></tr>
</tbody></table>
{if $s.wht > 0}<p class="muted">Withholding tax of {displayPrice price=$s.wht} has been deducted and will be remitted to the Federal Inland Revenue Service. Your WHT credit note follows.</p>{/if}
{/foreach}
<h4>Total paid on this run: {displayPrice price=$rem.total}</h4>
</div>
<div class="noprint" style="margin-top:12px"><a class="btn btn-default" href="javascript:window.print()">Print</a> <a class="btn btn-default" href="{$self_url}">Back to payables</a></div>
</div></div>
