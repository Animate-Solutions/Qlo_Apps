<div class="pulse-ch"><div class="panel"><h3><i class="icon-list-alt"></i> Channel Logs &amp; Health</h3>
<form method="get" class="form-inline ch-filter"><input type="hidden" name="controller" value="AdminPulseChannelLogs"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="id_channel" class="form-control"><option value="0">All channels</option>{foreach $channels as $c}<option value="{$c.id_pulse_ch_channel}" {if $c.id_pulse_ch_channel==$id_channel}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
<select name="status" class="form-control"><option value="">Any status</option><option value="ok" {if $status=='ok'}selected{/if}>ok</option><option value="error" {if $status=='error'}selected{/if}>error</option></select>
<select name="type" class="form-control"><option value="">Any type</option>
{foreach $log_types as $t}<option value="{$t}" {if $type==$t}selected{/if}>{$t}</option>{/foreach}</select>
<button class="btn btn-default">Filter</button></form>

<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#l-health">Health</a></li><li><a data-toggle="tab" href="#l-log">Messages ({$logs|count})</a></li><li><a data-toggle="tab" href="#l-queue">Queue ({$queue|count})</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="l-health"><table class="table table-condensed"><thead><tr><th>Channel</th><th>Health</th><th>Last success</th><th>Last failure</th><th>Calls 24h</th><th>Errors 24h</th><th>Avg ms</th><th>Slowest ms</th><th>Last error</th></tr></thead><tbody>
{foreach $health as $h}<tr class="{if $h.health=='down'}danger{elseif $h.health=='degraded'}warning{/if}"><td>{$h.name|escape:'html':'UTF-8'}</td><td><span class="ch-dot ch-{$h.health}"></span> {$h.health}</td><td>{$h.last_success|default:'never'}</td><td>{$h.last_failure|default:'—'}</td><td>{$h.calls}</td><td>{$h.errors}</td><td>{$h.avg_ms|default:'—'}</td><td>{$h.max_ms|default:'—'}</td><td><small>{$h.last_error|truncate:80|escape:'html':'UTF-8'}</small></td></tr>{/foreach}</tbody></table>
<form method="post" class="form-inline"><button name="pruneLogs" class="btn btn-default">Prune logs older than {$keep_days} days</button></form></div>

<div class="tab-pane" id="l-log">
{if $detail}<div class="panel ch-detail"><h4>#{$detail.id_pulse_ch_log} · {$detail.channel|escape:'html':'UTF-8'} · {$detail.direction} · {$detail.type|escape:'html':'UTF-8'} · HTTP {$detail.http_status|default:'—'} · {$detail.duration_ms} ms · {$detail.date_add}</h4>
{if $detail.error}<div class="alert alert-danger">{$detail.error|escape:'html':'UTF-8'}</div>{/if}
<h5>Request</h5><pre class="ch-raw">{$detail.request|escape}</pre><h5>Response</h5><pre class="ch-raw">{$detail.response|escape}</pre>
<a class="btn btn-default" href="{$self_url}">Close</a></div>{/if}
<table class="table table-condensed"><thead><tr><th>When</th><th>Channel</th><th>Dir</th><th>Type</th><th>Reference</th><th>HTTP</th><th>ms</th><th>Status</th><th>Error</th><th></th></tr></thead><tbody>
{foreach $logs as $l}<tr class="{if $l.status=='error'}danger{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M:%S"}</td><td>{$l.channel|escape:'html':'UTF-8'}</td><td>{$l.direction}</td><td>{$l.type|escape:'html':'UTF-8'}</td><td><small>{$l.reference|escape:'html':'UTF-8'}</small></td><td>{$l.http_status}</td><td>{$l.duration_ms}</td><td>{$l.status}</td><td><small>{$l.error|truncate:60|escape:'html':'UTF-8'}</small></td><td><a class="btn btn-xs btn-default" href="{$self_url}&id_log={$l.id_pulse_ch_log}">View</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>No messages logged yet.</em></td></tr>{/foreach}</tbody></table></div>

<div class="tab-pane" id="l-queue"><table class="table table-condensed"><thead><tr><th>#</th><th>Channel</th><th>Room type</th><th>Dates</th><th>Reason</th><th>Status</th><th>Attempts</th><th>Cells</th><th>Next try</th><th>Error</th><th></th></tr></thead><tbody>
{foreach $queue as $q}<tr class="{if $q.status=='poison'}danger{elseif $q.status=='failed'}warning{elseif $q.status=='sent'}text-muted{/if}"><td>{$q.id_pulse_ch_queue}</td><td>{$q.channel|escape:'html':'UTF-8'}</td><td>{$q.room_type|default:'all mapped'|escape:'html':'UTF-8'}</td><td>{$q.date_from} → {$q.date_to}</td><td>{$q.reason|escape:'html':'UTF-8'}</td><td>{$q.status}</td><td>{$q.attempts}</td><td>{$q.cells}</td><td>{$q.next_attempt_at|date_format:"%d/%m %H:%M"}</td><td><small>{$q.last_error|truncate:60|escape:'html':'UTF-8'}</small></td>
<td>{if $q.status!='sent'}<form method="post" class="inline"><input type="hidden" name="id_queue" value="{$q.id_pulse_ch_queue}"><button name="requeue" class="btn btn-xs btn-default">Retry</button> <button name="cancelQueue" class="btn btn-xs btn-link">✕</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="11"><em>Queue is empty.</em></td></tr>{/foreach}</tbody></table></div>

</div></div></div>
