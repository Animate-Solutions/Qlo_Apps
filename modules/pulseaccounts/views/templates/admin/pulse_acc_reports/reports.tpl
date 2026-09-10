<div class="pulse-acc"><div class="panel"><h3><i class="icon-bar-chart"></i> Accounting reports</h3>
<form method="get" class="form-inline noprint" style="margin-bottom:10px">
<input type="hidden" name="controller" value="AdminPulseAccReports"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="r" class="form-control">{foreach $reports as $k => $v}<option value="{$k}" {if $r == $k}selected{/if}>{$v}</option>{/foreach}</select>
<input type="date" name="from" value="{$from}" class="form-control"> <input type="date" name="to" value="{$to}" class="form-control">
{if $r == 'gl'}<select name="account_code" class="form-control">{foreach $accounts as $a}<option value="{$a.code}" {if $account_code == $a.code}selected{/if}>{$a.code} — {$a.name|escape}</option>{/foreach}</select>{/if}
{if $r == 'budget'}<input name="year" type="number" class="form-control" value="{$year}" style="width:90px"><input name="month" type="number" min="1" max="12" class="form-control" value="{$month}" placeholder="month" style="width:90px">{/if}
{if $r == 'drj'}<input type="date" name="date" value="{$date}" class="form-control">{/if}
{if $r == 'pl'}<label><input type="checkbox" name="compare" value="1" {if $smarty.get.compare}checked{/if}> vs last year</label>{/if}
{if $r == 'tb'}<label><input type="checkbox" name="include_zero" value="1" {if $smarty.get.include_zero}checked{/if}> show nil accounts</label>{/if}
<button class="btn btn-default">Run</button>
<a class="btn btn-default" href="{$self_url}&amp;r={$r}&amp;from={$from}&amp;to={$to}&amp;account_code={$account_code|escape:'url'}&amp;year={$year}&amp;month={$month}&amp;date={$date}&amp;export=1">Export CSV</a>
<a class="btn btn-default" href="javascript:window.print()">Print</a>
</form>

{if $r == 'tb'}
<h4>Trial balance {$from} to {$to} {if !$data.balanced}<span class="badge" style="background:#c0392b">out of balance</span>{/if}</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Account</th><th>Type</th><th class="num">Opening</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Closing Dr</th><th class="num">Closing Cr</th></tr></thead><tbody>
{foreach $data.rows as $x}<tr><td class="acc-code"><a href="{$self_url}&amp;r=gl&amp;account_code={$x.code|escape:'url'}&amp;from={$from}&amp;to={$to}">{$x.code}</a></td><td>{$x.name|escape}</td><td>{$x.type}</td>
<td class="num">{displayPrice price=$x.opening}</td><td class="num">{displayPrice price=$x.debit}</td><td class="num">{displayPrice price=$x.credit}</td>
<td class="num">{if $x.closing_dr}{displayPrice price=$x.closing_dr}{/if}</td><td class="num">{if $x.closing_cr}{displayPrice price=$x.closing_cr}{/if}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="4">Totals</td><td class="num">{displayPrice price=$data.totals.debit}</td><td class="num">{displayPrice price=$data.totals.credit}</td>
<td class="num">{displayPrice price=$data.totals.closing_dr}</td><td class="num">{displayPrice price=$data.totals.closing_cr}</td></tr>
</tbody></table>

{elseif $r == 'gl'}
{if !$data}<div class="alert alert-warning">Pick an account.</div>{else}
<h4>{$data.account.code} — {$data.account.name|escape} &middot; {$from} to {$to}</h4>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Journal</th><th>Source</th><th>Memo</th><th>Cost centre</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>
<tr><td colspan="7"><em>Opening balance</em></td><td class="num">{displayPrice price=$data.opening}</td></tr>
{foreach $data.rows as $x}<tr><td>{$x.business_date}</td><td><a href="{$link_journals}&amp;id_journal={$x.id_pulse_acc_journal}">{$x.journal_no}</a></td>
<td>{$x.source}{if $x.source_ref}<br><small class="muted">{$x.source_ref|escape}</small>{/if}</td><td>{$x.memo|escape}</td><td>{$x.cost_centre}</td>
<td class="num">{if $x.debit > 0}{displayPrice price=$x.debit}{/if}</td><td class="num">{if $x.credit > 0}{displayPrice price=$x.credit}{/if}</td><td class="num">{displayPrice price=$x.balance}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No movement in this range</em></td></tr>{/foreach}
<tr class="pl-total"><td colspan="5">Closing</td><td class="num">{displayPrice price=$data.debit}</td><td class="num">{displayPrice price=$data.credit}</td><td class="num">{displayPrice price=$data.closing}</td></tr>
</tbody></table>{/if}

{elseif $r == 'pl'}
<h4>Summary operating statement (USALI) — {$from} to {$to}</h4>
<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Total revenue</span><span class="v">{displayPrice price=$data.total_revenue}</span></div></div>
<div class="col-md-3"><div class="tile ok"><span class="k">GOP</span><span class="v">{displayPrice price=$data.gop}</span><small class="muted">{$data.gop_pct}% of revenue</small></div></div>
<div class="col-md-3"><div class="tile ok"><span class="k">EBITDA</span><span class="v">{displayPrice price=$data.ebitda}</span><small class="muted">{$data.ebitda_pct}%</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Net profit</span><span class="v">{displayPrice price=$data.net_profit}</span></div></div>
</div>
{if $data.stats.rooms}<p class="muted">Occupancy {$data.stats.occupancy_pct}% &middot; ADR {displayPrice price=$data.stats.adr} &middot; RevPAR {displayPrice price=$data.stats.revpar} &middot; {$data.stats.rooms_sold} of {$data.stats.rooms_available} room nights sold</p>{/if}
<table class="table table-condensed" style="max-width:900px"><tbody>
{foreach $data.departments as $d}
<tr class="pl-section"><td colspan="2">{$d.label}</td><td class="num">{displayPrice price=$d.revenue}</td></tr>
<tr><td colspan="2" style="padding-left:24px">Cost of sales</td><td class="num">({displayPrice price=$d.cost_of_sales})</td></tr>
<tr><td colspan="2" style="padding-left:24px">Payroll and related</td><td class="num">({displayPrice price=$d.payroll})</td></tr>
<tr><td colspan="2" style="padding-left:24px">Other departmental expenses</td><td class="num">({displayPrice price=$d.other})</td></tr>
<tr class="pl-total"><td colspan="2">{$d.label} departmental profit</td><td class="num">{displayPrice price=$d.profit}{if $d.margin_pct !== null} <small class="muted">{$d.margin_pct}%</small>{/if}</td></tr>
{/foreach}
<tr class="pl-total"><td colspan="2">Total departmental profit</td><td class="num">{displayPrice price=$data.departmental_profit}</td></tr>
<tr class="pl-section"><td colspan="3">Undistributed operating expenses</td></tr>
{foreach $data.undistributed.groups as $g}<tr><td colspan="2" style="padding-left:24px">{$g.label}</td><td class="num">({displayPrice price=$g.total})</td></tr>{/foreach}
<tr class="pl-total"><td colspan="2">Gross operating profit (GOP)</td><td class="num">{displayPrice price=$data.gop}</td></tr>
<tr class="pl-section"><td colspan="3">Fixed charges</td></tr>
{foreach $data.fixed.accounts as $x}<tr><td colspan="2" style="padding-left:24px">{$x.name|escape}</td><td class="num">({displayPrice price=$x.amount})</td></tr>{/foreach}
<tr class="pl-total"><td colspan="2">EBITDA (before depreciation and interest)</td><td class="num">{displayPrice price=$data.ebitda}</td></tr>
<tr class="pl-total"><td colspan="2">EBIT</td><td class="num">{displayPrice price=$data.ebit}</td></tr>
{if $data.non_operating.income || $data.non_operating.expense}<tr><td colspan="2">Non-operating income / expense</td><td class="num">{displayPrice price=$data.non_operating.income} / ({displayPrice price=$data.non_operating.expense})</td></tr>{/if}
{if $data.tax}<tr><td colspan="2">Income tax</td><td class="num">({displayPrice price=$data.tax})</td></tr>{/if}
<tr class="pl-total"><td colspan="2">Net profit</td><td class="num">{displayPrice price=$data.net_profit}</td></tr>
</tbody></table>
<h4>Detail by account</h4>
<table class="table table-condensed"><thead><tr><th>Department</th><th>Code</th><th>Account</th><th class="num">Amount</th>{if $smarty.get.compare}<th class="num">Last year</th>{/if}</tr></thead><tbody>
{foreach $data.departments as $d}{foreach $d.accounts as $x}<tr><td>{$d.label}</td><td class="acc-code">{$x.code}</td><td>{$x.name|escape}</td><td class="num">{displayPrice price=$x.amount}</td>
{if $smarty.get.compare}<td class="num muted">{$x.prior}</td>{/if}</tr>{/foreach}{/foreach}
{foreach $data.undistributed.groups as $g}{foreach $g.accounts as $x}<tr><td>{$g.label}</td><td class="acc-code">{$x.code}</td><td>{$x.name|escape}</td><td class="num">{displayPrice price=$x.amount}</td>
{if $smarty.get.compare}<td class="num muted">{$x.prior}</td>{/if}</tr>{/foreach}{/foreach}
</tbody></table>

{elseif $r == 'bs'}
<h4>Balance sheet as at {$to} {if !$data.balanced}<span class="badge" style="background:#c0392b">out by {$data.difference}</span>{/if}</h4>
<div class="row">
<div class="col-md-6">
{foreach $data.left as $g}
<h4>{$g.label}</h4>
<table class="table table-condensed"><tbody>
{foreach $g.rows as $x}<tr><td class="acc-code">{$x.code}</td><td>{$x.name|escape}</td><td class="num">{displayPrice price=$x.amount}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="2">Total</td><td class="num">{displayPrice price=$g.total}</td></tr>
</tbody></table>{/foreach}
<table class="table table-condensed"><tbody><tr class="pl-total"><td colspan="2">TOTAL ASSETS</td><td class="num">{displayPrice price=$data.total_assets}</td></tr></tbody></table>
</div>
<div class="col-md-6">
{foreach $data.right as $g}
<h4>{$g.label}</h4>
<table class="table table-condensed"><tbody>
{foreach $g.rows as $x}<tr><td class="acc-code">{$x.code}</td><td>{$x.name|escape}</td><td class="num">{displayPrice price=$x.amount}</td></tr>{/foreach}
{if isset($g.is_equity)}<tr><td></td><td>Result for the year to date</td><td class="num">{displayPrice price=$data.result_for_year}</td></tr>{/if}
<tr class="pl-total"><td colspan="2">Total</td><td class="num">{if isset($g.is_equity)}{displayPrice price=$data.total_equity}{else}{displayPrice price=$g.total}{/if}</td></tr>
</tbody></table>{/foreach}
<table class="table table-condensed"><tbody><tr class="pl-total"><td colspan="2">TOTAL LIABILITIES AND EQUITY</td><td class="num">{displayPrice price=$data.total_funding}</td></tr></tbody></table>
</div></div>

{elseif $r == 'cf'}
<h4>Cash flow (indirect) — {$from} to {$to}</h4>
<table class="table table-condensed" style="max-width:640px"><tbody>
<tr class="pl-section"><td colspan="2">Operating activities</td></tr>
<tr><td>Profit for the period</td><td class="num">{displayPrice price=$data.profit}</td></tr>
<tr><td>Add back depreciation and amortisation</td><td class="num">{displayPrice price=$data.depreciation}</td></tr>
{foreach $data.working_capital as $k => $v}<tr><td style="padding-left:20px">Movement in {$k}</td><td class="num">{displayPrice price=$v}</td></tr>{/foreach}
<tr class="pl-total"><td>Cash generated from operations</td><td class="num">{displayPrice price=$data.operating}</td></tr>
<tr class="pl-section"><td colspan="2">Investing activities</td></tr>
<tr><td>Purchase and disposal of fixed assets</td><td class="num">{displayPrice price=$data.investing}</td></tr>
<tr class="pl-section"><td colspan="2">Financing activities</td></tr>
<tr><td>Loans, capital and drawings</td><td class="num">{displayPrice price=$data.financing}</td></tr>
<tr class="pl-total"><td>Net movement in cash</td><td class="num">{displayPrice price=$data.net_movement}</td></tr>
<tr><td>Opening cash and bank</td><td class="num">{displayPrice price=$data.opening_cash}</td></tr>
<tr class="pl-total"><td>Closing cash and bank</td><td class="num">{displayPrice price=$data.closing_cash}</td></tr>
{if $data.unexplained > 0.01 || $data.unexplained < -0.01}<tr class="danger"><td>Unexplained difference — check for postings outside the classified accounts</td><td class="num">{displayPrice price=$data.unexplained}</td></tr>{/if}
</tbody></table>

{elseif $r == 'budget'}
<h4>Budget vs actual — {$data.from} to {$data.to}</h4>
{if isset($data.note)}<div class="alert alert-info">{$data.note}</div>{/if}
<table class="table table-condensed" style="max-width:820px"><thead><tr><th>Line</th><th>Kind</th><th class="num">Budget</th><th class="num">Actual</th><th class="num">Variance</th><th class="num">%</th></tr></thead><tbody>
{foreach $data.rows as $x}<tr class="{if $x.variance < 0}danger{elseif $x.variance > 0}success{/if}"><td>{$x.label|escape}</td><td>{$x.kind}</td>
<td class="num">{displayPrice price=$x.budget}</td><td class="num">{displayPrice price=$x.actual}</td><td class="num">{displayPrice price=$x.variance}</td>
<td class="num">{if $x.variance_pct !== null}{$x.variance_pct}%{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No budget lines for this period — set them in Pulse Reports</em></td></tr>{/foreach}
</tbody></table>
<p class="help-block">Favourable variance is shown positive: more revenue than budget, or less expense than budget.</p>

{elseif $r == 'rev_dept'}
<h4>Revenue by department — {$from} to {$to}</h4>
<table class="table table-condensed" style="max-width:520px"><thead><tr><th>Department</th><th class="num">Revenue</th><th class="num">Journals</th></tr></thead><tbody>
{foreach $data as $x}<tr><td>{$x.department|replace:'_':' '}</td><td class="num">{displayPrice price=$x.revenue}</td><td class="num">{$x.journals}</td></tr>{/foreach}
</tbody></table>

{elseif $r == 'rev_code'}
<h4>Revenue by charge code — {$from} to {$to}</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Department</th><th class="num">Postings</th><th class="num">Net</th><th class="num">Tax</th><th class="num">Gross</th></tr></thead><tbody>
{foreach $data as $x}<tr><td>{$x.code}</td><td>{$x.name|escape}</td><td>{$x.department}</td><td class="num">{$x.postings}</td>
<td class="num">{displayPrice price=$x.net}</td><td class="num">{displayPrice price=$x.tax}</td><td class="num">{displayPrice price=$x.gross}</td></tr>
{foreachelse}<tr><td colspan="7"><em>Front Desk is not installed, so there are no charge-code postings</em></td></tr>{/foreach}
</tbody></table>

{elseif $r == 'drj'}
<h4>Daily revenue journal — {$data.date}</h4>
<div class="row"><div class="col-md-6">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Account</th><th class="num">Revenue</th></tr></thead><tbody>
{foreach $data.gl as $x}<tr><td class="acc-code">{$x.code}</td><td>{$x.name|escape}</td><td class="num">{displayPrice price=$x.amount}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="2">Total revenue posted</td><td class="num">{displayPrice price=$data.gl_total}</td></tr>
<tr><td colspan="2">Tax collected (VAT and consumption)</td><td class="num">{displayPrice price=$data.tax}</td></tr>
</tbody></table>
<table class="table table-condensed"><tbody>
<tr><th>Night audit revenue</th><td class="num">{if $data.audit_revenue === null}—{else}{displayPrice price=$data.audit_revenue}{/if}</td></tr>
<tr class="{if $data.variance_vs_audit > 0.01 || $data.variance_vs_audit < -0.01}danger{/if}"><th>Variance GL vs audit</th><td class="num">{if $data.variance_vs_audit === null}—{else}{displayPrice price=$data.variance_vs_audit}{/if}</td></tr>
<tr><th>Folio charges net of tax</th><td class="num">{if $data.folio_net === null}—{else}{displayPrice price=$data.folio_net}{/if}</td></tr>
{if $data.queue_pending}<tr class="warning"><th>Still waiting to post</th><td class="num">{$data.queue_pending}</td></tr>{/if}
</tbody></table>
</div>
<div class="col-md-6">
<h4>Settlement and ledger movement</h4>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Account</th><th class="num">Movement</th></tr></thead><tbody>
{foreach $data.settlements as $x}<tr><td class="acc-code">{$x.account_code}</td><td>{$x.account_name|escape}</td><td class="num">{displayPrice price=$x.amount}</td></tr>{/foreach}
</tbody></table>
</div></div>
{/if}

</div></div>
