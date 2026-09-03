<div class="pulse-fd"><div class="panel"><h3><i class="icon-bed"></i> Housekeeping &mdash; {$business_date}
  <span class="pull-right"><a class="btn btn-sm {if $mine}btn-primary{else}btn-default{/if}" href="{$self_url}&mine=1">My tasks</a> <a class="btn btn-sm {if !$mine}btn-primary{else}btn-default{/if}" href="{$self_url}">All</a></span></h3>
  <form method="post" class="form-inline" style="margin-bottom:10px">
    <select name="id_room" class="form-control">{foreach $board as $r}<option value="{$r.id_room}">{$r.room_num} — {$r.hk_status|replace:'_':' '}{if $r.guest} ({$r.guest}){/if}</option>{/foreach}</select>
    <select name="type" class="form-control"><option>clean</option><option>turndown</option><option>inspect</option><option>maintenance</option><option>minibar</option><option>linen</option><option>deep_clean</option></select>
    <select name="priority" class="form-control"><option value="1">P1 urgent</option><option value="3">P3 departure</option><option value="5" selected>P5 normal</option><option value="8">P8 low</option></select>
    <select name="assigned_to" class="form-control"><option value="">Unassigned</option>{foreach $attendants as $e}<option value="{$e.id_employee}">{$e.firstname} {$e.lastname}</option>{/foreach}</select>
    <input name="note" class="form-control" placeholder="Note"> <button name="addTask" class="btn btn-default">Add task</button>
  </form>
  <form method="post"><table class="table"><thead><tr><th><input type="checkbox" id="chk-all"></th><th>P</th><th>Room</th><th>Floor</th><th>Task</th><th>Room status</th><th>Assigned</th><th>Note</th><th>Status</th><th></th></tr></thead><tbody>
  {foreach $tasks as $t}<tr class="{if $t.priority<=3}warning{/if}"><td><input type="checkbox" name="task_ids[]" value="{$t.id_pulse_housekeeping_task}"></td><td>{$t.priority}</td><td><strong>{$t.room_num}</strong></td><td>{$t.floor}</td><td>{$t.type|replace:'_':' '}</td><td><span class="sw hk-{$t.hk_status}"></span>{$t.hk_status|replace:'_':' '} / {$t.fo_status}</td><td>{$t.assignee|default:'—'}</td><td><small>{$t.note}</small></td><td>{$t.status|replace:'_':' '}</td>
    <td>{if $t.status=='open'}<button name="setStatus" value="in_progress" class="btn btn-xs btn-default" formaction="{$self_url}&id_task={$t.id_pulse_housekeeping_task}&status=in_progress">Start</button>{/if}
        <button name="setStatus" class="btn btn-xs btn-success" formaction="{$self_url}&id_task={$t.id_pulse_housekeeping_task}&status=done">Done</button>
        <button name="setStatus" class="btn btn-xs btn-default" formaction="{$self_url}&id_task={$t.id_pulse_housekeeping_task}&status=skipped">Skip</button></td></tr>
  {foreachelse}<tr><td colspan="10"><em>Queue is empty</em></td></tr>{/foreach}</tbody></table>
  <div class="form-inline"><select name="assigned_to" class="form-control">{foreach $attendants as $e}<option value="{$e.id_employee}">{$e.firstname} {$e.lastname}</option>{/foreach}</select> <button name="bulkAssign" class="btn btn-default">Assign selected</button></div></form>
</div>
<div class="panel"><h3>Mark room out of order / service</h3>
  <form method="post" class="form-inline"><select name="id_room" class="form-control">{foreach $board as $r}<option value="{$r.id_room}">{$r.room_num}</option>{/foreach}</select>
    <select name="hk_status" class="form-control">{foreach $hk_statuses as $s}<option value="{$s}">{$s|replace:'_':' '}</option>{/foreach}</select>
    <input name="reason" class="form-control" placeholder="Reason (OOO)"> <input type="date" name="until" class="form-control"> <button name="setHk" class="btn btn-default">Set status</button></form>
  <p class="help-block">OOO/OOS rooms are automatically made unsellable in QloApps (room status "temporarily inactive") and restored when returned to service.</p>
</div>
<div class="panel"><h3>Completed today ({$done_today|count})</h3><table class="table table-condensed"><tbody>{foreach $done_today as $t}<tr><td>{$t.room_num}</td><td>{$t.type}</td><td>{$t.assignee}</td><td>{$t.status}</td><td>{$t.date_done}</td></tr>{/foreach}</tbody></table></div>
</div>
<script>$('#chk-all').change(function(){ $('input[name="task_ids[]"]').prop('checked',this.checked); });</script>
