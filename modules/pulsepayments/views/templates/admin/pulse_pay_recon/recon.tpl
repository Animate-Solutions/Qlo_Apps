<div class="pulse-pay"><div class="panel"><h3><i class="icon-balance-scale"></i> Settlement reconciliation</h3>
<div class="row"><div class="col-md-5"><form method="post" enctype="multipart/form-data" class="form-inline noprint">
<select name="gateway" class="form-control input-sm">{foreach $gateways as $g}<option value="{$g.code}">{$g.name}</option>{/foreach}</select>
<input type="file" name="statement" accept=".csv,text/csv" class="input-sm"> <button name="importStatement" class="btn btn-primary btn-sm">Import statement</button></form>
<p class="help-block">Export the settlement report from the gateway dashboard as CSV. Columns are matched by name, so Paystack, Flutterwave and Interswitch exports all import as they come.</p></div>
<div class="col-md-7"><table class="table table-condensed"><thead><tr><th>Imported</th><th>Gateway</th><th>File</th><th>Period</th><th class="text-right">Rows</th><th class="text-right">Gross</th><th class="text-right">Fees</th><th class="text-right">Net</th><th>Match</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $settlements as $x}<tr class="{if $x.rows_unmatched > 0}warning{elseif $x.status=='closed'}success{/if}"><td>{$x.date_add|date_format:"%d/%m %H:%M"}</td><td>{$x.gateway}</td><td><a href="{$self_url}&id_settlement={$x.id_pulse_pay_settlement}">{$x.filename}</a></td><td>{$x.period_from} → {$x.period_to}</td>
<td class="text-right">{$x.rows_total}</td><td class="text-right">{displayPrice price=$x.gross_total}</td><td class="text-right">{displayPrice price=$x.fee_total}</td><td class="text-right">{displayPrice price=$x.net_total}</td>
<td>{$x.rows_matched} ok · {$x.rows_variance} var · {$x.rows_unmatched} miss</td><td>{$x.status}{if $x.fee_expense_posted} · fees posted{/if}</td>
<td class="noprint"><form method="post" class="inline"><input type="hidden" name="id_settlement_s" value="{$x.id_pulse_pay_settlement}"><button name="deleteStatement" class="btn btn-xs btn-link" onclick="return confirm('Remove this statement and its lines?')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="11"><em>No statements imported yet</em></td></tr>{/foreach}</tbody></table></div></div>

{if $s}
<hr><h4>{$s.filename} — {$s.gateway} <small>{$s.period_from} → {$s.period_to}</small></h4>
<form method="post" class="form-inline noprint"><input type="hidden" name="id_settlement_s" value="{$s.id_pulse_pay_settlement}">
<button name="rematch" class="btn btn-default btn-sm">Re-match</button>
{if $rpt}<button name="postFees" class="btn btn-default btn-sm" {if $s.fee_expense_posted}disabled{/if}>Post {displayPrice price=$s.fee_total} of fees to the expense ledger (BANK)</button>{else}<span class="help-block">Install Pulse Reports to post gateway fees as an expense.</span>{/if}
</form>
<ul class="nav nav-tabs"><li class="{if !$match_state}active{/if}"><a href="{$self_url}&id_settlement={$s.id_pulse_pay_settlement}">All ({$s.rows_total})</a></li>
<li class="{if $match_state=='matched'}active{/if}"><a href="{$self_url}&id_settlement={$s.id_pulse_pay_settlement}&match_state=matched">Matched ({$s.rows_matched})</a></li>
<li class="{if $match_state=='fee_variance'}active{/if}"><a href="{$self_url}&id_settlement={$s.id_pulse_pay_settlement}&match_state=fee_variance">Fee variance</a></li>
<li class="{if $match_state=='amount_variance'}active{/if}"><a href="{$self_url}&id_settlement={$s.id_pulse_pay_settlement}&match_state=amount_variance">Amount variance</a></li>
<li class="{if $match_state=='unmatched'}active{/if}"><a href="{$self_url}&id_settlement={$s.id_pulse_pay_settlement}&match_state=unmatched">Unmatched ({$s.rows_unmatched})</a></li></ul>
<table class="table table-condensed"><thead><tr><th>Gateway ref</th><th>Statement ref</th><th>Paid at</th><th class="text-right">Gross</th><th class="text-right">Fee</th><th class="text-right">Net</th><th>Our transaction</th><th>Match</th><th class="text-right">Variance</th><th>Note</th></tr></thead><tbody>
{foreach $lines as $l}<tr class="{if $l.match_state=='matched'}success{elseif $l.match_state=='unmatched'}danger{else}warning{/if}">
<td>{$l.gateway_ref}</td><td>{$l.reference}</td><td>{$l.paid_at|date_format:"%d/%m %H:%M"}</td>
<td class="text-right">{displayPrice price=$l.gross}</td><td class="text-right">{displayPrice price=$l.fee}</td><td class="text-right">{displayPrice price=$l.net}</td>
<td>{if $l.our_reference}{$l.our_reference} <small class="text-muted">{$l.channel} · {$l.tx_state}</small>{else}<form method="post" class="form-inline noprint"><input type="hidden" name="id_line" value="{$l.id_pulse_pay_settlement_line}"><input name="reference" class="input-sm" style="width:130px" placeholder="our reference"><button name="linkLine" class="btn btn-xs btn-default">Match</button></form>{/if}</td>
<td>{$l.match_state}</td><td class="text-right">{if $l.variance != 0}{displayPrice price=$l.variance}{/if}</td><td><small>{$l.note}</small></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nothing in this bucket</em></td></tr>{/foreach}</tbody></table>

{if $missing}<h4>Captured by us but not on the statement ({$missing|count})</h4>
<table class="table table-condensed"><thead><tr><th>Reference</th><th>Date</th><th>Channel</th><th class="text-right">Amount</th><th>State</th><th>Gateway ref</th></tr></thead><tbody>
{foreach $missing as $m}<tr class="danger"><td>{$m.reference}</td><td>{$m.business_date}</td><td>{$m.channel}</td><td class="text-right">{displayPrice price=$m.amount_captured}</td><td>{$m.state}</td><td>{$m.gateway_ref}</td></tr>{/foreach}</tbody></table>
<p class="help-block">These are either still in the next settlement batch or never actually reached the gateway. Verify each one from Transactions before chasing the bank.</p>{/if}
{/if}
</div></div>
