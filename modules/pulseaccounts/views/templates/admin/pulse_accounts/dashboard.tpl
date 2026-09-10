<div class="pulse-acc"><div class="panel"><h3><i class="icon-book"></i> Accounts — business date {$date} &middot; period {$d.period}
{if $d.period_row && $d.period_row.status != 'open'}<span class="badge" style="background:#c0392b">{$d.period_row.status|escape}</span>{else}<span class="badge" style="background:#27ae60">open</span>{/if}</h3>

<div class="row">
<div class="col-md-3"><div class="tile {if $d.queue_failed}bad{elseif $d.queue_pending}warn{else}ok{/if}"><span class="k">Waiting to post</span><span class="v">{$d.queue_pending}</span>
{if $d.queue_failed}<span class="neg">{$d.queue_failed} failed</span>{/if}{if $d.queue_oldest}<br><small class="muted">oldest {$d.queue_oldest}</small>{/if}</div></div>
<div class="col-md-3"><div class="tile"><span class="k">Cash &amp; bank</span><span class="v">{displayPrice price=$d.cash_total}</span><small class="muted">{$d.cash|count} accounts</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Receivables</span><span class="v">{displayPrice price=$d.ar.total}</span><small class="muted">over 90 days {displayPrice price=$d.ar.b_over}</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Payables</span><span class="v">{displayPrice price=$d.ap.total}</span><small class="muted">over 90 days {displayPrice price=$d.ap.b_over}</small></div></div>
</div>
<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Month to date revenue</span><span class="v">{displayPrice price=$d.mtd.revenue}</span><small class="muted">result {displayPrice price=$d.mtd_result}</small></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Guest ledger</span><span class="v">{displayPrice price=$d.guest_ledger}</span><small class="muted">deposits held {displayPrice price=$d.deposits}</small></div></div>
<div class="col-md-3"><div class="tile {if $d.vat_due > 0}warn{/if}"><span class="k">VAT due this period</span><span class="v">{displayPrice price=$d.vat_due}</span><small class="muted">filed by the 21st</small></div></div>
<div class="col-md-3"><div class="tile {if $d.unmapped}warn{/if}"><span class="k">Unmapped items</span><span class="v">{$d.unmapped}</span><small class="muted"><a href="{$link_rules}">posting rules</a></small></div></div>
</div>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#a-queue">Posting queue ({$counts.pending})</a></li>
<li><a data-toggle="tab" href="#a-drj">Daily revenue journal</a></li>
<li><a data-toggle="tab" href="#a-cash">Cash position</a></li>
<li><a data-toggle="tab" href="#a-ar">AR / AP ageing</a></li>
<li><a data-toggle="tab" href="#a-recent">Recent journals</a></li>
{if $d.unmapped}<li><a data-toggle="tab" href="#a-unmapped" class="text-danger">Unmapped ({$d.unmapped})</a></li>{/if}
</ul>
<div class="tab-content">

<div class="tab-pane active" id="a-queue">
<form method="post" class="form-inline noprint" style="margin-bottom:10px">
<input type="date" name="date" value="{$date}" class="form-control">
<button name="postNow" class="btn btn-primary">Post now</button>
<button name="sweepDay" class="btn btn-default">Rescan the day</button>
{if $counts.failed}<button name="retryFailed" class="btn btn-warning">Retry {$counts.failed} failed</button>{/if}
<span class="help-block" style="display:inline-block;margin-left:12px">Posting runs at night audit. Use this when you need the ledger up to date right now.</span>
</form>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Source</th><th>Document</th><th>Status</th><th>Tries</th><th>Last error</th></tr></thead><tbody>
{foreach $failed as $q}<tr class="danger"><td>{$q.business_date}</td><td>{$q.source}</td><td>{$q.source_ref}</td><td>failed</td><td>{$q.attempts}</td><td>{$q.last_error|escape}
<form method="post" class="inline"><input type="hidden" name="id_queue" value="{$q.id_pulse_acc_queue}"><button name="retryFailed" class="btn btn-xs btn-default">Retry</button></form></td></tr>{/foreach}
{foreach $queue as $q}<tr><td>{$q.business_date}</td><td>{$q.source}</td><td>{$q.source_ref}</td><td>pending</td><td>{$q.attempts}</td><td class="muted">{$q.last_error|escape}</td></tr>
{foreachelse}{if !$failed}<tr><td colspan="6"><em>Nothing waiting — the ledger is level with the operation</em></td></tr>{/if}{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="a-drj">
<h4>{$drj.date} — what the ledger booked against what the night audit says</h4>
<table class="table table-condensed" style="max-width:640px"><tbody>
<tr><th>Revenue posted to the GL</th><td class="num">{displayPrice price=$drj.gl_total}</td></tr>
<tr><th>Night audit revenue</th><td class="num">{if $drj.audit_revenue === null}<span class="muted">no audit record</span>{else}{displayPrice price=$drj.audit_revenue}{/if}</td></tr>
<tr class="{if $drj.variance_vs_audit && ($drj.variance_vs_audit > 0.01 || $drj.variance_vs_audit < -0.01)}danger{/if}"><th>Variance</th><td class="num">{if $drj.variance_vs_audit === null}—{else}{displayPrice price=$drj.variance_vs_audit}{/if}</td></tr>
<tr><th>Folio charges net of tax</th><td class="num">{if $drj.folio_net === null}—{else}{displayPrice price=$drj.folio_net}{/if}</td></tr>
<tr><th>Tax collected (VAT + consumption)</th><td class="num">{displayPrice price=$drj.tax}</td></tr>
{if $drj.queue_pending}<tr class="warning"><th>Still unposted for this date</th><td class="num">{$drj.queue_pending}</td></tr>{/if}
</tbody></table>
<div class="row"><div class="col-md-6"><h4>Revenue by account</h4><table class="table table-condensed"><tbody>
{foreach $drj.gl as $g}<tr><td class="acc-code">{$g.code}</td><td>{$g.name}</td><td class="num">{displayPrice price=$g.amount}</td></tr>{foreachelse}<tr><td colspan="3"><em>Nothing posted for this date</em></td></tr>{/foreach}
</tbody></table></div>
<div class="col-md-6"><h4>Settlement and ledger movement</h4><table class="table table-condensed"><tbody>
{foreach $drj.settlements as $s}<tr><td class="acc-code">{$s.account_code}</td><td>{$s.account_name}</td><td class="num">{displayPrice price=$s.amount}</td></tr>{/foreach}
</tbody></table></div></div>
</div>

<div class="tab-pane" id="a-cash">
<table class="table table-condensed" style="max-width:720px"><thead><tr><th>Account</th><th>Type</th><th>GL</th><th class="num">Balance</th></tr></thead><tbody>
{foreach $d.cash as $c}<tr><td>{$c.name}</td><td>{$c.type}</td><td class="acc-code">{$c.account_code}</td><td class="num">{displayPrice price=$c.balance}</td></tr>{/foreach}
<tr class="pl-total"><td colspan="3">Total cash and bank</td><td class="num">{displayPrice price=$d.cash_total}</td></tr>
</tbody></table>
<p><a class="btn btn-default btn-xs" href="{$link_bank}">Banking &amp; reconciliation</a></p>
</div>

<div class="tab-pane" id="a-ar">
<div class="row"><div class="col-md-6"><h4>Receivables <a class="btn btn-xs btn-default" href="{$link_ar}">open</a></h4>
<table class="table table-condensed"><tbody>
<tr><td>Current</td><td class="num">{displayPrice price=$d.ar.b_current}</td></tr><tr><td>1–30 days</td><td class="num">{displayPrice price=$d.ar.b_30}</td></tr>
<tr><td>31–60 days</td><td class="num">{displayPrice price=$d.ar.b_60}</td></tr><tr><td>61–90 days</td><td class="num">{displayPrice price=$d.ar.b_90}</td></tr>
<tr class="{if $d.ar.b_over > 0}danger{/if}"><td>Over 90 days</td><td class="num">{displayPrice price=$d.ar.b_over}</td></tr>
<tr class="pl-total"><td>Total</td><td class="num">{displayPrice price=$d.ar.total}</td></tr></tbody></table>
{if $stop_list}<h4 class="text-danger">Stop list</h4><table class="table table-condensed"><tbody>
{foreach $stop_list as $s}<tr class="danger"><td>{$s.company_name}</td><td class="num">{displayPrice price=$s.total}</td><td>{$s.reasons}</td></tr>{/foreach}</tbody></table>{/if}
</div>
<div class="col-md-6"><h4>Payables <a class="btn btn-xs btn-default" href="{$link_ap}">open</a></h4>
<table class="table table-condensed"><tbody>
<tr><td>Current</td><td class="num">{displayPrice price=$d.ap.b_current}</td></tr><tr><td>1–30 days</td><td class="num">{displayPrice price=$d.ap.b_30}</td></tr>
<tr><td>31–60 days</td><td class="num">{displayPrice price=$d.ap.b_60}</td></tr><tr><td>61–90 days</td><td class="num">{displayPrice price=$d.ap.b_90}</td></tr>
<tr class="{if $d.ap.b_over > 0}danger{/if}"><td>Over 90 days</td><td class="num">{displayPrice price=$d.ap.b_over}</td></tr>
<tr class="pl-total"><td>Total</td><td class="num">{displayPrice price=$d.ap.total}</td></tr></tbody></table></div></div>
</div>

<div class="tab-pane" id="a-recent">
<table class="table table-condensed"><thead><tr><th>Journal</th><th>Date</th><th>Type</th><th>Source</th><th>Memo</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
{foreach $recent as $j}<tr><td><a href="{$link_journals}&amp;id_journal={$j.id_pulse_acc_journal}">{$j.journal_no}</a></td><td>{$j.business_date}</td><td>{$j.type}</td><td>{$j.source}</td><td>{$j.memo|escape}</td><td class="num">{displayPrice price=$j.total_debit}</td><td>{$j.status}</td></tr>{/foreach}
</tbody></table>
<p><a class="btn btn-default btn-xs" href="{$link_journals}">All journals</a> <a class="btn btn-default btn-xs" href="{$link_reports}">Reports</a></p>
</div>

{if $d.unmapped}<div class="tab-pane" id="a-unmapped">
<p class="alert alert-warning">These will not post until a rule exists. Set them up under <a href="{$link_rules}">Posting Rules</a>, then retry the queue.</p>
<table class="table table-condensed"><thead><tr><th>Kind</th><th>Key</th><th>What it is</th><th>What it needs</th></tr></thead><tbody>
{foreach $unmapped as $u}<tr><td>{$u.kind}</td><td><strong>{$u.key|escape}</strong></td><td>{$u.label|escape}</td><td class="muted">{$u.hint}</td></tr>{/foreach}
</tbody></table></div>{/if}

</div></div>

{if !$fd && !$pos && !$inv && !$rpt}<div class="alert alert-info">No other Pulse modules are enabled, so nothing posts automatically yet. The chart of accounts, manual journals, AR, AP, banking and fixed assets all work on their own.</div>{/if}
</div>
