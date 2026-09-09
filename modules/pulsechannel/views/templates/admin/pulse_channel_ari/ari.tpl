<div class="pulse-ch"><div class="panel"><h3><i class="icon-calendar"></i> ARI Calendar</h3>
<form method="get" class="form-inline ch-filter"><input type="hidden" name="controller" value="AdminPulseChannelAri"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_channel" class="form-control">{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}" {if $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}{if !$c.enabled} (off){/if}</option>{/foreach}</select>
<select name="id_rate_plan" class="form-control"><option value="0">All rate plans</option>{foreach $rate_plans as $rp}<option value="{$rp.id_pulse_ch_rate_plan}" {if $rp.id_pulse_ch_rate_plan==$id_rate_plan}selected{/if}>{$rp.code} — {$rp.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<input type="date" name="from" value="{$from}" class="form-control">
<select name="days" class="form-control"><option value="14" {if $days==14}selected{/if}>14 days</option><option value="30" {if $days==30}selected{/if}>30 days</option><option value="45" {if $days==45}selected{/if}>45 days</option><option value="60" {if $days==60}selected{/if}>60 days</option></select>
<button class="btn btn-default">Show</button></form>

<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#a-grid">Grid</a></li><li><a data-toggle="tab" href="#a-bulk">Bulk update</a></li><li><a data-toggle="tab" href="#a-parity">Parity ({$parity|count})</a></li><li><a data-toggle="tab" href="#a-queue">Queue ({$queue|count})</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="a-grid">
<form method="post"><input type="hidden" name="id_channel" value="{$id_channel}"><input type="hidden" name="from" value="{$from}"><input type="hidden" name="days" value="{$days}">
<p class="text-muted">Type over a rate to override it (the override survives recompute until you clear it in Bulk update). <span class="ch-legend ch-stop"></span> stop-sell · <span class="ch-legend ch-cta"></span> closed to arrival · <span class="ch-legend ch-dirty"></span> waiting to push.</p>
<div class="ch-scroll"><table class="table table-condensed ch-grid"><thead><tr><th class="ch-sticky">Room type / rate plan</th>{foreach $dates as $d}<th class="{if $d|date_format:'%w'==0 || $d|date_format:'%w'==6}ch-we{/if}">{$d|date_format:"%a"}<br>{$d|date_format:"%d/%m"}</th>{/foreach}</tr></thead><tbody>
{foreach $rows as $r}
<input type="hidden" name="dirty_product[]" value="{$r.id_product}">
<tr class="ch-rowhead"><td class="ch-sticky"><b>{$r.room_type}</b><br><small class="text-muted">{$r.rate_plan_code} · {$r.room_code}/{$r.rate_code}</small></td>
{foreach $dates as $d}{if isset($r.cells[$d])}{assign var=c value=$r.cells[$d]}
<td class="ch-cell {if $c.stop_sell}ch-stop{elseif $c.available<=0}ch-zero{/if} {if $c.dirty}ch-dirty{/if}">
<div class="ch-av" title="physical {$c.physical} · booked {$c.booked} · blocked {$c.blocked} · OOO {$c.ooo}">{$c.available}</div>
<input class="ch-rate" name="cell[{$c.id_pulse_ch_ari}][rate]" value="{$c.rate|string_format:"%.0f"}" size="6"{if $c.manual_rate !== null} title="manual override"{/if}>
<div class="ch-badges"><label title="Stop sell"><input type="checkbox" name="cell[{$c.id_pulse_ch_ari}][stop_sell]" value="1" {if $c.stop_sell}checked{/if}>S</label>
<label title="Closed to arrival"><input type="checkbox" name="cell[{$c.id_pulse_ch_ari}][cta]" value="1" {if $c.cta}checked{/if}>A</label>
<label title="Closed to departure"><input type="checkbox" name="cell[{$c.id_pulse_ch_ari}][ctd]" value="1" {if $c.ctd}checked{/if}>D</label>
<input class="ch-los" name="cell[{$c.id_pulse_ch_ari}][min_los]" value="{$c.min_los}" size="1" title="Min LOS"></div></td>
{else}<td class="ch-cell ch-none">—</td>{/if}{/foreach}</tr>
{foreachelse}<tr><td colspan="99"><em>Nothing computed for this channel yet — map its room types, then press Recompute.</em></td></tr>{/foreach}</tbody></table></div>
<button name="saveCells" class="btn btn-primary"><i class="icon-save"></i> Save &amp; queue</button>
<button name="recomputeNow" class="btn btn-default"><i class="icon-repeat"></i> Recompute</button>
<button name="pushNow" class="btn btn-default"><i class="icon-cloud-upload"></i> Push now</button></form></div>

<div class="tab-pane" id="a-bulk"><form method="post" class="form-horizontal"><div class="row">
<div class="col-md-4"><div class="form-group"><label class="col-sm-4">Channels</label><div class="col-sm-8"><select name="b_channels[]" multiple size="6" class="form-control">{foreach $channels as $c}{if $c.enabled}<option value="{$c.id_pulse_ch_channel}" {if $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Room types</label><div class="col-sm-8"><select name="b_products[]" multiple size="6" class="form-control">{foreach $room_types as $rt}<option value="{$rt.id_product}">{$rt.name|escape:'html':'UTF-8'}</option>{/foreach}</select><small class="text-muted">none = all</small></div></div>
<div class="form-group"><label class="col-sm-4">Rate plans</label><div class="col-sm-8"><select name="b_plans[]" multiple size="5" class="form-control">{foreach $rate_plans as $rp}<option value="{$rp.id_pulse_ch_rate_plan}">{$rp.code}</option>{/foreach}</select></div></div></div>
<div class="col-md-4"><div class="form-group"><label class="col-sm-4">Dates</label><div class="col-sm-8"><input type="date" name="b_from" value="{$from}" class="form-control"><input type="date" name="b_to" value="{$from}" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Days of week</label><div class="col-sm-8">{foreach $dows as $i => $dn}<label class="ch-dow"><input type="checkbox" name="b_dow[]" value="{$i}"> {$dn}</label>{/foreach}<small class="text-muted">none = every day</small></div></div>
<div class="form-group"><label class="col-sm-4">Rate</label><div class="col-sm-8"><input name="b_rate" class="form-control" placeholder="leave blank to keep"></div></div>
<div class="form-group"><label class="col-sm-4">Min / Max LOS</label><div class="col-sm-8"><input name="b_min_los" class="form-control" placeholder="min"><input name="b_max_los" class="form-control" placeholder="max (0 = none)"></div></div></div>
<div class="col-md-4"><div class="form-group"><label class="col-sm-4">Stop sell</label><div class="col-sm-8"><select name="b_stop" class="form-control"><option value="">keep</option><option value="1">close</option><option value="0">open</option></select></div></div>
<div class="form-group"><label class="col-sm-4">CTA</label><div class="col-sm-8"><select name="b_cta" class="form-control"><option value="">keep</option><option value="1">close to arrival</option><option value="0">open</option></select></div></div>
<div class="form-group"><label class="col-sm-4">CTD</label><div class="col-sm-8"><select name="b_ctd" class="form-control"><option value="">keep</option><option value="1">close to departure</option><option value="0">open</option></select></div></div>
<div class="form-group"><label class="col-sm-4">Release days</label><div class="col-sm-8"><input name="b_release" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">&nbsp;</label><div class="col-sm-8"><label><input type="checkbox" name="b_clear" value="1"> Clear manual overrides first</label></div></div>
<button name="bulkUpdate" class="btn btn-primary btn-lg">Apply to range</button></div></div></form>
<hr><h4>Close-out</h4><form method="post" class="form-inline"><select name="co_channel" class="form-control"><option value="0">All enabled channels</option>{foreach $channels as $c}{if $c.enabled}<option value="{$c.id_pulse_ch_channel}">{$c.name|escape:'html':'UTF-8'}</option>{/if}{/foreach}</select>
<input type="date" name="co_from" value="{$business_date}" class="form-control"> <input type="date" name="co_to" value="{$business_date}" class="form-control">
<select name="co_open" class="form-control"><option value="0">Stop-sell everything</option><option value="1">Re-open everything</option></select>
<button name="closeOut" class="btn btn-danger" onclick="return confirm('Apply to every mapped room type in that range?')">Apply</button></form></div>

<div class="tab-pane" id="a-parity"><p class="text-muted">Drift beyond {$tolerance}% of what the channel was last told is flagged. "Never pushed" means the channel has no rate for that night at all.</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Channel</th><th>Room type</th><th>Plan</th><th>Our rate</th><th>Pushed rate</th><th>Drift</th><th>Avail</th><th>Pushed avail</th><th>Last push</th><th>Flag</th></tr></thead><tbody>
{foreach $parity as $p}{if $p.flag != 'ok'}<tr class="{if $p.flag=='drift' || $p.flag=='never_pushed'}warning{/if}"><td>{$p.ari_date}</td><td>{$p.channel}</td><td>{$p.room_type}</td><td>{$p.rate_plan}</td><td>{displayPrice price=$p.rate}</td><td>{if $p.pushed_rate===null}<em>—</em>{else}{displayPrice price=$p.pushed_rate}{/if}</td><td>{if $p.drift===null}—{else}{displayPrice price=$p.drift}{if $p.drift_pct !== null} ({$p.drift_pct}%){/if}{/if}</td><td>{$p.available}</td><td>{$p.pushed_available|default:'—'}</td><td>{$p.pushed_at|default:'never'}</td><td><span class="label label-{if $p.flag=='drift'}danger{elseif $p.flag=='never_pushed'}warning{else}default{/if}">{$p.flag|replace:'_':' '}</span></td></tr>{/if}
{foreachelse}<tr><td colspan="11"><em>No parity data yet.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="a-queue"><table class="table table-condensed"><thead><tr><th>#</th><th>Room type</th><th>Dates</th><th>Reason</th><th>Status</th><th>Attempts</th><th>Cells</th><th>Next try</th><th>Error</th></tr></thead><tbody>
{foreach $queue as $q}<tr class="{if $q.status=='poison'}danger{elseif $q.status=='failed'}warning{/if}"><td>{$q.id_pulse_ch_queue}</td><td>{$q.room_type|default:'all mapped'}</td><td>{$q.date_from} → {$q.date_to}</td><td>{$q.reason}</td><td>{$q.status}</td><td>{$q.attempts}</td><td>{$q.cells}</td><td>{$q.next_attempt_at|date_format:"%d/%m %H:%M"}</td><td><small>{$q.last_error|truncate:60}</small></td></tr>
{foreachelse}<tr><td colspan="9"><em>Nothing queued.</em></td></tr>{/foreach}</tbody></table></div>

</div></div></div>
