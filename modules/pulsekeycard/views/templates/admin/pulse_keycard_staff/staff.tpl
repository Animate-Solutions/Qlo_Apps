<div class="pulse-kc"><div class="panel"><h3><i class="icon-group"></i> Staff access</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#s-groups">Access groups ({$groups|count})</a></li><li><a data-toggle="tab" href="#s-cards">Staff cards ({$cards|count})</a></li><li><a data-toggle="tab" href="#s-expiring">Expiring soon ({$expiring|count})</a></li><li><a data-toggle="tab" href="#s-reissue">Lost master</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="s-groups">
<table class="table table-condensed"><thead><tr><th>Group</th><th>Department</th><th>Doors</th><th>All guest rooms</th><th>Shift</th><th>Days</th><th>Card life</th><th>Overrides</th><th>Live cards</th><th></th></tr></thead><tbody>
{foreach $groups as $g}<tr class="{if !$g.active}text-muted{elseif $g.is_master}warning{/if}">
  <td><strong>{$g.name|escape:'html':'UTF-8'}</strong>{if $g.is_master} <span class="label label-danger">master</span>{/if}</td><td>{$g.department|escape:'html':'UTF-8'}</td>
  <td>{$g.doors|default:'—'|escape:'html':'UTF-8'}</td><td>{if $g.all_rooms}yes{else}no{/if}</td>
  <td>{$g.shift_start|truncate:5:''|escape:'html':'UTF-8'}–{$g.shift_end|truncate:5:''|escape:'html':'UTF-8'}</td><td>{$g.days_mask|escape:'html':'UTF-8'}</td><td>{$g.card_days|escape:'html':'UTF-8'} d</td>
  <td>{if $g.override_deadbolt}deadbolt{/if} {if $g.override_dnd}dnd{/if}</td><td>{$g.active_cards|escape:'html':'UTF-8'}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_group={$g.id_pulse_kc_staff_group|escape:'html':'UTF-8'}">Cards</a></td></tr>
{/foreach}
</tbody></table>
<h4>{if $group}Edit {$group.name|escape:'html':'UTF-8'}{else}Add an access group{/if}</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_group_edit" value="{if $group}{$group.id_pulse_kc_staff_group|escape:'html':'UTF-8'}{/if}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="name" class="form-control" value="{if $group}{$group.name|escape:'html':'UTF-8'}{/if}" required></div></div>
  <div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><select name="department" class="form-control">{foreach $departments as $d}<option value="{$d|escape:'html':'UTF-8'}" {if $group && $group.department==$d}selected{/if}>{$d|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Shift</label><div class="col-sm-4"><input name="shift_start" class="form-control" value="{if $group}{$group.shift_start|escape:'html':'UTF-8'}{else}06:00{/if}"></div><div class="col-sm-4"><input name="shift_end" class="form-control" value="{if $group}{$group.shift_end|escape:'html':'UTF-8'}{else}18:00{/if}"></div></div>
  <div class="form-group"><label class="col-sm-4">Days</label><div class="col-sm-8">
    {foreach $days_list as $bit => $label}<label class="checkbox-inline"><input type="checkbox" name="days[]" value="{$bit|escape:'html':'UTF-8'}" {if $days_checked[$bit]}checked{/if}> {$label|escape:'html':'UTF-8'}</label>{/foreach}
  </div></div>
  <div class="form-group"><label class="col-sm-4">Card life (days)</label><div class="col-sm-8"><input name="card_days" type="number" class="form-control" value="{if $group}{$group.card_days|escape:'html':'UTF-8'}{else}{$card_days|escape:'html':'UTF-8'}{/if}"></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Doors</label><div class="col-sm-8" style="max-height:180px;overflow:auto">
    {foreach $doors as $d}<label class="checkbox-inline"><input type="checkbox" name="doors[]" value="{$d.id_pulse_kc_door|escape:'html':'UTF-8'}" {if $d.id_pulse_kc_door|in_array:$group_doors}checked{/if}> {$d.name|escape:'html':'UTF-8'}</label>{/foreach}
  </div></div>
  <div class="form-group"><div class="col-sm-8 col-sm-offset-4">
    <label><input type="checkbox" name="all_rooms" value="1" {if $group && $group.all_rooms}checked{/if}> Opens every guest room (floor / grand master)</label><br>
    <label><input type="checkbox" name="is_master" value="1" {if $group && $group.is_master}checked{/if}> This is a master group</label><br>
    <label><input type="checkbox" name="override_deadbolt" value="1" {if $group && $group.override_deadbolt}checked{/if}> Deadbolt override</label><br>
    <label><input type="checkbox" name="override_dnd" value="1" {if $group && $group.override_dnd}checked{/if}> Do-not-disturb override</label><br>
    <label><input type="checkbox" name="active" value="1" {if !$group || $group.active}checked{/if}> Active</label>
  </div></div>
  <div class="form-group"><div class="col-sm-8 col-sm-offset-4"><button name="saveGroup" class="btn btn-primary">Save group</button></div></div>
</div></div>
</form>
</div>

<div class="tab-pane" id="s-cards">
<form method="post" class="form-inline">
  <select name="id_employee" class="form-control input-sm">{foreach $employees as $e}<option value="{$e.id_employee|escape:'html':'UTF-8'}">{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="id_group_issue" class="form-control input-sm">{foreach $groups as $g}{if $g.active}<option value="{$g.id_pulse_kc_staff_group|escape:'html':'UTF-8'}">{$g.name|escape:'html':'UTF-8'}</option>{/if}{/foreach}</select>
  <select name="id_encoder" class="form-control input-sm">{foreach $encoders as $e}<option value="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}">{$e.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <input name="days" type="number" class="form-control input-sm" style="width:90px" placeholder="days">
  <button name="issueStaffCard" class="btn btn-success btn-sm">Cut a staff card</button>
</form>
<table class="table table-condensed"><thead><tr><th>Key</th><th>Holder</th><th>Group</th><th>Department</th><th>Shift</th><th>Card</th><th>Valid to</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $cards as $c}<tr class="{if $c.status=='failed'}danger{elseif $c.valid_to < $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'}warning{/if}">
  <td>{$c.key_no|escape:'html':'UTF-8'}</td><td>{$c.holder|default:$c.guest_name|escape:'html':'UTF-8'}</td><td>{$c.group_name|escape:'html':'UTF-8'}</td><td>{$c.department|escape:'html':'UTF-8'}</td>
  <td>{$c.shift_start|truncate:5:''|escape:'html':'UTF-8'}–{$c.shift_end|truncate:5:''|escape:'html':'UTF-8'}</td><td><code>{$c.card_serial|default:'—'|escape:'html':'UTF-8'}</code></td><td>{$c.valid_to|escape:'html':'UTF-8'}</td><td>{$c.status|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_key" value="{$c.id_pulse_kc_key|escape:'html':'UTF-8'}"><input name="reason" class="input-sm" placeholder="reason"><button name="cancelStaffCard" class="btn btn-xs btn-danger">Withdraw</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em>No staff cards yet</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-expiring">
<table class="table table-condensed"><thead><tr><th>Key</th><th>Holder</th><th>Group</th><th>Department</th><th>Expires</th></tr></thead><tbody>
{foreach $expiring as $x}<tr class="warning"><td>{$x.key_no|escape:'html':'UTF-8'}</td><td>{$x.holder|escape:'html':'UTF-8'}</td><td>{$x.group_name|escape:'html':'UTF-8'}</td><td>{$x.department|escape:'html':'UTF-8'}</td><td>{$x.valid_to|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="5"><em>No staff card lapses in the next 14 days</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-reissue">
<p class="text-danger"><strong>A master card has gone missing.</strong> This kills every live card in the group (or the whole department), blacklists the serials on lock systems that support it, and cuts a replacement for each holder. Cards that fail to encode are listed so you can chase them one by one at the desk.</p>
<form method="post" class="form-inline">
  <select name="id_group_reissue" class="form-control">{foreach $groups as $g}<option value="{$g.id_pulse_kc_staff_group|escape:'html':'UTF-8'}">{$g.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="department_reissue" class="form-control"><option value="">this group only</option>{foreach $departments as $d}<option value="{$d|escape:'html':'UTF-8'}">whole {$d|escape:'html':'UTF-8'} department</option>{/foreach}</select>
  <input name="reason" class="form-control" value="Master card lost" style="width:240px">
  <button name="reissueGroup" class="btn btn-danger" onclick="return confirm('Kill and re-cut every card in the selection?')">Re-issue now</button>
</form>
</div>

</div></div></div>
