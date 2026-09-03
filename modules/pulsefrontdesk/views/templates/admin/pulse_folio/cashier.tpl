<div class="pulse-fd"><div class="panel"><h3><i class="icon-money"></i> Cashier shift</h3>
{if $session}
  <p>Shift open since <strong>{$session.date_open}</strong> &middot; opening float {displayPrice price=$session.opening_float} &middot; business date {$session.business_date}</p>
  <table class="table" style="width:auto"><thead><tr><th>Method</th><th>Count</th><th class="text-right">Total</th></tr></thead><tbody>{foreach $totals as $t}<tr><td>{$t.payment_method}</td><td>{$t.n}</td><td class="text-right">{displayPrice price=$t.total}</td></tr>{foreachelse}<tr><td colspan="3"><em>No payments taken yet</em></td></tr>{/foreach}</tbody></table>
  <h4>Drawer movements</h4>
  <table class="table table-condensed" style="width:auto"><tbody>{foreach $movements as $m}<tr><td>{$m.date_add|date_format:"%H:%M"}</td><td>{$m.type|replace:'_':' '}</td><td class="text-right">{displayPrice price=$m.amount}</td><td>{$m.note}</td><td><small>{$m.who}{if $m.witness} · witness #{$m.witness}{/if}</small></td></tr>{/foreach}</tbody></table>
  <form method="post" class="form-inline"><select name="type" class="form-control"><option value="blind_drop">Blind drop (to safe)</option><option value="paid_out">Paid out</option><option value="float_in">Float in</option><option value="float_out">Float out</option><option value="correction">Correction</option></select> <input name="amount" type="number" step="0.01" class="form-control" required> <input name="note" class="form-control" placeholder="Note / envelope no."> <select name="witness" class="form-control"><option value="">Witness (optional)</option>{foreach $employees as $e}<option value="{$e.id_employee}">{$e.firstname} {$e.lastname}</option>{/foreach}</select> <button name="drawerMove" class="btn btn-default">Record</button></form>
  <hr>
  <form method="post" class="form-inline"><label>Cash counted in drawer</label> <input name="counted_cash" type="number" step="0.01" class="form-control" required> <input name="note" class="form-control" placeholder="Note"> <button name="closeShift" class="btn btn-primary" onclick="return confirm('Close shift? Variance will be recorded.')">Close shift</button></form>
{else}
  <form method="post" class="form-inline"><label>Opening float</label> <input name="opening_float" type="number" step="0.01" class="form-control" value="0"> <button name="openShift" class="btn btn-success">Open shift</button></form>
  <p class="help-block">Payments you record are tied to your open shift. Night audit cannot close while a shift is open.</p>
{/if}
</div></div>
