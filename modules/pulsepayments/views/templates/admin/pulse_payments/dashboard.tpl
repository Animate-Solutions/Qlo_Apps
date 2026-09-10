<div class="pulse-pay"><div class="panel"><h3><i class="icon-credit-card"></i> Payments — {$business_date}</h3>
<form method="get" class="form-inline pull-right noprint" style="margin-top:-34px"><input type="hidden" name="controller" value="AdminPulsePayments"><input type="hidden" name="token" value="{$smarty.get.token|escape}"><input type="date" name="bdate" value="{$business_date}" class="form-control input-sm"> <button class="btn btn-default btn-sm">Go</button></form>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#p-take">Takings</a></li><li><a data-toggle="tab" href="#p-pre">Pre-auths ({$d.preauths|count})</a></li><li><a data-toggle="tab" href="#p-term">Terminal ({$d.terminal|count})</a></li><li><a data-toggle="tab" href="#p-fail">Needs attention ({$d.failures|count} / {$d.pending|count})</a></li><li><a data-toggle="tab" href="#p-hook">Webhooks</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="p-take">
<div class="row">
  <div class="col-md-3"><div class="pp-kpi"><span>Gross</span><b>{displayPrice price=$d.totals.gross}</b></div></div>
  <div class="col-md-3"><div class="pp-kpi"><span>Gateway fees</span><b>{displayPrice price=$d.totals.fee}</b></div></div>
  <div class="col-md-3"><div class="pp-kpi"><span>Net expected</span><b>{displayPrice price=$d.totals.net}</b></div></div>
  <div class="col-md-3"><div class="pp-kpi {if $d.totals.refunds > 0}pp-warn{/if}"><span>Refunds</span><b>{displayPrice price=$d.totals.refunds}</b></div></div>
</div>
<div class="row"><div class="col-md-6">
<h4>By gateway</h4><table class="table table-condensed"><thead><tr><th>Gateway</th><th class="text-right">Txns</th><th class="text-right">Gross</th><th class="text-right">Fee</th><th class="text-right">Net</th></tr></thead><tbody>
{foreach $d.by_gateway as $g}<tr><td>{$g.gateway}</td><td class="text-right">{$g.n}</td><td class="text-right">{displayPrice price=$g.gross}</td><td class="text-right">{displayPrice price=$g.fee}</td><td class="text-right">{displayPrice price=$g.net}</td></tr>
{foreachelse}<tr><td colspan="5"><em>Nothing taken yet today</em></td></tr>{/foreach}</tbody></table></div>
<div class="col-md-6"><h4>By channel</h4><table class="table table-condensed"><thead><tr><th>Channel</th><th>Method</th><th class="text-right">Txns</th><th class="text-right">Gross</th></tr></thead><tbody>
{foreach $d.by_channel as $c}<tr><td>{$c.channel}</td><td>{$c.method}</td><td class="text-right">{$c.n}</td><td class="text-right">{displayPrice price=$c.gross}</td></tr>
{foreachelse}<tr><td colspan="4"><em>—</em></td></tr>{/foreach}</tbody></table>
<form method="post" class="noprint"><input type="hidden" name="bdate" value="{$business_date}"><button name="rollDaily" class="btn btn-default btn-xs">Roll settlement summary for {$business_date}</button></form></div></div>
{if $d.disputes}<h4>Open disputes</h4><table class="table table-condensed"><tbody>{foreach $d.disputes as $x}<tr class="danger"><td>{$x.reference}</td><td>{$x.category}</td><td>{displayPrice price=$x.amount}</td><td>{$x.reason}</td><td>due {$x.due_at}</td><td><a class="btn btn-xs btn-default" href="{$tx_url}&reference={$x.reference}">Open</a></td></tr>{/foreach}</tbody></table>{/if}
</div>

<div class="tab-pane" id="p-pre">
<table class="table table-condensed"><thead><tr><th>Reference</th><th>Room</th><th>Guest</th><th>Gateway</th><th>Hold</th><th class="text-right">Held</th><th class="text-right">Captured</th><th class="text-right">Folio balance</th><th>Expires</th><th></th></tr></thead><tbody>
{foreach $d.preauths as $p}<tr class="{if $p.hours_left < 0}danger{elseif $p.hours_left < $warn_hours}warning{/if}">
<td><a href="{$tx_url}&reference={$p.reference}">{$p.reference}</a></td><td>{$p.room_num}</td><td>{$p.customer_name}</td><td>{$p.gateway}</td><td>{$p.hold_type}</td>
<td class="text-right">{displayPrice price=$p.amount}</td><td class="text-right">{displayPrice price=$p.amount_captured}</td><td class="text-right">{if $p.folio_no}{displayPrice price=$p.folio_balance}{else}—{/if}</td>
<td>{$p.expires_at|date_format:"%d/%m %H:%M"}{if $p.hours_left >= 0} <small class="text-muted">{$p.hours_left}h</small>{else} <b>expired</b>{/if}</td>
<td class="noprint"><form method="post" class="form-inline"><input type="hidden" name="reference" value="{$p.reference}">
<input name="amount" type="number" step="0.01" class="input-sm" style="width:100px" value="{if $p.folio_balance > 0}{$p.folio_balance}{else}{$p.remaining}{/if}">
<input name="rrn" class="input-sm" style="width:90px" placeholder="RRN">
<button name="capturePre" class="btn btn-xs btn-primary">Capture</button>
<button name="topUpPre" class="btn btn-xs btn-default" title="Add the amount above to the hold">Top up</button>
<button name="voidPre" class="btn btn-xs btn-link" onclick="return confirm('Release this hold?')">Release</button></form></td></tr>
{foreachelse}<tr><td colspan="10"><em>No open card holds</em></td></tr>{/foreach}</tbody></table>
<p class="help-block">A hold of type <b>manual</b> is a signed authority slip in the drawer — capturing it means putting the card through the bank terminal and keying the RRN.</p>
</div>

<div class="tab-pane" id="p-term">
<div class="row"><div class="col-md-4"><h4>Send an amount to a terminal</h4><form method="post" class="form-horizontal">
<div class="form-group"><label class="col-sm-4">Terminal</label><div class="col-sm-8"><select name="terminal" class="form-control">{foreach $terminals as $t}<option value="{$t.code}">{$t.label} — {$t.bank} ({$t.mode})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Amount</label><div class="col-sm-8"><input name="amount" type="number" step="0.01" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4">Folio</label><div class="col-sm-8"><input name="id_pulse_folio" type="number" class="form-control" placeholder="id_pulse_folio (optional)"></div></div>
<div class="form-group"><label class="col-sm-4">Note</label><div class="col-sm-8"><input name="description" class="form-control" placeholder="Room 214 balance"></div></div>
<button name="terminalPush" class="btn btn-primary">Send to terminal</button></form></div>
<div class="col-md-8"><h4>Today's terminal queue</h4><table class="table table-condensed"><thead><tr><th>Reference</th><th>Terminal</th><th class="text-right">Amount</th><th>Status</th><th>RRN</th><th></th></tr></thead><tbody>
{foreach $d.terminal as $r}<tr class="{if $r.status=='approved'}success{elseif $r.status=='declined' || $r.status=='expired'}danger{elseif $r.status=='queued'}warning{/if}">
<td>{$r.reference}</td><td>{$r.terminal_label|default:'—'}</td><td class="text-right">{displayPrice price=$r.amount}</td><td>{$r.status}</td><td>{$r.rrn}</td>
<td class="noprint">{if $r.status=='queued' || $r.status=='claimed'}<form method="post" class="form-inline"><input type="hidden" name="reference" value="{$r.reference}"><input type="hidden" name="approved" value="1">
<input name="rrn" class="input-sm" style="width:100px" placeholder="RRN" required><input name="auth_code" class="input-sm" style="width:70px" placeholder="Auth"><input name="card_last4" class="input-sm" style="width:60px" placeholder="L4">
<button name="terminalAnswer" class="btn btn-xs btn-success">Approved</button><button name="terminalCancel" class="btn btn-xs btn-link">✕</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No terminal traffic today</em></td></tr>{/foreach}</tbody></table>
<p class="help-block">Terminal offline? Swipe on the bank POS as usual and key the RRN, auth code and last four here — the folio still balances.</p></div></div>
</div>

<div class="tab-pane" id="p-fail">
<h4>Failed or waiting for confirmation</h4><table class="table table-condensed"><thead><tr><th>Reference</th><th>Gateway</th><th>Channel</th><th class="text-right">Amount</th><th>State</th><th>Reason</th><th></th></tr></thead><tbody>
{foreach $d.failures as $t}<tr class="{if $t.state=='failed'}danger{else}warning{/if}"><td><a href="{$tx_url}&reference={$t.reference}">{$t.reference}</a></td><td>{$t.gateway}</td><td>{$t.channel}</td><td class="text-right">{displayPrice price=$t.amount}</td><td>{$t.state}</td><td><small>{$t.failed_reason}</small></td>
<td class="noprint"><form method="post" class="inline"><input type="hidden" name="reference" value="{$t.reference}"><button name="verifyTx" class="btn btn-xs btn-default">Ask the gateway</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em>Nothing needs attention</em></td></tr>{/foreach}</tbody></table>
<h4>Still pending (guest may have abandoned checkout)</h4><table class="table table-condensed"><tbody>
{foreach $d.pending as $t}<tr><td><a href="{$tx_url}&reference={$t.reference}">{$t.reference}</a></td><td>{$t.gateway}</td><td>{$t.channel}</td><td class="text-right">{displayPrice price=$t.amount}</td><td>{$t.date_add|date_format:"%d/%m %H:%M"}</td>
<td class="noprint"><form method="post" class="inline"><input type="hidden" name="reference" value="{$t.reference}"><button name="verifyTx" class="btn btn-xs btn-default">Verify</button></form></td></tr>
{foreachelse}<tr><td><em>—</em></td></tr>{/foreach}</tbody></table>
</div>

<div class="tab-pane" id="p-hook">
<table class="table table-condensed"><thead><tr><th>When</th><th>Gateway</th><th>Event</th><th>Reference</th><th>Signature</th><th>Handled</th><th>Result</th></tr></thead><tbody>
{foreach $events as $e}<tr class="{if !$e.signature_ok}danger{elseif !$e.handled}warning{/if}"><td>{$e.date_add|date_format:"%d/%m %H:%M"}</td><td>{$e.gateway}</td><td>{$e.event_type}</td><td>{$e.reference}</td><td>{if $e.signature_ok}ok{else}<b>failed</b>{/if}</td><td>{if $e.handled}yes{else}no{/if}</td><td><small>{$e.result}</small></td></tr>
{foreachelse}<tr><td colspan="7"><em>No webhook deliveries yet — check the URLs in Payment Settings are pasted into the gateway dashboard</em></td></tr>{/foreach}</tbody></table>
</div>

</div></div></div>
