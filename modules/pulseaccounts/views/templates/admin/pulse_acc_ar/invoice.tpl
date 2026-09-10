<div class="pulse-acc">
{if !$inv}<div class="alert alert-danger">That invoice does not exist. <a href="{$self_url}">Back to receivables</a></div>{else}
<div class="panel"><h3><i class="icon-file-text"></i> {$inv.invoice_no} — {$inv.company_name|escape}
<span class="badge" style="background:{if $inv.status == 'paid'}#27ae60{elseif $inv.status == 'written_off'}#7f8c8d{elseif $inv.balance > 0}#f39c12{else}#3498db{/if}">{$inv.status}</span></h3>

<div class="letter">
<div class="row"><div class="col-sm-6"><h4>{$hotel.name|escape}</h4><p>{$hotel.address|nl2br}<br>TIN {$hotel.tin|escape}<br>{$hotel.email|escape}</p></div>
<div class="col-sm-6"><h4>{if $inv.type == 'credit_note'}CREDIT NOTE{else}TAX INVOICE{/if} {$inv.invoice_no}</h4>
<p><strong>{$inv.company_name|escape}</strong><br>{$inv.address|escape}<br>{if $inv.tin}TIN {$inv.tin|escape}<br>{/if}{$inv.email|escape}</p>
<p>Date {$inv.invoice_date} &middot; Due {$inv.due_date} ({$inv.terms_days} days)</p></div></div>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Description</th><th>Dept</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">VAT %</th><th class="num">Amount</th></tr></thead><tbody>
{foreach $inv.lines as $l}<tr><td>{$l.business_date}</td><td>{$l.description|escape}</td><td>{$l.department}</td><td class="num">{$l.qty}</td>
<td class="num">{displayPrice price=$l.unit_price}</td><td class="num">{$l.tax_rate}</td><td class="num">{displayPrice price=$l.line_total}</td></tr>{/foreach}
</tbody><tfoot>
<tr><th colspan="6" class="num">Subtotal</th><td class="num">{displayPrice price=$inv.subtotal}</td></tr>
<tr><th colspan="6" class="num">VAT and consumption tax</th><td class="num">{displayPrice price=$inv.tax_amount}</td></tr>
<tr class="pl-total"><th colspan="6" class="num">Total</th><td class="num">{displayPrice price=$inv.total}</td></tr>
<tr><th colspan="6" class="num">Allocated</th><td class="num">{displayPrice price=$inv.allocated}</td></tr>
<tr class="pl-total"><th colspan="6" class="num">Balance due</th><td class="num">{displayPrice price=$inv.balance}</td></tr>
</tfoot></table>
{if $inv.note}<p><em>{$inv.note|escape}</em></p>{/if}
<p class="muted">Where withholding tax is deducted, please send the credit note so the account can be cleared in full.</p>
</div>

{if $inv.allocations}<h4>Payments and credits applied</h4>
<table class="table table-condensed" style="max-width:700px"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="num">Amount</th><th class="noprint"></th></tr></thead><tbody>
{foreach $inv.allocations as $a}<tr><td>{$a.receipt_date|default:$a.date_add|truncate:10:''}</td><td>{$a.source_type}</td><td>{$a.receipt_no|default:$a.credit_no}</td><td class="num">{displayPrice price=$a.amount}</td>
<td class="noprint"><form method="post" class="inline"><input type="hidden" name="id_allocation" value="{$a.id_pulse_acc_allocation}"><button name="unallocate" class="btn btn-xs btn-link" onclick="return confirm('Remove this allocation?')">unapply</button></form></td></tr>{/foreach}
</tbody></table>{/if}

{if $inv.einvoice}<h4>FIRS e-invoicing</h4>
<table class="table table-condensed" style="max-width:700px"><tbody>
<tr><th>Status</th><td>{$inv.einvoice.status}{if $inv.einvoice.last_error} — <span class="muted">{$inv.einvoice.last_error|escape}</span>{/if}</td></tr>
<tr><th>IRN</th><td>{$inv.einvoice.irn|escape}</td></tr>
{if $inv.einvoice.accepted_at}<tr><th>Accepted</th><td>{$inv.einvoice.accepted_at}</td></tr>{/if}
</tbody></table>{/if}

<div class="noprint" style="margin-top:12px">
<a class="btn btn-default" href="javascript:window.print()">Print</a>
<a class="btn btn-default" href="{$self_url}">Back</a>
{if $inv.type == 'invoice' && $inv.balance > 0}
<form method="post" class="form-inline" style="display:inline-block;margin-left:12px"><input type="hidden" name="id_invoice_s" value="{$inv.id_pulse_acc_invoice}">
<input name="amount" type="number" step="0.01" class="form-control input-sm num" placeholder="credit amount" max="{$inv.balance}">
<input name="reason" class="form-control input-sm" placeholder="reason" required>
<input type="date" name="cn_date" class="form-control input-sm">
<button name="creditNote" class="btn btn-xs btn-warning">Credit note</button>
<button name="writeOff" class="btn btn-xs btn-link" onclick="return confirm('Write the remaining balance off to bad debts?')">Write off</button>
</form>{/if}
<form method="post" class="inline"><input type="hidden" name="id_invoice_s" value="{$inv.id_pulse_acc_invoice}"><button name="queueEinvoice" class="btn btn-xs btn-default">Queue for FIRS</button></form>
</div>
</div>
{/if}</div>
