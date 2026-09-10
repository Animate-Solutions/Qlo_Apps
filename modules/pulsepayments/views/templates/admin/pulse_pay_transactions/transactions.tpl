<div class="pulse-pay"><div class="panel"><h3><i class="icon-list"></i> Transactions</h3>
<form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulsePayTransactions"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<input type="date" name="from" value="{$f.from}" class="form-control input-sm"> <input type="date" name="to" value="{$f.to}" class="form-control input-sm">
<select name="gateway" class="form-control input-sm"><option value="">All gateways</option>{foreach $gateways as $g}<option value="{$g.code}" {if $f.gateway==$g.code}selected{/if}>{$g.name}</option>{/foreach}</select>
<select name="state" class="form-control input-sm"><option value="">Any state</option>{foreach $states as $s}<option value="{$s}" {if $f.state==$s}selected{/if}>{$s}</option>{/foreach}</select>
<select name="channel" class="form-control input-sm"><option value="">Any channel</option>{foreach $channels as $c}<option value="{$c}" {if $f.channel==$c}selected{/if}>{$c}</option>{/foreach}</select>
<select name="type" class="form-control input-sm"><option value="">Any type</option>{foreach $types as $t}<option value="{$t}" {if $f.type==$t}selected{/if}>{$t}</option>{/foreach}</select>
<input name="q" value="{$f.q|escape}" class="form-control input-sm" placeholder="reference, RRN, guest"> <button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Reference</th><th>Date</th><th>Gateway</th><th>Type</th><th>Channel</th><th>Method</th><th>Room</th><th>Guest</th><th class="text-right">Amount</th><th class="text-right">Captured</th><th class="text-right">Fee</th><th>State</th></tr></thead><tbody>
{foreach $rows as $t}<tr class="{if $t.state=='failed'}danger{elseif $t.state=='awaiting_confirmation' || $t.state=='intent'}warning{elseif $t.state=='settled'}success{/if}">
<td><a href="{$self_url}&reference={$t.reference}">{$t.reference}</a>{if $t.rrn}<br><small class="text-muted">RRN {$t.rrn}</small>{/if}</td>
<td>{$t.date_add|date_format:"%d/%m %H:%M"}</td><td>{$t.gateway}</td><td>{$t.type}</td><td>{$t.channel}</td><td>{$t.method}</td><td>{$t.room_num}</td><td>{$t.customer_name}</td>
<td class="text-right">{displayPrice price=$t.amount}</td><td class="text-right">{displayPrice price=$t.amount_captured}</td><td class="text-right">{displayPrice price=$t.fee}</td><td>{$t.state}</td></tr>
{foreachelse}<tr><td colspan="12"><em>No transactions in that window</em></td></tr>{/foreach}</tbody></table>

<h4>Disputes &amp; chargebacks</h4>
<form method="post" class="form-inline noprint"><input name="reference" class="input-sm" placeholder="Payment reference" required> <select name="category" class="input-sm"><option value="chargeback">chargeback</option><option value="fraud">fraud</option><option value="service">service</option><option value="duplicate">duplicate</option><option value="other">other</option></select>
<input name="amount" type="number" step="0.01" class="input-sm" style="width:110px" placeholder="Amount"> <input name="dispute_ref" class="input-sm" placeholder="Bank ref"> <input type="date" name="due_at" class="input-sm"> <input name="reason" class="input-sm" placeholder="Reason"> <button name="addDispute" class="btn btn-xs btn-default">Log dispute</button></form>
<table class="table table-condensed"><thead><tr><th>Raised</th><th>Payment</th><th>Category</th><th class="text-right">Amount</th><th>Reason</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $disputes as $x}<tr class="{if $x.status=='open'}warning{elseif $x.status=='lost'}danger{elseif $x.status=='won'}success{/if}"><td>{$x.date_add|date_format:"%d/%m"}</td><td><a href="{$self_url}&reference={$x.reference}">{$x.reference}</a></td><td>{$x.category}</td><td class="text-right">{displayPrice price=$x.amount}</td><td>{$x.reason}</td><td>{$x.due_at|date_format:"%d/%m"}</td><td>{$x.status}</td>
<td class="noprint"><form method="post" class="form-inline"><input type="hidden" name="id_dispute" value="{$x.id_pulse_pay_dispute}"><select name="status" class="input-sm"><option value="open">open</option><option value="evidence_sent">evidence sent</option><option value="won">won</option><option value="lost">lost</option><option value="cancelled">cancelled</option></select><input name="evidence" class="input-sm" placeholder="Evidence note"><button name="setDispute" class="btn btn-xs btn-default">Save</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em>No disputes — long may it last</em></td></tr>{/foreach}</tbody></table>
</div></div>
