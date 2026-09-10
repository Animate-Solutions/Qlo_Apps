<div class="pulse-acc"><div class="panel"><h3><i class="icon-legal"></i> Tax — period {$period}</h3>
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccTax"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="period" class="form-control">{foreach $periods as $p}<option value="{$p.code}" {if $period == $p.code}selected{/if}>{$p.code} ({$p.status})</option>{/foreach}</select>
<button class="btn btn-default">Show</button></form>

<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Output VAT ({$vat_pct}%)</span><span class="v">{displayPrice price=$ret.output_vat}</span><small class="muted">on {displayPrice price=$ret.output_net}</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Input VAT claimed</span><span class="v">{displayPrice price=$ret.input_vat}</span><small class="muted">on {displayPrice price=$ret.input_net}</small></div></div>
<div class="col-md-3"><div class="tile {if $ret.net_payable > 0}warn{/if}"><span class="k">Net VAT payable</span><span class="v">{displayPrice price=$ret.net_payable}</span><small class="muted">due {$ret.due_date}</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Consumption tax ({$cons_pct}%)</span><span class="v">{displayPrice price=$ret.consumption_tax}</span><small class="muted">Rivers State, F&amp;B</small></div></div>
</div>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#t-ret">VAT return</a></li><li><a data-toggle="tab" href="#t-out">Output register ({$vat_out|count})</a></li>
<li><a data-toggle="tab" href="#t-in">Input register ({$vat_in|count})</a></li><li><a data-toggle="tab" href="#t-wht">Withholding tax ({$wht|count})</a></li>
<li><a data-toggle="tab" href="#t-einv">FIRS e-invoicing ({$einvoices|count})</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="t-ret">
<table class="table table-condensed" style="max-width:640px"><tbody>
<tr><th>Standard-rated supplies (net)</th><td class="num">{displayPrice price=$ret.output_net}</td></tr>
<tr><th>Output VAT at {$vat_pct}%</th><td class="num">{displayPrice price=$ret.output_vat}</td></tr>
<tr><th>Purchases bearing VAT (net)</th><td class="num">{displayPrice price=$ret.input_net}</td></tr>
<tr><th>Input VAT recoverable</th><td class="num">{displayPrice price=$ret.input_vat}</td></tr>
<tr class="pl-total"><th>Net VAT payable to FIRS</th><td class="num">{displayPrice price=$ret.net_payable}</td></tr>
<tr><th>Consumption tax collected (state)</th><td class="num">{displayPrice price=$ret.consumption_tax}</td></tr>
</tbody></table>
<h4>Ledger check</h4>
<table class="table table-condensed" style="max-width:640px"><tbody>
<tr><th>2210 VAT output movement this period</th><td class="num">{displayPrice price=$ret.gl_output}</td><td class="{if $ret.gl_output != $ret.output_vat}neg{/if}">{if $ret.gl_output != $ret.output_vat}differs from the register — check for journals posted straight to 2210{else}agrees{/if}</td></tr>
<tr><th>1270 VAT input movement this period</th><td class="num">{displayPrice price=$ret.gl_input}</td><td class="{if -$ret.gl_input != $ret.input_vat}neg{/if}">{if -$ret.gl_input != $ret.input_vat}differs from the register{else}agrees{/if}</td></tr>
<tr><th>2240 consumption tax movement</th><td class="num">{displayPrice price=$ret.gl_consumption}</td><td></td></tr>
</tbody></table>
<form method="post" class="form-inline noprint"><input type="hidden" name="period_s" value="{$period}">
<input name="reference" class="form-control" placeholder="FIRS filing reference" required>
<button name="fileVat" class="btn btn-primary" onclick="return confirm('File {$period}? The register is locked and the net VAT moves to 2220 VAT payable.')">File this return</button></form>
<p class="help-block">Filing posts 2210 Dr, 1270 Cr and the net to 2220, and flags every register row as returned. Pay 2220 from Banking when the transfer goes out.</p>
</div>

<div class="tab-pane" id="t-out">
<a class="btn btn-default btn-xs noprint" href="{$self_url}&amp;period={$period}&amp;export=vat&amp;direction=output">Export CSV</a>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Document</th><th>Customer</th><th>Dept</th><th class="num">Net</th><th class="num">VAT</th><th class="num">Consumption</th><th>Filed</th></tr></thead><tbody>
{foreach $vat_out as $v}<tr><td>{$v.business_date}</td><td>{$v.doc_no|escape}</td><td>{$v.party_name|escape}</td><td>{$v.department}</td>
<td class="num">{displayPrice price=$v.net_amount}</td><td class="num">{displayPrice price=$v.vat_amount}</td><td class="num">{if $v.consumption_tax != 0}{displayPrice price=$v.consumption_tax}{/if}</td>
<td>{if $v.returned}{$v.return_ref|escape}{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No output VAT in this period</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="t-in">
<a class="btn btn-default btn-xs noprint" href="{$self_url}&amp;period={$period}&amp;export=vat&amp;direction=input">Export CSV</a>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Document</th><th>Supplier</th><th>TIN</th><th class="num">Net</th><th class="num">VAT</th><th>Filed</th></tr></thead><tbody>
{foreach $vat_in as $v}<tr><td>{$v.business_date}</td><td>{$v.doc_no|escape}</td><td>{$v.party_name|escape}</td><td>{$v.tin|escape}</td>
<td class="num">{displayPrice price=$v.net_amount}</td><td class="num">{displayPrice price=$v.vat_amount}</td><td>{if $v.returned}{$v.return_ref|escape}{/if}</td></tr>
{foreachelse}<tr><td colspan="7"><em>No input VAT in this period</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="t-wht">
<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Withheld from suppliers</span><span class="v">{displayPrice price=$wht_summary.deducted}</span></div></div>
<div class="col-md-3"><div class="tile {if $wht_summary.unremitted > 0}warn{/if}"><span class="k">Not yet remitted</span><span class="v">{displayPrice price=$wht_summary.unremitted}</span></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Suffered on our sales</span><span class="v">{displayPrice price=$wht_summary.suffered}</span><small class="muted">credit in 1260</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Certificates</span><span class="v">{$wht_summary.certificates}</span></div></div>
</div>
<form method="post" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="period_s" value="{$period}">
<input name="reference" class="form-control" placeholder="remittance reference" required>
<select name="id_bank" class="form-control">{foreach $banks as $b}<option value="{$b.id_pulse_acc_bank_account}">{$b.name|escape}</option>{/foreach}</select>
<button name="remitWht" class="btn btn-primary" onclick="return confirm('Remit every unremitted certificate in {$period}?')">Remit {$period}</button>
<a class="btn btn-default" href="{$self_url}&amp;period={$period}&amp;export=wht">Export CSV</a></form>
<table class="table table-condensed"><thead><tr><th>Certificate</th><th>Date</th><th>Direction</th><th>Party</th><th>TIN</th><th>Type</th><th class="num">Base</th><th class="num">Rate</th><th class="num">Tax</th><th>Remitted</th><th></th></tr></thead><tbody>
{foreach $wht as $w}<tr class="{if $w.direction == 'suffered'}info{elseif !$w.remitted}warning{/if}">
<td>{$w.cert_no}</td><td>{$w.business_date}</td><td>{$w.direction}</td><td>{$w.party_name|escape}</td><td>{$w.tin|escape}</td><td>{$w.wht_type}</td>
<td class="num">{displayPrice price=$w.base_amount}</td><td class="num">{$w.rate_pct}%</td><td class="num">{displayPrice price=$w.amount}</td>
<td>{if $w.remitted}{$w.remit_date}{else}<span class="muted">no</span>{/if}</td>
<td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_wht={$w.id_pulse_acc_wht}">Certificate</a></td></tr>
{foreachelse}<tr><td colspan="11"><em>No withholding tax in this period</em></td></tr>{/foreach}
</tbody></table>
<h4 class="noprint">Record a certificate by hand</h4>
<form method="post" class="form-inline noprint">
<select name="direction" class="form-control"><option value="deducted">we withheld</option><option value="suffered">a customer withheld</option></select>
<select name="party_type" class="form-control"><option value="supplier">supplier</option><option value="company">company</option><option value="employee">employee</option><option value="other">other</option></select>
<input name="party_name" class="form-control" placeholder="party name" required><input name="tin" class="form-control" placeholder="TIN">
<select name="wht_type" class="form-control"><option value="services">services 5%</option><option value="contracts">contracts 5%</option><option value="rent">rent 10%</option><option value="dividends">dividends 10%</option><option value="commission">commission 5%</option><option value="royalties">royalties 10%</option><option value="other">other</option></select>
<input name="base_amount" type="number" step="0.01" class="form-control num" placeholder="base" required>
<input name="rate_pct" type="number" step="0.1" class="form-control num" placeholder="rate %" value="5">
<input name="doc_no" class="form-control" placeholder="document"><input type="date" name="business_date" class="form-control">
<button name="addWht" class="btn btn-default">Record</button></form>
</div>

<div class="tab-pane" id="t-einv">
{if !$einv_on}<div class="alert alert-warning">FIRS e-invoicing is switched off. Invoices are still built and queued here so nothing is lost — turn it on and add the endpoint, business ID, service ID, key and secret under <a href="{$link_settings}">Settings</a> when the credentials arrive.</div>
{elseif !$einv_endpoint}<div class="alert alert-info">No transmission endpoint is configured, so the queue runs as a dry run: every payload is built and validated but nothing is sent.</div>{/if}
<form method="post" class="form-inline noprint" style="margin-bottom:8px"><button name="drainEinvoice" class="btn btn-primary">Send the queue now</button>
<span class="help-block" style="display:inline-block;margin-left:10px">The cron does this every run; failures back off and retry rather than blocking the invoice.</span></form>
<table class="table table-condensed"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th class="num">Total</th><th>IRN</th><th>Status</th><th class="num">Tries</th><th>Last error</th><th></th></tr></thead><tbody>
{foreach $einvoices as $e}<tr class="{if $e.status == 'accepted'}success{elseif $e.status == 'rejected' || $e.status == 'failed'}danger{elseif $e.status == 'queued'}warning{/if}">
<td>{$e.invoice_no}</td><td>{$e.company_name|escape}</td><td>{$e.business_date}</td><td class="num">{displayPrice price=$e.total}</td>
<td><small>{$e.irn|escape}</small></td><td>{$e.status}{if $e.http_code} <small class="muted">HTTP {$e.http_code}</small>{/if}</td><td class="num">{$e.attempts}</td><td class="muted">{$e.last_error|escape}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_einvoice_s" value="{$e.id_pulse_acc_einvoice}">
<button name="sendEinvoice" class="btn btn-xs btn-default">Send</button>
{if $e.status == 'rejected' || $e.status == 'failed'}<button name="requeueEinvoice" class="btn btn-xs btn-link">Requeue</button>{/if}</form>
<a class="btn btn-xs btn-link" href="{$self_url}&amp;period={$period}&amp;id_einvoice={$e.id_pulse_acc_einvoice}#t-einv">Payload</a></td></tr>
{foreachelse}<tr><td colspan="9"><em>Nothing queued</em></td></tr>{/foreach}
</tbody></table>
{if $einv_row}<h4>Payload — {$einv_row.invoice_no}</h4>
<textarea class="json" readonly>{$einv_row.payload|escape}</textarea>
{if $einv_row.response}<h4>Service response</h4><textarea class="json" readonly>{$einv_row.response|escape}</textarea>{/if}{/if}
</div>

</div></div></div>
