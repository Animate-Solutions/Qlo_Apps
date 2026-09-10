<div class="pulse-pay"><div class="panel"><h3><i class="icon-credit-card"></i> {$t.reference} <small>{$t.gateway} · {$t.type} · {$t.channel}</small></h3>
<a class="btn btn-default btn-xs noprint" href="{$self_url}">&larr; All transactions</a>
<div class="row"><div class="col-md-6"><table class="table table-condensed">
<tr><th>State</th><td><b>{$t.state}</b>{if $t.failed_reason} — <span class="text-danger">{$t.failed_reason}</span>{/if}</td></tr>
<tr><th>Amount</th><td>{displayPrice price=$t.amount}{if $t.surcharge > 0} <small class="text-muted">(incl. surcharge {displayPrice price=$t.surcharge} + VAT {displayPrice price=$t.surcharge_tax})</small>{/if}</td></tr>
<tr><th>Captured / refunded</th><td>{displayPrice price=$t.amount_captured} / {displayPrice price=$t.amount_refunded}</td></tr>
<tr><th>Fee / net</th><td>{displayPrice price=$t.fee} / {displayPrice price=$t.net}</td></tr>
<tr><th>Gateway reference</th><td>{$t.gateway_ref|default:'—'}</td></tr>
<tr><th>RRN / auth code</th><td>{$t.rrn|default:'—'} / {$t.auth_code|default:'—'}</td></tr>
<tr><th>Card</th><td>{if $t.card_last4}{$t.card_brand} ****{$t.card_last4}{if $t.bank} · {$t.bank}{/if}{else}—{/if}</td></tr>
<tr><th>Stored token</th><td>{if $t.auth_token_masked}{$t.auth_token_masked}{else}none{/if}</td></tr>
<tr><th>Hold</th><td>{$t.hold_type}{if $t.expires_at} · expires {$t.expires_at}{/if}</td></tr>
</table></div>
<div class="col-md-6"><table class="table table-condensed">
<tr><th>Guest</th><td>{$t.customer_name} {if $t.customer_email}&lt;{$t.customer_email}&gt;{/if}</td></tr>
<tr><th>Booking / folio / check</th><td>{$t.id_htl_booking|default:'—'} / {$t.id_pulse_folio|default:'—'} / {$t.id_pulse_pos_check|default:'—'}</td></tr>
<tr><th>Description</th><td>{$t.description}</td></tr>
<tr><th>Business date</th><td>{$t.business_date}</td></tr>
<tr><th>Created / updated</th><td>{$t.date_add} / {$t.date_upd}</td></tr>
<tr><th>Idempotency key</th><td><small>{$t.idempotency_key}</small></td></tr>
<tr><th>Ledger postings</th><td>{foreach $postings as $p}{$p.purpose} → {$p.target} #{$p.id_target} line {$p.id_line} ({displayPrice price=$p.amount})<br>{foreachelse}<em>not posted</em>{/foreach}</td></tr>
</table></div></div>

<div class="noprint"><h4>Actions</h4><div class="row">
<div class="col-md-3"><form method="post"><input type="hidden" name="reference" value="{$t.reference}">
<div class="input-group"><input name="amount" type="number" step="0.01" class="form-control input-sm" value="{$t.amount-$t.amount_captured}"><span class="input-group-btn"><button name="doCapture" class="btn btn-sm btn-primary">Capture</button></span></div>
<input name="rrn" class="form-control input-sm" placeholder="RRN (manual / terminal)"><input name="auth_code" class="form-control input-sm" placeholder="Auth code"></form></div>
<div class="col-md-3"><form method="post"><input type="hidden" name="reference" value="{$t.reference}">
<div class="input-group"><input name="amount" type="number" step="0.01" class="form-control input-sm" value="{$t.amount_captured-$t.amount_refunded}"><span class="input-group-btn"><button name="doRefund" class="btn btn-sm btn-warning" onclick="return confirm('Refund this amount?')">Refund</button></span></div>
<input name="reason" class="form-control input-sm" placeholder="Reason (required for the audit trail)"></form></div>
<div class="col-md-3"><form method="post"><input type="hidden" name="reference" value="{$t.reference}"><input name="reason" class="form-control input-sm" placeholder="Void reason"><button name="doVoid" class="btn btn-sm btn-default" onclick="return confirm('Void / release this payment?')">Void</button> <button name="doVerify" class="btn btn-sm btn-default">Ask the gateway</button></form></div>
<div class="col-md-3"><form method="post"><input type="hidden" name="reference" value="{$t.reference}"><b>Confirm manually</b>
<input name="rrn" class="form-control input-sm" placeholder="RRN / transfer ref" required><input name="auth_code" class="form-control input-sm" placeholder="Auth code"><input name="card_last4" class="form-control input-sm" placeholder="Last 4"><input name="bank" class="form-control input-sm" placeholder="Bank">
<select name="method" class="form-control input-sm"><option value="card">card</option><option value="transfer">transfer</option><option value="mobile_money">mobile money</option><option value="cash">cash</option></select>
<button name="doConfirmManual" class="btn btn-sm btn-success">Confirm &amp; post</button></form></div>
</div></div>

{if $children}<h4>Captures &amp; children</h4><table class="table table-condensed"><thead><tr><th>Reference</th><th>Type</th><th class="text-right">Amount</th><th>State</th><th>When</th></tr></thead><tbody>
{foreach $children as $c}<tr><td><a href="{$self_url}&reference={$c.reference}">{$c.reference}</a></td><td>{$c.type}</td><td class="text-right">{displayPrice price=$c.amount}</td><td>{$c.state}</td><td>{$c.date_add}</td></tr>{/foreach}</tbody></table>{/if}

{if $refunds}<h4>Refunds</h4><table class="table table-condensed"><thead><tr><th>Reference</th><th class="text-right">Amount</th><th>Reason</th><th>Status</th><th>By</th><th>When</th></tr></thead><tbody>
{foreach $refunds as $r}<tr><td>{$r.reference}</td><td class="text-right">{displayPrice price=$r.amount}</td><td>{$r.reason}</td><td>{$r.status}{if $r.failed_reason} — {$r.failed_reason}{/if}</td><td>{$r.who}</td><td>{$r.date_add}</td></tr>{/foreach}</tbody></table>{/if}

<h4>Gateway call log <small>(secrets redacted)</small></h4>
<table class="table table-condensed pp-log"><thead><tr><th>When</th><th>Operation</th><th>HTTP</th><th>Try</th><th>ms</th><th>Request</th><th>Response</th></tr></thead><tbody>
{foreach $logs as $l}<tr class="{if !$l.ok}danger{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M:%S"}</td><td>{$l.operation}</td><td>{$l.http_code}</td><td>{$l.attempt}</td><td>{$l.duration_ms}</td><td><pre>{$l.request|escape:'html'|truncate:600}</pre></td><td><pre>{$l.response|escape:'html'|truncate:900}</pre></td></tr>
{foreachelse}<tr><td colspan="7"><em>No gateway calls recorded</em></td></tr>{/foreach}</tbody></table>

<h4>Stored payload</h4><pre class="pp-raw">{$t.raw|escape:'html'}</pre>
</div></div>
