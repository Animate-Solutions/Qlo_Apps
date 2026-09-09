<div class="pulse-ta"><div class="panel"><h3><i class="icon-warning-sign"></i> Exceptions — {$counts.total} open{if $counts.block}, {$counts.block} blocking approval{/if}</h3>
<p class="text-muted">Everywhere the punch record and the roster disagree. A blocking exception stops a period being approved, because that is exactly the case where somebody's pay would be wrong. Every fix writes an adjustment carrying your name, the reason and the time — the punches themselves are never touched.</p>

<form method="get" class="form-inline noprint" style="margin-bottom:10px">
  <input type="hidden" name="controller" value="AdminPulseTaExceptions"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
  <select name="status" class="form-control"><option value="open" {if $f.status=='open'}selected{/if}>Open</option><option value="resolved" {if $f.status=='resolved'}selected{/if}>Resolved</option><option value="waived" {if $f.status=='waived'}selected{/if}>Waived</option><option value="auto_closed" {if $f.status=='auto_closed'}selected{/if}>Auto-closed</option></select>
  <select name="type" class="form-control"><option value="">Any type</option>{foreach $types as $k => $v}<option value="{$k}" {if $f.type==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <select name="severity" class="form-control"><option value="">Any severity</option><option value="block" {if $f.severity=='block'}selected{/if}>Blocking</option><option value="warn" {if $f.severity=='warn'}selected{/if}>Warning</option><option value="info" {if $f.severity=='info'}selected{/if}>Info</option></select>
  <select name="department" class="form-control"><option value="">Any department</option>{foreach $departments as $k => $v}<option value="{$k}" {if $f.department==$k}selected{/if}>{$v}</option>{/foreach}</select>
  <input type="date" name="from" value="{$f.from}" class="form-control"> <input type="date" name="to" value="{$f.to}" class="form-control">
  <button class="btn btn-default">Filter</button>
</form>

{if $counts.by_type}<p>{foreach $counts.by_type as $t => $n}<span class="label label-default" style="margin-right:4px">{if isset($types[$t])}{$types[$t]}{else}{$t}{/if}: {$n}</span>{/foreach}</p>{/if}

<form method="post">
<table class="table table-condensed"><thead><tr><th style="width:24px"></th><th>Date</th><th>Staff</th><th>Type</th><th>Sev</th><th>Detail</th><th>Shift</th><th>Punched</th><th>Status</th><th></th></tr></thead><tbody>
{foreach $rows as $r}
<tr class="{if $r.severity=='block' && $r.status=='open'}danger{elseif $r.severity=='warn' && $r.status=='open'}warning{/if}">
  <td>{if $r.status=='open'}<input type="checkbox" name="ex[]" value="{$r.id_pulse_ta_exception}">{/if}</td>
  <td>{$r.business_date}</td>
  <td>{if $r.staff_name}{$r.staff_no|escape:'html':'UTF-8'} {$r.staff_name|escape:'html':'UTF-8'}{else}<small class="text-muted">fleet-wide</small>{/if}</td>
  <td>{if isset($types[$r.type])}{$types[$r.type]}{else}{$r.type}{/if}</td>
  <td>{if $r.severity=='block'}<span class="label label-danger">block</span>{elseif $r.severity=='warn'}<span class="label label-warning">warn</span>{else}<span class="label label-default">info</span>{/if}</td>
  <td>{$r.detail|escape:'html':'UTF-8'}</td>
  <td>{if $r.shift_code}{$r.shift_code|escape:'html':'UTF-8'} {$r.shift_start|date_format:"%H:%M"}–{$r.shift_end|date_format:"%H:%M"}{/if}</td>
  <td>{if $r.first_in}{$r.first_in|date_format:"%H:%M"}{else}—{/if} → {if $r.last_out}{$r.last_out|date_format:"%H:%M"}{else}—{/if}</td>
  <td>{$r.status}{if $r.resolved_by}<br><small class="text-muted">{$r.resolved_by|escape:'html':'UTF-8'} {$r.resolved_at|date_format:"%d/%m %H:%M"}</small>{/if}{if $r.resolution}<br><small>{$r.resolution|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{if $r.status=='open'}{if $r.locked}<small class="text-muted">period locked</small>{else}<a class="btn btn-xs btn-primary" href="{$self_url}&id_exception={$r.id_pulse_ta_exception}">Fix</a>{/if}{/if}</td>
</tr>
{foreachelse}<tr><td colspan="10"><em>Nothing matches. If the filter says "open", that means every timesheet in the range agrees with its roster.</em></td></tr>{/foreach}
</tbody></table>
{if $f.status=='open' && $rows}
<div class="well well-sm">
  <strong>Waive the selected exceptions</strong> — use this only when the record is right and the exception is noise (a rostered day nobody was expected to work, say). A waiver is audited with your name.
  <div class="form-inline" style="margin-top:6px"><input name="bulk_reason" class="form-control" style="width:420px" placeholder="Why are these being waived?"> <button name="bulkWaive" class="btn btn-default" onclick="return confirm('Waive the selected exceptions?')">Waive selected</button></div>
</div>
{/if}
</form>
</div></div>
