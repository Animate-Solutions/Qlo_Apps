<div class="pulse-acc"><div class="panel"><h3><i class="icon-certificate"></i> Withholding tax certificate {$c.cert.cert_no}</h3>
<div class="letter">
<h4 style="text-align:center">WITHHOLDING TAX CREDIT NOTE</h4>
<p style="text-align:center" class="muted">Federal Inland Revenue Service — deduction at source</p>
<table class="table table-condensed"><tbody>
<tr><th style="width:34%">Certificate number</th><td>{$c.cert.cert_no}</td></tr>
<tr><th>Deducting company</th><td>{$c.hotel|escape} &middot; TIN {$c.hotel_tin|escape}<br>{$c.hotel_address|escape}</td></tr>
<tr><th>{if $c.cert.direction == 'deducted'}Payee (tax deducted from){else}Customer (tax withheld by){/if}</th><td>{$c.cert.party_name|escape}{if $c.cert.tin} &middot; TIN {$c.cert.tin|escape}{/if}</td></tr>
<tr><th>Nature of payment</th><td>{if isset($c.types[$c.cert.wht_type])}{$c.types[$c.cert.wht_type]}{else}{$c.cert.wht_type}{/if}</td></tr>
<tr><th>Transaction date</th><td>{$c.cert.business_date} (period {$c.cert.period})</td></tr>
<tr><th>Document reference</th><td>{$c.cert.doc_no|escape}</td></tr>
<tr><th>Gross amount</th><td class="num">{displayPrice price=$c.cert.base_amount}</td></tr>
<tr><th>Rate applied</th><td>{$c.cert.rate_pct}%</td></tr>
<tr class="pl-total"><th>Tax withheld</th><td class="num">{displayPrice price=$c.cert.amount}</td></tr>
<tr><th>Remitted</th><td>{if $c.cert.remitted}Yes — {$c.cert.remit_date} reference {$c.cert.remit_ref|escape}{else}Not yet remitted{/if}</td></tr>
</tbody></table>
<p>This certificate is issued as evidence of tax deducted at source and may be presented to the relevant tax authority as a credit against the payee's income tax liability.</p>
<p style="margin-top:40px">_______________________________<br>For {$c.hotel|escape}</p>
</div>
<div class="noprint" style="margin-top:12px"><a class="btn btn-default" href="javascript:window.print()">Print</a> <a class="btn btn-default" href="{$self_url}">Back to tax</a></div>
</div></div>
