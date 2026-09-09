<div class="pulse-acc">
{if !$a}<div class="alert alert-danger">That asset does not exist. <a href="{$self_url}">Back to the register</a></div>{else}
<div class="panel"><h3><i class="icon-cube"></i> {$a.code} — {$a.name|escape}
<span class="badge" style="background:{if $a.status == 'in_service'}#27ae60{elseif $a.status == 'disposed' || $a.status == 'written_off'}#7f8c8d{else}#f39c12{/if}">{$a.status|replace:'_':' '}</span></h3>

<div class="row"><div class="col-md-6">
<table class="table table-condensed"><tbody>
<tr><th style="width:180px">Class</th><td>{$a.class_name|escape} ({$a.class_code})</td></tr>
<tr><th>Accounts</th><td class="acc-code">{$a.asset_account} cost · {$a.accum_account} accumulated · {$a.expense_account} charge</td></tr>
<tr><th>Acquired / in service</th><td>{$a.acquisition_date} / {$a.in_service_date}</td></tr>
<tr><th>Cost</th><td class="num">{displayPrice price=$a.cost}</td></tr>
{if $a.revaluation != 0}<tr><th>Revaluation</th><td class="num">{displayPrice price=$a.revaluation}</td></tr>{/if}
{if $a.impairment != 0}<tr><th>Impairment</th><td class="num">{displayPrice price=$a.impairment}</td></tr>{/if}
<tr><th>Residual value</th><td class="num">{displayPrice price=$a.residual_value}</td></tr>
<tr><th>Accumulated depreciation</th><td class="num">{displayPrice price=$a.accum_depreciation}</td></tr>
<tr class="pl-total"><th>Net book value</th><td class="num">{displayPrice price=$a.nbv}</td></tr>
<tr><th>Method</th><td>{$a.method|replace:'_':' '}{if $a.method == 'straight_line'} over {$a.life_months} months{elseif $a.method == 'reducing_balance'} at {$a.rate_pct}% a year{elseif $a.method == 'units_of_production'} — {$a.units_used} of {$a.units_total} units used{/if}</td></tr>
<tr><th>Last depreciated</th><td>{$a.last_period|default:'never'}</td></tr>
{if $a.status == 'disposed' || $a.status == 'written_off'}<tr class="warning"><th>Disposal</th><td>{$a.disposal_date} · proceeds {displayPrice price=$a.disposal_proceeds} · {if $a.disposal_gain_loss >= 0}gain{else}loss{/if} {displayPrice price=$a.disposal_gain_loss}<br><small>{$a.disposal_note|escape}</small></td></tr>{/if}
</tbody></table>
</div>
<div class="col-md-6">
<table class="table table-condensed"><tbody>
<tr><th style="width:180px">Location</th><td>{$a.location|escape}{if $a.room_num} — room {$a.room_num}{/if}</td></tr>
<tr><th>Cost centre</th><td>{$a.cost_centre}</td></tr>
<tr><th>Supplier / invoice</th><td>{$a.supplier|escape} {$a.invoice_ref|escape}</td></tr>
<tr><th>Serial</th><td>{$a.serial_no|escape}</td></tr>
<tr><th>CAPEX budget line</th><td>{$a.capex_budget_line|escape|default:'—'}</td></tr>
<tr><th>Nigerian capital allowance</th><td class="muted">{$a.capital_allowance_note|escape}</td></tr>
{if $a.engineering}<tr class="info"><th>Engineering register</th><td>{$a.engineering.code} {$a.engineering.name|escape}<br>
<small>{$a.engineering.make_model|escape} · serial {$a.engineering.serial_no|escape} · warranty to {$a.engineering.warranty_until|default:'—'} · {$a.engineering.status}</small></td></tr>
{elseif $mnt}<tr><th>Engineering register</th><td class="muted">not linked — work orders and warranty for this asset live in Pulse Maintenance</td></tr>{/if}
{if $a.components}<tr><th>Components</th><td>{foreach $a.components as $c}{$c.code} {$c.name|escape} ({displayPrice price=$c.nbv})<br>{/foreach}</td></tr>{/if}
{if $a.note}<tr><th>Note</th><td>{$a.note|escape}</td></tr>{/if}
</tbody></table>
</div></div>

<ul class="nav nav-tabs noprint"><li class="active"><a data-toggle="tab" href="#s-sched">Depreciation schedule</a></li><li><a data-toggle="tab" href="#s-hist">History</a></li>
<li><a data-toggle="tab" href="#s-edit">Edit</a></li>{if $a.status != 'disposed' && $a.status != 'written_off'}<li><a data-toggle="tab" href="#s-move">Transfer, revalue, impair</a></li><li><a data-toggle="tab" href="#s-disp">Dispose</a></li>{/if}</ul>
<div class="tab-content">

<div class="tab-pane active" id="s-sched">
<table class="table table-condensed" style="max-width:700px"><thead><tr><th>Period</th><th>Method</th><th class="num">Opening NBV</th><th class="num">Charge</th><th class="num">Accumulated</th><th class="num">Closing NBV</th><th>Journal</th></tr></thead><tbody>
{foreach $a.schedule as $s}<tr><td>{$s.period}</td><td>{$s.method|replace:'_':' '}</td><td class="num">{displayPrice price=$s.opening_nbv}</td>
<td class="num">{displayPrice price=$s.amount}</td><td class="num">{displayPrice price=$s.accum_after}</td><td class="num">{displayPrice price=$s.closing_nbv}</td>
<td>{if $s.id_pulse_acc_journal}<a href="{$link_journals}&amp;id_journal={$s.id_pulse_acc_journal}">#{$s.id_pulse_acc_journal}</a>{/if}</td></tr>
{foreachelse}<tr><td colspan="7"><em>No depreciation posted for this asset yet</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-hist">
<table class="table table-condensed" style="max-width:800px"><thead><tr><th>Date</th><th>Event</th><th class="num">Amount</th><th>From</th><th>To</th><th>Note</th><th>Journal</th></tr></thead><tbody>
{foreach $a.events as $e}<tr><td>{$e.business_date}</td><td>{$e.type}</td><td class="num">{if $e.amount != 0}{displayPrice price=$e.amount}{/if}</td>
<td>{$e.from_value|escape}</td><td>{$e.to_value|escape}</td><td>{$e.note|escape}</td>
<td>{if $e.journal_no}<a href="{$link_journals}&amp;id_journal={$e.id_pulse_acc_journal}">{$e.journal_no}</a>{/if}</td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-edit">
<form method="post" class="form-horizontal" style="max-width:700px"><input type="hidden" name="id_asset_s" value="{$a.id_pulse_acc_asset}">
<div class="form-group"><label class="col-sm-4 control-label">Name</label><div class="col-sm-8"><input name="name" class="form-control" value="{$a.name|escape}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Location / room</label><div class="col-sm-5"><input name="location" class="form-control" value="{$a.location|escape}"></div>
<div class="col-sm-3"><input name="id_room" type="number" class="form-control" value="{$a.id_room}" placeholder="room id"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Cost centre</label><div class="col-sm-8"><input name="cost_centre" class="form-control" value="{$a.cost_centre}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Supplier / serial</label><div class="col-sm-4"><input name="supplier" class="form-control" value="{$a.supplier|escape}"></div>
<div class="col-sm-4"><input name="serial_no" class="form-control" value="{$a.serial_no|escape}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Method</label><div class="col-sm-8"><select name="method" class="form-control">
{foreach ['straight_line','reducing_balance','units_of_production','none'] as $m}<option value="{$m}" {if $a.method == $m}selected{/if}>{$m|replace:'_':' '}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Life / rate / residual</label><div class="col-sm-3"><input name="life_months" type="number" class="form-control" value="{$a.life_months}"></div>
<div class="col-sm-2"><input name="rate_pct" type="number" step="0.1" class="form-control" value="{$a.rate_pct}"></div>
<div class="col-sm-3"><input name="residual_value" type="number" step="0.01" class="form-control num" value="{$a.residual_value}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Units total / used</label><div class="col-sm-4"><input name="units_total" type="number" step="0.01" class="form-control num" value="{$a.units_total}"></div>
<div class="col-sm-4"><input name="units_used" type="number" step="0.01" class="form-control num" value="{$a.units_used}"><span class="help-block">Generator hours, kilometres — move this on before the run.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Engineering asset id</label><div class="col-sm-4"><input name="id_pulse_asset" type="number" class="form-control" value="{$a.id_pulse_asset}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Status</label><div class="col-sm-4"><select name="status" class="form-control">
{foreach ['in_service','idle','under_repair','held_for_sale'] as $s}<option value="{$s}" {if $a.status == $s}selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-8"><input name="note" class="form-control" value="{$a.note|escape}"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><button name="updateAsset" class="btn btn-primary">Save</button></div></div>
</form>
</div>

{if $a.status != 'disposed' && $a.status != 'written_off'}
<div class="tab-pane" id="s-move">
<form method="post" class="form-inline" style="margin-bottom:10px"><input type="hidden" name="id_asset_s" value="{$a.id_pulse_acc_asset}">
<strong style="display:inline-block;width:110px">Transfer</strong>
<input name="cost_centre" class="form-control" placeholder="new cost centre" value="{$a.cost_centre}">
<input name="location" class="form-control" placeholder="new location">
<input name="id_room" type="number" class="form-control" placeholder="room id" style="width:100px">
<input name="note" class="form-control" placeholder="reason">
<button name="transferAsset" class="btn btn-default">Transfer</button></form>
<form method="post" class="form-inline" style="margin-bottom:10px"><input type="hidden" name="id_asset_s" value="{$a.id_pulse_acc_asset}">
<strong style="display:inline-block;width:110px">Revalue</strong>
<input name="amount" type="number" step="0.01" class="form-control num" placeholder="+/- amount" required>
<input type="date" name="event_date" class="form-control" value="{$business_date}">
<input name="note" class="form-control" placeholder="valuer / basis">
<button name="revalueAsset" class="btn btn-default">Post revaluation</button>
<span class="help-block" style="display:inline-block;margin-left:8px">Asset account against 3300 revaluation reserve.</span></form>
<form method="post" class="form-inline"><input type="hidden" name="id_asset_s" value="{$a.id_pulse_acc_asset}">
<strong style="display:inline-block;width:110px">Impair</strong>
<input name="amount" type="number" step="0.01" class="form-control num" placeholder="amount" required>
<input type="date" name="event_date" class="form-control" value="{$business_date}">
<input name="note" class="form-control" placeholder="reason">
<button name="impairAsset" class="btn btn-warning">Post impairment</button>
<span class="help-block" style="display:inline-block;margin-left:8px">8900 impairment against accumulated depreciation.</span></form>
</div>

<div class="tab-pane" id="s-disp">
<form method="post" class="form-horizontal" style="max-width:640px"><input type="hidden" name="id_asset_s" value="{$a.id_pulse_acc_asset}">
<p class="help-block">Cost and accumulated depreciation come off the balance sheet, the proceeds go in, and the difference against net book value ({displayPrice price=$a.nbv}) posts as a gain (4920) or loss (8700).</p>
<div class="form-group"><label class="col-sm-4 control-label">Proceeds</label><div class="col-sm-8"><input name="proceeds" type="number" step="0.01" class="form-control num" value="0"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Into</label><div class="col-sm-8"><select name="proceeds_account" class="form-control">{foreach $banks as $b}<option value="{$b.account_code}">{$b.name|escape}</option>{/foreach}<option value="1250">1250 Other receivables</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Date</label><div class="col-sm-8"><input type="date" name="event_date" class="form-control" value="{$business_date}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Note</label><div class="col-sm-8"><input name="note" class="form-control" placeholder="sold to, scrapped, stolen…"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8">
<button name="disposeAsset" class="btn btn-warning" onclick="return confirm('Dispose of this asset?')">Post disposal</button>
<button name="writeOffAsset" class="btn btn-link" onclick="return confirm('Write this asset off in full?')">Write off (no proceeds)</button></div></div>
</form>
</div>
{/if}

</div>
<a class="btn btn-default noprint" href="{$self_url}">Back to the register</a>
</div>
{/if}</div>
