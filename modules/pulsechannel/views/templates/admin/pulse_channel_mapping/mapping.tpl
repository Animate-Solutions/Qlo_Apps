<div class="pulse-ch"><div class="panel"><h3><i class="icon-random"></i> Channel Mappings</h3>
{if $unmapped}<div class="alert alert-danger"><b>{$unmapped|count} room type(s) are not mapped on an enabled channel.</b> The OTA keeps selling nights we never told it about — this is how a 52-room house ends up with 54 arrivals.</div>{/if}
{if $broken}<div class="alert alert-warning"><b>{$broken|count} mapping(s) push nothing</b> — a blank code, a deleted room type or a disabled rate plan.</div>{/if}

<form method="get" class="form-inline ch-filter"><input type="hidden" name="controller" value="AdminPulseChannelMapping"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_channel" class="form-control"><option value="0">All channels</option>{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}" {if $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<button class="btn btn-default">Filter</button></form>

<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#m-list">Mappings ({$mappings|count})</a></li><li{if $unmapped} class="ch-alerttab"{/if}><a data-toggle="tab" href="#m-gaps">Unmapped ({$unmapped|count})</a></li><li><a data-toggle="tab" href="#m-edit">{if $edit}Edit mapping{else}Add mapping{/if}</a></li><li><a data-toggle="tab" href="#m-plans">Rate plans</a></li><li><a data-toggle="tab" href="#m-copy">Copy</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="m-list"><table class="table table-condensed"><thead><tr><th>Channel</th><th>Room type</th><th>Rooms</th><th>Rate plan</th><th>Channel room</th><th>Channel rate</th><th>Occ</th><th>Single adj</th><th>Extra adult</th><th>Child</th><th>Channel adj</th><th>Allot</th><th>Min LOS</th><th></th></tr></thead><tbody>
{foreach $mappings as $m}<tr class="{if !$m.active}text-muted{elseif !$m.channel_room_code || !$m.channel_rate_code}danger{/if}">
<td>{$m.channel|escape:'html':'UTF-8'}</td><td>{$m.room_type|escape:'html':'UTF-8'}</td><td>{$m.rooms}</td><td>{$m.rate_plan_code} <small class="text-muted">{$m.meal_plan|replace:'_':' '}</small></td>
<td><code>{$m.channel_room_code|default:'(blank)'|escape:'html':'UTF-8'}</code></td><td><code>{$m.channel_rate_code|default:'(blank)'|escape:'html':'UTF-8'}</code></td>
<td>{$m.base_occupancy}/{$m.max_occupancy}</td><td>{displayPrice price=$m.single_adj}</td><td>{displayPrice price=$m.extra_adult_adj}</td><td>{displayPrice price=$m.child_adj}</td>
<td>{if $m.rate_adjust_type=='percent'}{$m.rate_adjust_value}%{elseif $m.rate_adjust_type=='amount'}{displayPrice price=$m.rate_adjust_value}{else}—{/if}</td>
<td>{if $m.allotment}{$m.allotment}{else}all{/if}</td><td>{if $m.min_los}{$m.min_los}{else}inherit{/if}</td>
<td class="text-right"><a class="btn btn-xs btn-default" href="{$self_url}&id_mapping={$m.id_pulse_ch_mapping}#m-edit">Edit</a>
<form method="post" class="inline"><input type="hidden" name="id_mapping_del" value="{$m.id_pulse_ch_mapping}"><button name="deleteMapping" class="btn btn-xs btn-link" onclick="return confirm('Delete this mapping and its ARI cells?')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="14"><em>No mappings yet.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="m-gaps">{if $unmapped}
<form method="post"><p class="text-muted">Give each room type the code the OTA uses. The rate code defaults to the room code when you leave it blank.</p>
<div class="form-inline ch-filter">Rate plan for these mappings: <select name="q_plan" class="form-control">{foreach $rate_plans as $rp}{if $rp.active}<option value="{$rp.id_pulse_ch_rate_plan}">{$rp.code|escape:'html':'UTF-8'} — {$rp.name|escape:'html':'UTF-8'}</option>{/if}{/foreach}</select>
Default rate code: <input name="q_rate_code" class="form-control" placeholder="blank = same as room code">
Single supplement: <input name="q_single" class="form-control" value="-5000" size="8"></div>
<table class="table table-condensed"><thead><tr><th>Channel</th><th>Room type</th><th>Rooms</th><th>Channel room code</th><th>Channel rate code</th></tr></thead><tbody>
{foreach $unmapped as $u}<tr class="danger"><td>{$u.channel}</td><td><b>{$u.room_type}</b></td><td>{$u.rooms}</td>
<td><input name="q_code[{$u.id_pulse_ch_channel}-{$u.id_product}]" class="form-control" placeholder="e.g. DLX-KING"></td>
<td><input name="q_rate_code_{$u.id_pulse_ch_channel}-{$u.id_product}" class="form-control" placeholder="blank = room code"></td></tr>{/foreach}</tbody></table>
<button name="quickMap" class="btn btn-primary btn-lg">Map them all</button></form>
{else}<p class="text-success"><i class="icon-check"></i> Every room type is mapped on every enabled channel.</p>{/if}</div>

<div class="tab-pane" id="m-edit"><form method="post" class="form-horizontal"><input type="hidden" name="id_pulse_ch_mapping" value="{if $edit}{$edit.id_pulse_ch_mapping}{/if}"><div class="row">
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Channel</label><div class="col-sm-8"><select name="m_channel" class="form-control">{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}" {if $edit && $edit.id_pulse_ch_channel==$c.id_pulse_ch_channel}selected{elseif !$edit && $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Room type</label><div class="col-sm-8"><select name="m_product" class="form-control">{foreach $room_types as $rt}<option value="{$rt.id_product}" {if $edit && $edit.id_product==$rt.id_product}selected{/if}>{$rt.name|escape:'html':'UTF-8'} ({$rt.rooms} rooms, max {$rt.max_guests})</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Rate plan</label><div class="col-sm-8"><select name="m_plan" class="form-control">{foreach $rate_plans as $rp}<option value="{$rp.id_pulse_ch_rate_plan}" {if $edit && $edit.id_pulse_ch_rate_plan==$rp.id_pulse_ch_rate_plan}selected{/if}>{$rp.code|escape:'html':'UTF-8'} — {$rp.name|escape:'html':'UTF-8'}{if !$rp.active} (inactive){/if}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Channel room code</label><div class="col-sm-8"><input name="m_room_code" class="form-control" value="{if $edit}{$edit.channel_room_code|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Channel rate code</label><div class="col-sm-8"><input name="m_rate_code" class="form-control" value="{if $edit}{$edit.channel_rate_code|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Occupancy base / max</label><div class="col-sm-8"><input name="m_base_occ" class="form-control" value="{if $edit}{$edit.base_occupancy}{else}2{/if}"><input name="m_max_occ" class="form-control" value="{if $edit}{$edit.max_occupancy}{else}3{/if}"></div></div>
</div><div class="col-md-6">
<div class="form-group"><label class="col-sm-5">Single supplement</label><div class="col-sm-7"><input name="m_single" class="form-control" value="{if $edit}{$edit.single_adj}{else}-5000{/if}"><small class="text-muted">single rate = double rate + this (so −5000 sells single at ₦5,000 less)</small></div></div>
<div class="form-group"><label class="col-sm-5">Extra adult</label><div class="col-sm-7"><input name="m_extra" class="form-control" value="{if $edit}{$edit.extra_adult_adj}{else}10000{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Child</label><div class="col-sm-7"><input name="m_child" class="form-control" value="{if $edit}{$edit.child_adj}{else}5000{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Channel adjustment</label><div class="col-sm-7"><select name="m_adj_type" class="form-control"><option value="none" {if $edit && $edit.rate_adjust_type=='none'}selected{/if}>none</option><option value="percent" {if $edit && $edit.rate_adjust_type=='percent'}selected{/if}>percent</option><option value="amount" {if $edit && $edit.rate_adjust_type=='amount'}selected{/if}>amount</option></select><input name="m_adj_value" class="form-control" value="{if $edit}{$edit.rate_adjust_value}{else}0{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Allotment (0 = all)</label><div class="col-sm-7"><input name="m_allot" class="form-control" value="{if $edit}{$edit.allotment}{else}0{/if}"></div></div>
<div class="form-group"><label class="col-sm-5">Min LOS override</label><div class="col-sm-7"><input name="m_min_los" class="form-control" value="{if $edit}{$edit.min_los}{else}0{/if}"><small class="text-muted">0 = inherit from the room type / rate plan</small></div></div>
<div class="form-group"><label class="col-sm-5">Active</label><div class="col-sm-7"><input type="checkbox" name="m_active" value="1" {if !$edit || $edit.active}checked{/if}></div></div>
<button name="saveMapping" class="btn btn-primary btn-lg">Save mapping</button></div></div></form></div>

<div class="tab-pane" id="m-plans"><table class="table table-condensed"><thead><tr><th>Code</th><th>Name</th><th>Meal plan</th><th>Derived from</th><th>Adjustment</th><th>Refundable</th><th>LOS</th><th>Release</th><th>Active</th></tr></thead><tbody>
{foreach $rate_plans as $rp}<tr class="{if !$rp.active}text-muted{/if}"><td><code>{$rp.code|escape:'html':'UTF-8'}</code></td><td>{$rp.name|escape:'html':'UTF-8'}</td><td>{$rp.meal_plan|replace:'_':' '}</td><td>{$rp.derive_from|default:'base'}</td>
<td>{if $rp.adjust_type=='percent'}{$rp.adjust_value}%{elseif $rp.adjust_type=='amount'}{displayPrice price=$rp.adjust_value}{else}—{/if}</td><td>{if $rp.refundable}yes{else}no{/if}</td><td>{$rp.min_los}{if $rp.max_los}–{$rp.max_los}{/if}</td><td>{$rp.release_days}</td><td>{if $rp.active}yes{else}no{/if}</td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="rp_id" value="">
<input name="rp_code" class="form-control" placeholder="CODE" size="8"> <input name="rp_name" class="form-control" placeholder="Name">
<select name="rp_meal" class="form-control"><option value="room_only">room only</option><option value="bed_breakfast">bed &amp; breakfast</option><option value="half_board">half board</option><option value="full_board">full board</option><option value="all_inclusive">all inclusive</option></select>
<select name="rp_parent" class="form-control"><option value="0">base (BAR)</option>{foreach $rate_plans as $rp}<option value="{$rp.id_pulse_ch_rate_plan}">derive from {$rp.code|escape:'html':'UTF-8'}</option>{/foreach}</select>
<select name="rp_adj_type" class="form-control"><option value="none">none</option><option value="percent">percent</option><option value="amount">amount</option></select>
<input name="rp_adj_value" class="form-control" placeholder="0" size="6"> <input name="rp_min_los" class="form-control" placeholder="min LOS" size="4"> <input name="rp_max_los" class="form-control" placeholder="max LOS" size="4">
<input name="rp_release" class="form-control" placeholder="release" size="4"> <label><input type="checkbox" name="rp_refundable" value="1" checked> refundable</label> <label><input type="checkbox" name="rp_active" value="1" checked> active</label>
<button name="saveRatePlan" class="btn btn-default">Add rate plan</button></form></div>

<div class="tab-pane" id="m-copy"><form method="post" class="form-inline"><p class="text-muted">Copy every mapping from one channel to another, codes included, then edit the codes the new OTA uses.</p>
<select name="copy_from" class="form-control">{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}">{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select> →
<select name="copy_to" class="form-control">{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}">{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<button name="copyMappings" class="btn btn-default">Copy</button></form></div>

</div></div></div>
