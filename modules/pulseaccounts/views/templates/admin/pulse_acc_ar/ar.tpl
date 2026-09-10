<div class="pulse-acc"><div class="panel"><h3><i class="icon-briefcase"></i> Receivables — city ledger</h3>
{if !$fd}<div class="alert alert-info">Front Desk is not installed, so there are no company folios to invoice. Manual invoices, receipts and ageing still work.</div>{/if}
<div class="row">
<div class="col-md-2"><div class="tile"><span class="k">Current</span><span class="v">{displayPrice price=$summary.b_current}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">1–30</span><span class="v">{displayPrice price=$summary.b_30}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">31–60</span><span class="v">{displayPrice price=$summary.b_60}</span></div></div>
<div class="col-md-2"><div class="tile"><span class="k">61–90</span><span class="v">{displayPrice price=$summary.b_90}</span></div></div>
<div class="col-md-2"><div class="tile {if $summary.b_over > 0}bad{/if}"><span class="k">Over 90</span><span class="v">{displayPrice price=$summary.b_over}</span></div></div>
<div class="col-md-2"><div class="tile ok"><span class="k">Total</span><span class="v">{displayPrice price=$summary.total}</span></div></div>
</div>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#r-age">Ageing</a></li><li><a data-toggle="tab" href="#r-inv">Invoices</a></li>
<li><a data-toggle="tab" href="#r-bill">Invoice a company</a></li><li><a data-toggle="tab" href="#r-rct">Receipts &amp; allocation</a></li>
<li><a data-toggle="tab" href="#r-stmt">Statement</a></li><li><a data-toggle="tab" href="#r-stop">Stop list ({$stop_list|count})</a></li>
<li><a data-toggle="tab" href="#r-dun">Dunning ({$dunning|count})</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="r-age">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAr"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
As at <input type="date" name="as_of" value="{$as_of}" class="form-control"> <button class="btn btn-default">Show</button> <a class="btn btn-default" href="{$self_url}&amp;as_of={$as_of}&amp;export=ageing">Export CSV</a></form>
<table class="table table-condensed"><thead><tr><th>Company</th><th class="num">Invoices</th><th class="num">Current</th><th class="num">1–30</th><th class="num">31–60</th><th class="num">61–90</th><th class="num">91–120</th><th class="num">120+</th><th class="num">Total</th><th></th></tr></thead><tbody>
{foreach $ageing as $a}<tr class="{if $a.b_over > 0}danger{elseif $a.b_120 > 0}warning{/if}">
<td><a href="{$self_url}&amp;id_pulse_company={$a.id_pulse_company}">{$a.company_name|escape}</a></td><td class="num">{$a.invoices}</td>
<td class="num">{displayPrice price=$a.b_current}</td><td class="num">{displayPrice price=$a.b_30}</td><td class="num">{displayPrice price=$a.b_60}</td>
<td class="num">{displayPrice price=$a.b_90}</td><td class="num">{displayPrice price=$a.b_120}</td><td class="num">{displayPrice price=$a.b_over}</td>
<td class="num"><strong>{displayPrice price=$a.total}</strong></td>
<td class="noprint"><form method="post" class="inline"><input type="hidden" name="id_company_s" value="{$a.id_pulse_company}"><input type="hidden" name="as_of" value="{$as_of}"><button name="makeLetter" class="btn btn-xs btn-default">Letter</button></form></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nothing outstanding</em></td></tr>{/foreach}
<tr class="pl-total"><td colspan="2">Total</td><td class="num">{displayPrice price=$summary.b_current}</td><td class="num">{displayPrice price=$summary.b_30}</td><td class="num">{displayPrice price=$summary.b_60}</td><td class="num">{displayPrice price=$summary.b_90}</td><td colspan="2" class="num">{displayPrice price=$summary.b_over}</td><td class="num">{displayPrice price=$summary.total}</td><td></td></tr>
</tbody></table>
</div>

<div class="tab-pane" id="r-inv">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAr"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_pulse_company" class="form-control"><option value="">every company</option>{foreach $companies as $c}<option value="{$c.id_pulse_company}" {if $id_pulse_company == $c.id_pulse_company}selected{/if}>{$c.name|escape}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">any status</option>{foreach ['issued','part_paid','paid','credited','written_off'] as $s}<option value="{$s}" {if $smarty.get.status == $s}selected{/if}>{$s}</option>{/foreach}</select>
<input name="q" class="form-control" placeholder="invoice number or company" value="{$smarty.get.q|escape}"><button class="btn btn-default">Search</button></form>
<table class="table table-condensed"><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Company</th><th class="num">Total</th><th class="num">Allocated</th><th class="num">Balance</th><th>Status</th><th class="num">Age</th></tr></thead><tbody>
{foreach $invoices as $i}<tr class="{if $i.days_overdue > 0 && $i.balance > 0}warning{/if}">
<td><a href="{$self_url}&amp;id_invoice={$i.id_pulse_acc_invoice}">{$i.invoice_no}</a>{if $i.type == 'credit_note'} <small class="muted">CN</small>{/if}</td>
<td>{$i.invoice_date}</td><td>{$i.due_date}</td><td>{$i.company_name|escape}</td>
<td class="num">{displayPrice price=$i.total}</td><td class="num">{displayPrice price=$i.allocated}</td><td class="num">{displayPrice price=$i.balance}</td>
<td>{$i.status}</td><td class="num">{if $i.days_overdue > 0}{$i.days_overdue}d{/if}</td></tr>
{foreachelse}<tr><td colspan="9"><em>No invoices</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="r-bill">
<div class="row"><div class="col-md-6">
<h4>From a company folio</h4>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseAccAr"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_pulse_company" class="form-control"><option value="">pick a company…</option>{foreach $companies as $c}<option value="{$c.id_pulse_company}" {if $id_pulse_company == $c.id_pulse_company}selected{/if}>{$c.name|escape}</option>{/foreach}</select>
<button class="btn btn-default">Load unbilled charges</button></form>
{if $id_pulse_company}<form method="post">
<input type="hidden" name="id_company_s" value="{$id_pulse_company}"><input type="hidden" name="as_of" value="{$as_of}">
<table class="table table-condensed"><thead><tr><th><input type="checkbox" class="acc-check-all" data-target="#bill-lines" checked></th><th>Date</th><th>Folio</th><th>Description</th><th class="num">Amount</th></tr></thead><tbody id="bill-lines">
{foreach $billable as $l}<tr><td><input type="checkbox" name="line[{$l.id_pulse_folio_line}]" value="1" checked></td><td>{$l.business_date}</td><td>{$l.folio_no}</td><td>{$l.description|escape}</td><td class="num">{displayPrice price=$l.amount_tax_incl}</td></tr>
{foreachelse}<tr><td colspan="5"><em>Nothing unbilled on this company's ledger folio</em></td></tr>{/foreach}
</tbody></table>
{if $billable}<input name="note" class="form-control" placeholder="Note on the invoice"><button name="invoiceCompany" class="btn btn-primary">Raise invoice</button>{/if}
</form>{/if}
</div>
<div class="col-md-6">
<h4>Manual invoice</h4>
<form method="post">
<div class="row"><div class="col-sm-6"><select name="id_company_s" class="form-control"><option value="">— no company account —</option>{foreach $companies as $c}<option value="{$c.id_pulse_company}">{$c.name|escape}</option>{/foreach}</select></div>
<div class="col-sm-6"><input name="customer_name" class="form-control" placeholder="or a one-off customer name"></div></div>
<input type="date" name="inv_date" value="{$as_of}" class="form-control" style="margin-top:6px">
<table class="table table-condensed" style="margin-top:6px"><thead><tr><th>Description</th><th>Account</th><th>Dept</th><th class="num">Qty</th><th class="num">Price</th><th class="num">VAT %</th></tr></thead><tbody>
{section name=m loop=4}<tr>
<td><input name="m_desc[]" class="form-control input-sm"></td>
<td><select name="m_account[]" class="form-control input-sm"><option value="4360">4360 Hall / venue hire</option><option value="4410">4410 Concession rent</option><option value="4420">4420 Commission</option><option value="4400">4400 Miscellaneous income</option><option value="4130">4130 Groups &amp; conferences</option></select></td>
<td><input name="m_dept[]" class="form-control input-sm" value="general"></td>
<td><input name="m_qty[]" type="number" step="0.01" value="1" class="form-control input-sm num"></td>
<td><input name="m_price[]" type="number" step="0.01" class="form-control input-sm num"></td>
<td><input name="m_tax[]" type="number" step="0.001" value="7.5" class="form-control input-sm num"></td>
</tr>{/section}</tbody></table>
<input name="note" class="form-control" placeholder="Note"><button name="invoiceManual" class="btn btn-primary" style="margin-top:6px">Raise invoice</button>
<p class="help-block">A manual invoice posts revenue and VAT itself. An invoice raised from a folio does not — those charges were already posted when the desk keyed them.</p>
</form>
</div></div>
</div>

<div class="tab-pane" id="r-rct">
<div class="row"><div class="col-md-5">
<h4>Record a receipt</h4>
<form method="post" class="form-horizontal">
<div class="form-group"><label class="col-sm-4 control-label">Company</label><div class="col-sm-8"><select name="id_company_s" class="form-control" required>{foreach $companies as $c}<option value="{$c.id_pulse_company}" {if $id_pulse_company == $c.id_pulse_company}selected{/if}>{$c.name|escape}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Date</label><div class="col-sm-8"><input type="date" name="receipt_date" value="{$as_of}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Amount received</label><div class="col-sm-8"><input name="amount" type="number" step="0.01" class="form-control num" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">WHT deducted</label><div class="col-sm-8"><input name="wht_amount" type="number" step="0.01" class="form-control num" placeholder="0.00">
<input name="wht_cert_no" class="form-control" placeholder="WHT credit note number"><span class="help-block">The customer's WHT still clears the invoice — it lands in 1260 as a tax credit.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Method</label><div class="col-sm-8"><select name="method" class="form-control">{foreach ['transfer','cash','cheque','pos','card','online'] as $m}<option value="{$m}">{$m}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Into</label><div class="col-sm-8"><select name="id_bank" class="form-control"><option value="">rule default</option>{foreach $banks as $b}<option value="{$b.id_pulse_acc_bank_account}">{$b.name|escape}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Reference</label><div class="col-sm-8"><input name="reference" class="form-control" placeholder="transfer / cheque reference"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><label><input type="checkbox" name="auto_allocate" value="1" checked> Apply to the oldest invoices first</label></div></div>
{if $open_invoices}<table class="table table-condensed"><thead><tr><th>Or allocate by hand</th><th class="num">Outstanding</th><th class="num">Apply</th></tr></thead><tbody>
{foreach $open_invoices as $i}<tr><td>{$i.invoice_no} <small class="muted">due {$i.due_date}</small></td><td class="num">{displayPrice price=$i.balance}</td>
<td><input name="alloc[{$i.id_pulse_acc_invoice}]" type="number" step="0.01" class="form-control input-sm num alloc-amount" data-max="{$i.balance}"></td></tr>{/foreach}
</tbody></table>{/if}
<button name="addReceipt" class="btn btn-primary">Post receipt</button>
</form>
</div>
<div class="col-md-7">
<h4>Receipts with money still unapplied</h4>
<table class="table table-condensed"><thead><tr><th>Receipt</th><th>Date</th><th>Company</th><th class="num">Amount</th><th class="num">Unallocated</th><th></th></tr></thead><tbody>
{foreach $unallocated as $r}<tr class="warning"><td>{$r.receipt_no}</td><td>{$r.receipt_date}</td><td>{$r.company_name|escape}</td><td class="num">{displayPrice price=$r.amount}</td><td class="num">{displayPrice price=$r.unallocated}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_receipt" value="{$r.id_pulse_acc_receipt}"><button name="autoAllocate" class="btn btn-xs btn-default">Apply oldest first</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>Every receipt is fully applied</em></td></tr>{/foreach}
</tbody></table>
<h4>Recent receipts</h4>
<table class="table table-condensed"><thead><tr><th>Receipt</th><th>Date</th><th>Company</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th class="num">WHT</th></tr></thead><tbody>
{foreach $receipts as $r}<tr><td>{$r.receipt_no}</td><td>{$r.receipt_date}</td><td>{$r.company_name|escape}</td><td>{$r.method}</td><td>{$r.reference|escape}</td><td class="num">{displayPrice price=$r.amount}</td><td class="num">{if $r.wht_amount > 0}{displayPrice price=$r.wht_amount}{/if}</td></tr>{/foreach}
</tbody></table>
</div></div>
</div>

<div class="tab-pane" id="r-stmt">
{if $statement}
<h4>Statement — {$statement.company.name|escape} &middot; {$statement.from} to {$statement.to}</h4>
<table class="table table-condensed" style="max-width:860px"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Detail</th><th class="num">Charges</th><th class="num">Payments</th><th class="num">Balance</th></tr></thead><tbody>
<tr><td colspan="6"><em>Balance brought forward</em></td><td class="num">{displayPrice price=$statement.opening}</td></tr>
{foreach $statement.rows as $r}<tr><td>{$r.d}</td><td>{$r.kind}</td><td>{$r.ref}</td><td>{$r.memo|escape}</td>
<td class="num">{if $r.debit != 0}{displayPrice price=$r.debit}{/if}</td><td class="num">{if $r.credit != 0}{displayPrice price=$r.credit}{/if}</td><td class="num">{displayPrice price=$r.balance}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="6">Balance now due</td><td class="num">{displayPrice price=$statement.closing}</td></tr>
</tbody></table>
<p class="noprint"><a class="btn btn-default" href="javascript:window.print()">Print statement</a></p>
{else}<p class="muted">Pick a company on the Ageing tab to see its statement.</p>{/if}
</div>

<div class="tab-pane" id="r-stop">
<p class="help-block">Over the credit limit set on the company account, or with debt older than the stop-list setting. Front Desk can call <code>PulseAccAr::onStop()</code> before routing another charge to a company.</p>
<table class="table table-condensed"><thead><tr><th>Company</th><th class="num">Owed</th><th class="num">Credit limit</th><th class="num">Oldest</th><th>Why</th></tr></thead><tbody>
{foreach $stop_list as $s}<tr class="danger"><td>{$s.company_name|escape}</td><td class="num">{displayPrice price=$s.total}</td><td class="num">{displayPrice price=$s.credit_limit}</td><td class="num">{$s.oldest_days}d</td><td>{$s.reasons}</td></tr>
{foreachelse}<tr><td colspan="5"><em>Nobody is on stop — every account is inside its limit and its terms</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="r-dun">
<table class="table table-condensed"><thead><tr><th>Company</th><th class="num">Overdue</th><th class="num">Oldest</th><th>Suggested letter</th><th>Last sent</th><th></th></tr></thead><tbody>
{foreach $dunning as $c}<tr><td>{$c.company_name|escape}</td><td class="num">{displayPrice price=$c.overdue}</td><td class="num">{$c.oldest_days}d</td>
<td>{if $c.level == 3}<span class="badge" style="background:#c0392b">Final demand</span>{elseif $c.level == 2}<span class="badge" style="background:#e67e22">Second notice</span>{else}<span class="badge">Reminder</span>{/if}</td>
<td class="muted">{$c.last_sent|default:'—'}</td>
<td><form method="post" class="form-inline"><input type="hidden" name="id_company_s" value="{$c.id_pulse_company}"><input type="hidden" name="as_of" value="{$as_of}">
<select name="level" class="input-sm"><option value="1" {if $c.level == 1}selected{/if}>1</option><option value="2" {if $c.level == 2}selected{/if}>2</option><option value="3" {if $c.level == 3}selected{/if}>3</option></select>
<button name="makeLetter" class="btn btn-xs btn-primary">Generate</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>Nothing is far enough past due to chase</em></td></tr>{/foreach}
</tbody></table>
<h4>Letters</h4>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Company</th><th>Level</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $dunning_log as $l}<tr><td>{$l.as_of}</td><td>{$l.company_name|escape}</td><td>{$l.level}</td><td class="num">{displayPrice price=$l.balance}</td><td>{$l.status}{if $l.sent_at} <small class="muted">{$l.sent_at}</small>{/if}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_dunning={$l.id_pulse_acc_dunning}">Open</a></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
