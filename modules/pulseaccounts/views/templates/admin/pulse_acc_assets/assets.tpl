<div class="pulse-acc"><div class="panel"><h3><i class="icon-building"></i> Fixed assets</h3>
<div class="row">
<div class="col-md-3"><div class="tile"><span class="k">Cost</span><span class="v">{displayPrice price=$wdv.totals.cost}</span></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Accumulated depreciation</span><span class="v">{displayPrice price=$wdv.totals.accum}</span></div></div>
<div class="col-md-3"><div class="tile ok"><span class="k">Net book value</span><span class="v">{displayPrice price=$wdv.totals.nbv}</span></div></div>
<div class="col-md-3"><div class="tile"><span class="k">Assets on the register</span><span class="v">{$wdv.rows|count}</span></div></div>
</div>

<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#f-reg">Register</a></li><li><a data-toggle="tab" href="#f-new">Capitalise an asset</a></li>
<li><a data-toggle="tab" href="#f-dep">Depreciation run</a></li><li><a data-toggle="tab" href="#f-fc">Forecast</a></li>
<li><a data-toggle="tab" href="#f-capex">CAPEX vs budget</a></li><li><a data-toggle="tab" href="#f-cls">Classes</a></li>
{if $mnt && $unlinked}<li><a data-toggle="tab" href="#f-link" class="text-danger">Not capitalised ({$unlinked|count})</a></li>{/if}
</ul>
<div class="tab-content">

<div class="tab-pane active" id="f-reg">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAssets"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="class" class="form-control"><option value="">every class</option>{foreach $classes as $c}<option value="{$c.code}" {if $smarty.get.class == $c.code}selected{/if}>{$c.name|escape}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">in service</option>{foreach ['in_service','idle','under_repair','held_for_sale','disposed','written_off'] as $s}<option value="{$s}" {if $smarty.get.status == $s}selected{/if}>{$s}</option>{/foreach}</select>
<input name="q" class="form-control" placeholder="code, name, serial, location" value="{$smarty.get.q|escape}">
<label><input type="checkbox" name="include_disposed" value="1" {if $smarty.get.include_disposed}checked{/if}> include disposed</label>
<button class="btn btn-default">Search</button> <a class="btn btn-default" href="{$self_url}&amp;export=wdv">Export WDV register</a></form>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Asset</th><th>Class</th><th>Location</th><th>Cost centre</th><th>In service</th><th>Method</th><th class="num">Cost</th><th class="num">Accum</th><th class="num">NBV</th><th>Engineering</th></tr></thead><tbody>
{foreach $assets as $a}<tr class="{if $a.status == 'disposed' || $a.status == 'written_off'}muted{/if}">
<td><a href="{$self_url}&amp;id_asset={$a.id_pulse_acc_asset}">{$a.code}</a></td><td>{$a.name|escape}</td><td>{$a.class_code}</td>
<td>{$a.location|escape|default:$a.room_num}</td><td>{$a.cost_centre}</td><td>{$a.in_service_date}</td>
<td>{$a.method|replace:'_':' '}{if $a.method == 'reducing_balance'} {$a.rate_pct}%{elseif $a.method == 'straight_line'} {$a.life_months}m{/if}</td>
<td class="num">{displayPrice price=$a.cost}</td><td class="num">{displayPrice price=$a.accum_depreciation}</td><td class="num">{displayPrice price=$a.nbv}</td>
<td>{if $a.eng_name}<span class="badge" title="{$a.eng_name|escape} · {$a.eng_serial|escape}">linked</span>{else}<span class="muted">—</span>{/if}</td></tr>
{foreachelse}<tr><td colspan="11"><em>No assets on the register yet</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="f-new">
<form method="post" class="form-horizontal" style="max-width:900px">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Class</label><div class="col-sm-8"><select name="class_code" class="form-control" required>{foreach $classes as $c}{if $c.active}<option value="{$c.code}">{$c.name|escape} — {$c.method|replace:'_':' '}{if $c.method == 'straight_line'} {$c.life_months}m{elseif $c.method == 'reducing_balance'} {$c.rate_pct}%{/if}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Name</label><div class="col-sm-8"><input name="name" class="form-control" required placeholder="100 kVA Perkins generator"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Code</label><div class="col-sm-8"><input name="code" class="form-control" placeholder="leave blank to number it automatically"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Cost</label><div class="col-sm-8"><input name="cost" type="number" step="0.01" class="form-control num" required></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Residual value</label><div class="col-sm-8"><input name="residual_value" type="number" step="0.01" class="form-control num" placeholder="from the class percentage if blank"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Acquired / in service</label><div class="col-sm-4"><input type="date" name="acquisition_date" class="form-control" value="{$business_date}"></div>
<div class="col-sm-4"><input type="date" name="in_service_date" class="form-control" value="{$business_date}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Paid from</label><div class="col-sm-8"><select name="credit_account" class="form-control">
<option value="2110">2110 Trade payables (invoice to follow)</option>{foreach $accounts as $a}{if $a.subtype == 'cash'}<option value="{$a.code}">{$a.code} {$a.name|escape}</option>{/if}{/foreach}</select></div></div>
</div>
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4 control-label">Method override</label><div class="col-sm-8"><select name="method" class="form-control"><option value="">use the class default</option>
<option value="straight_line">straight line</option><option value="reducing_balance">reducing balance</option><option value="units_of_production">units of production</option><option value="none">not depreciated</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Life / rate / units</label><div class="col-sm-3"><input name="life_months" type="number" class="form-control" placeholder="months"></div>
<div class="col-sm-2"><input name="rate_pct" type="number" step="0.1" class="form-control" placeholder="%"></div>
<div class="col-sm-3"><input name="units_total" type="number" step="0.01" class="form-control" placeholder="total units"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Cost centre</label><div class="col-sm-8"><select name="cost_centre" class="form-control">{foreach $departments as $d}<option value="{$d.key_value}">{$d.key_value}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Room / location</label><div class="col-sm-4"><select name="id_room" class="form-control"><option value="">—</option>{foreach $rooms as $r}<option value="{$r.id_room}">{$r.room_num}</option>{/foreach}</select></div>
<div class="col-sm-4"><input name="location" class="form-control" placeholder="plant room, roof…"></div></div>
{if $mnt}<div class="form-group"><label class="col-sm-4 control-label">Engineering asset</label><div class="col-sm-8"><select name="id_pulse_asset" class="form-control"><option value="">— not linked —</option>
{foreach $unlinked as $u}<option value="{$u.id_pulse_asset}">{$u.code} {$u.name|escape}{if $u.serial_no} ({$u.serial_no|escape}){/if}</option>{/foreach}</select>
<span class="help-block">Links to the maintenance register — make, model, serial, warranty and work orders stay there.</span></div></div>{/if}
<div class="form-group"><label class="col-sm-4 control-label">Supplier / invoice</label><div class="col-sm-4"><input name="supplier" class="form-control"></div><div class="col-sm-4"><input name="invoice_ref" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Serial / CAPEX line</label><div class="col-sm-4"><input name="serial_no" class="form-control"></div><div class="col-sm-4"><input name="capex_budget_line" class="form-control" placeholder="capex:GEN"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-8"><input name="note" class="form-control"></div></div>
</div></div>
<button name="addAsset" class="btn btn-primary">Capitalise</button>
</form>
</div>

<div class="tab-pane" id="f-dep">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAssets"><input type="hidden" name="token" value="{$smarty.get.token|escape}"><input type="hidden" name="preview" value="1">
<select name="period" class="form-control">{foreach $periods as $p}<option value="{$p.code}" {if $period == $p.code}selected{/if}>{$p.code} ({$p.status})</option>{/foreach}</select>
<button class="btn btn-default">Preview the run</button></form>
{if $preview}
<h4>Preview {$preview.period} — {$preview.assets} assets, {displayPrice price=$preview.total}</h4>
<table class="table table-condensed" style="max-width:520px"><thead><tr><th>Class</th><th class="num">Charge</th></tr></thead><tbody>
{foreach $preview.by_class as $cls => $amt}<tr><td>{$cls}</td><td class="num">{displayPrice price=$amt}</td></tr>{/foreach}
<tr class="pl-total"><td>Total</td><td class="num">{displayPrice price=$preview.total}</td></tr></tbody></table>
<table class="table table-condensed"><thead><tr><th>Asset</th><th>Class</th><th>Method</th><th class="num">Opening NBV</th><th class="num">Charge</th></tr></thead><tbody>
{foreach $preview.rows as $r}<tr><td>{$r.asset.code} {$r.asset.name|escape}</td><td>{$r.asset.class_code}</td><td>{$r.asset.method|replace:'_':' '}</td>
<td class="num">{displayPrice price=$r.opening_nbv}</td><td class="num">{displayPrice price=$r.charge}</td></tr>{/foreach}
</tbody></table>
<form method="post"><input type="hidden" name="period_s" value="{$preview.period}">
<button name="runDepreciation" class="btn btn-primary" onclick="return confirm('Post the depreciation journals for {$preview.period}?')">Post this run</button></form>
{/if}
<h4>Runs already posted</h4>
<table class="table table-condensed" style="max-width:640px"><thead><tr><th>Period</th><th class="num">Assets</th><th class="num">Charge</th><th>Run at</th></tr></thead><tbody>
{foreach $runs as $r}<tr><td>{$r.period}</td><td class="num">{$r.assets}</td><td class="num">{displayPrice price=$r.total}</td><td class="muted">{$r.run_at}</td></tr>
{foreachelse}<tr><td colspan="4"><em>No depreciation posted yet</em></td></tr>{/foreach}
</tbody></table>
<p class="help-block">A period that already has a run is skipped, so a second run — by hand or by cron — changes nothing.</p>
</div>

<div class="tab-pane" id="f-fc">
<a class="btn btn-default btn-xs noprint" href="{$self_url}&amp;export=forecast">Export CSV</a>
<table class="table table-condensed" style="max-width:700px"><thead><tr><th>Period</th><th>By class</th><th class="num">Charge</th></tr></thead><tbody>
{foreach $forecast as $f}<tr><td>{$f.period}</td><td class="muted">{foreach $f.by_class as $c => $v}{$c} {$v|string_format:"%.0f"} {/foreach}</td><td class="num">{displayPrice price=$f.total}</td></tr>{/foreach}
</tbody></table>
<p class="help-block">Twelve months ahead on today's register — this is the depreciation line the budget needs.</p>
</div>

<div class="tab-pane" id="f-capex">
<form method="get" class="form-inline noprint" style="margin-bottom:8px"><input type="hidden" name="controller" value="AdminPulseAccAssets"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<input name="year" type="number" class="form-control" value="{$year}" style="width:100px"><button class="btn btn-default">Show</button>
<a class="btn btn-default" href="{$self_url}&amp;year={$year}&amp;export=capex">Export CSV</a></form>
<table class="table table-condensed" style="max-width:700px"><thead><tr><th>Class</th><th class="num">Budget</th><th class="num">Spent</th><th class="num">Items</th><th class="num">Variance</th><th class="num">Used</th></tr></thead><tbody>
{foreach $capex as $c}<tr class="{if $c.variance < 0}danger{/if}"><td>{$c.class}</td><td class="num">{displayPrice price=$c.budget}</td><td class="num">{displayPrice price=$c.spend}</td>
<td class="num">{$c.items}</td><td class="num">{displayPrice price=$c.variance}</td><td class="num">{if $c.used_pct !== null}{$c.used_pct}%{/if}</td></tr>
{foreachelse}<tr><td colspan="6"><em>No CAPEX budget lines (<code>capex:&lt;class&gt;</code> in Pulse Reports) and no additions this year</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="f-cls">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Method</th><th class="num">Life</th><th class="num">Rate</th><th class="num">Residual</th><th>Asset</th><th>Accum</th><th>Expense</th><th>Capital allowance note</th></tr></thead><tbody>
{foreach $classes as $c}<tr class="{if !$c.active}muted{/if}"><td>{$c.code}</td><td>{$c.name|escape}</td><td>{$c.method|replace:'_':' '}</td>
<td class="num">{if $c.life_months}{$c.life_months}m{/if}</td><td class="num">{if $c.rate_pct}{$c.rate_pct}%{/if}</td><td class="num">{$c.residual_pct}%</td>
<td class="acc-code">{$c.asset_account}</td><td class="acc-code">{$c.accum_account}</td><td class="acc-code">{$c.expense_account}</td><td class="muted">{$c.capital_allowance_note|escape}</td></tr>{/foreach}
</tbody></table>
<h4>Add or edit a class</h4>
<form method="post" class="form-inline">
<input name="id_class" type="hidden" value="">
<input name="c_code" class="form-control" placeholder="code" style="width:90px" required>
<input name="c_name" class="form-control" placeholder="name" style="width:200px" required>
<select name="c_method" class="form-control"><option value="straight_line">straight line</option><option value="reducing_balance">reducing balance</option><option value="units_of_production">units of production</option><option value="none">none</option></select>
<input name="c_life" type="number" class="form-control" placeholder="months" style="width:90px">
<input name="c_rate" type="number" step="0.1" class="form-control" placeholder="rate %" style="width:80px">
<input name="c_residual" type="number" step="0.1" class="form-control" placeholder="residual %" style="width:90px">
<select name="c_asset" class="form-control">{foreach $accounts as $a}{if $a.subtype == 'fixed_asset'}<option value="{$a.code}">{$a.code}</option>{/if}{/foreach}</select>
<select name="c_accum" class="form-control">{foreach $accounts as $a}{if $a.subtype == 'accum_depreciation'}<option value="{$a.code}">{$a.code}</option>{/if}{/foreach}</select>
<select name="c_expense" class="form-control">{foreach $accounts as $a}{if $a.subtype == 'depreciation'}<option value="{$a.code}">{$a.code}</option>{/if}{/foreach}</select>
<input name="c_note" class="form-control" placeholder="capital allowance note" style="width:260px">
<button name="saveClass" class="btn btn-default">Save class</button>
</form>
</div>

{if $mnt && $unlinked}<div class="tab-pane" id="f-link">
<p class="alert alert-warning">These are on the engineering register in Pulse Maintenance with a purchase cost, but have no financial record — so they are not in the balance sheet and nothing is depreciating. Capitalise them from the tab above, picking each one in the "Engineering asset" list.</p>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Asset</th><th>Category</th><th>Location</th><th>Installed</th><th class="num">Purchase cost</th></tr></thead><tbody>
{foreach $unlinked as $u}<tr><td>{$u.code}</td><td>{$u.name|escape}</td><td>{$u.category}</td><td>{$u.location|escape|default:$u.room_num}</td><td>{$u.installed_on}</td><td class="num">{displayPrice price=$u.purchase_cost}</td></tr>{/foreach}
</tbody></table>
</div>{/if}

</div></div></div>
