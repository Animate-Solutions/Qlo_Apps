<div class="pulse-crm"><div class="panel"><h3><i class="icon-random"></i> Journeys</h3>
<form method="post" class="form-inline noprint"><button name="advanceNow" class="btn btn-default btn-sm">Advance due steps now</button>
  <button name="triggerScheduled" class="btn btn-default btn-sm">Fire the calendar triggers</button>
  <span class="text-muted"> — the cron does both; these are for when you want to see it happen.</span></form>

<div class="row"><div class="col-md-4">
<table class="table table-condensed"><thead><tr><th>Journey</th><th>Trigger</th><th>Steps</th><th>Live</th><th></th></tr></thead><tbody>
{foreach $journeys as $jj}<tr class="{if !$jj.active}text-muted{/if}">
  <td><a href="{$self_url}&id_journey={$jj.id_pulse_crm_journey|escape:'html':'UTF-8'}">{$jj.name|escape:'html':'UTF-8'}</a><br><small class="text-muted">{$jj.description|escape:'html':'UTF-8'}</small></td>
  <td>{$triggers[$jj.trigger_event]|default:$jj.trigger_event|escape:'html':'UTF-8'}</td><td>{$jj.steps|count}</td><td>{$jj.count_started|escape:'html':'UTF-8'} started / {$jj.count_done|escape:'html':'UTF-8'} done</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_journey_a" value="{$jj.id_pulse_crm_journey|escape:'html':'UTF-8'}"><input type="hidden" name="active" value="{if $jj.active}0{else}1{/if}">
    <button name="toggleJourney" class="btn btn-xs {if $jj.active}btn-success{else}btn-default{/if}">{if $jj.active}on{else}off{/if}</button></form></td></tr>
{/foreach}</tbody></table>

<h4>{if $j}Edit journey{else}New journey{/if}</h4>
<form method="post"><input type="hidden" name="id_journey_a" value="{if $j}{$j.id_pulse_crm_journey|escape:'html':'UTF-8'}{else}0{/if}">
  <input name="name" class="form-control" placeholder="Name" value="{if $j}{$j.name|escape:'html'}{/if}" required>
  <input name="code" class="form-control" placeholder="Code" value="{if $j}{$j.code|escape:'html'}{/if}" {if $j}readonly{/if}>
  <input name="description" class="form-control" placeholder="What it does" value="{if $j}{$j.description|escape:'html'}{/if}">
  <select name="trigger_event" class="form-control">{foreach $triggers as $k => $lbl}<option value="{$k|escape:'html':'UTF-8'}" {if $j && $j.trigger_event == $k}selected{/if}>{$lbl|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <div class="row"><div class="col-xs-4"><input name="quiet_from" class="form-control" value="{if $j}{$j.quiet_from|escape:'html':'UTF-8'}{else}21:00{/if}" title="Quiet from"></div>
  <div class="col-xs-4"><input name="quiet_to" class="form-control" value="{if $j}{$j.quiet_to|escape:'html':'UTF-8'}{else}08:00{/if}" title="Quiet to"></div>
  <div class="col-xs-4"><input type="number" name="suppress_days" class="form-control" value="{if $j}{$j.suppress_days|escape:'html':'UTF-8'}{else}1{/if}" title="Suppression days"></div></div>
  <label class="checkbox-inline"><input type="checkbox" name="active" value="1" {if !$j || $j.active}checked{/if}> Active</label>
  <button name="saveJourney" class="btn btn-primary">Save journey</button>
</form>
<p class="help-block">Suppression days is the promise that a guest never gets two automated messages inside that window, however many journeys they happen to be on.</p>
</div>

<div class="col-md-8">
{if $j}
<h4>Steps in &ldquo;{$j.name|escape:'html':'UTF-8'}&rdquo;</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Step</th><th>Delay</th><th>Condition</th><th>Action</th><th>Channel</th><th></th></tr></thead><tbody>
{foreach $j.steps as $s}<tr><td>{$s.sort|escape:'html':'UTF-8'}</td><td>{$s.name|escape:'html':'UTF-8'}<br><small class="text-muted">{$s.subject|escape:'html':'UTF-8'}</small></td>
  <td>{if $s.delay_minutes >= 1440}{($s.delay_minutes/1440)|string_format:"%.1f"}d{elseif $s.delay_minutes >= 60}{($s.delay_minutes/60)|string_format:"%.1f"}h{else}{$s.delay_minutes|escape:'html':'UTF-8'}m{/if}</td>
  <td><small>{$s.condition_json|truncate:60|escape:'html':'UTF-8'}</small></td><td>{$actions[$s.action]|default:$s.action|escape:'html':'UTF-8'}<br><small class="text-muted">{$s.action_json|truncate:50|escape:'html':'UTF-8'}</small></td><td>{$s.channel|escape:'html':'UTF-8'}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_step" value="{$s.id_pulse_crm_journey_step|escape:'html':'UTF-8'}"><button name="delStep" class="btn btn-xs btn-link" onclick="return confirm('Remove this step?')">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em>No steps yet — a journey with no steps never starts.</em></td></tr>{/foreach}
</tbody></table>

<h4>Add or edit a step</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_journey_a" value="{$j.id_pulse_crm_journey|escape:'html':'UTF-8'}"><input type="hidden" name="id_step" value="{(int)$smarty.get.id_step}">
<div class="row"><div class="col-md-6">
  <div class="form-group"><label class="col-sm-4">Name</label><div class="col-sm-8"><input name="sname" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-4">Order</label><div class="col-sm-8"><input type="number" name="sort" class="form-control" value="{$j.steps|count + 1}"></div></div>
  <div class="form-group"><label class="col-sm-4">Delay (minutes)</label><div class="col-sm-8"><input type="number" name="delay_minutes" class="form-control" value="0"><span class="help-block">1440 = a day, 10080 = a week, 129600 = 90 days.</span></div></div>
  <div class="form-group"><label class="col-sm-4">Action</label><div class="col-sm-8"><select name="action" class="form-control">{foreach $actions as $k => $lbl}<option value="{$k|escape:'html':'UTF-8'}">{$lbl|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Channel</label><div class="col-sm-8"><select name="channel" class="form-control"><option value="auto">the guest's preference</option><option value="email">email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Only if</label><div class="col-sm-8">
    <select name="cond_field" class="form-control"><option value="">— always run —</option>
      <option value="nps_band">NPS band</option><option value="nps">NPS score</option><option value="gss">Satisfaction</option><option value="stays">Stays</option>
      <option value="nights">Nights</option><option value="lifetime_revenue">Lifetime revenue</option><option value="last_stay_days">Days since last stay</option><option value="is_member">Is a loyalty member</option></select>
    <select name="cond_op" class="form-control"><option value="eq">is</option><option value="ne">is not</option><option value="gte">at least</option><option value="lte">at most</option><option value="gt">more than</option><option value="lt">less than</option></select>
    <input name="cond_value" class="form-control" placeholder="value"></div></div>
</div><div class="col-md-6">
  <div class="form-group"><label class="col-sm-3">Subject</label><div class="col-sm-9"><input name="subject" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-3">Body</label><div class="col-sm-9"><textarea name="body" class="form-control" rows="6" placeholder="Dear {ldelim}first_name{rdelim}, ..."></textarea></div></div>
  <div class="form-group"><label class="col-sm-3">Template code</label><div class="col-sm-9"><input name="template_code" class="form-control" placeholder="crm_welcome — leave blank to use the body above"></div></div>
  <div class="form-group"><label class="col-sm-3">Attach survey</label><div class="col-sm-9"><select name="act_survey" class="form-control"><option value="">—</option>{foreach $surveys as $s}<option value="{$s.code|escape:'html':'UTF-8'}">{$s.name|escape:'html':'UTF-8'}</option>{/foreach}</select><span class="help-block">Adds {ldelim}survey_url{rdelim} to the message.</span></div></div>
  <div class="form-group"><label class="col-sm-3">Tag to add</label><div class="col-sm-9"><select name="act_tag" class="form-control"><option value="">—</option>{foreach $tags as $t}<option value="{$t.code|escape:'html':'UTF-8'}">{$t.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">Points</label><div class="col-sm-9"><input type="number" name="act_points" class="form-control" placeholder="for the add-points action"></div></div>
  <div class="form-group"><label class="col-sm-3">Department</label><div class="col-sm-9"><input name="act_department" class="form-control" placeholder="for a ticket or a case"> <input name="act_priority" class="form-control" placeholder="priority"> <input name="act_title" class="form-control" placeholder="title"></div></div>
</div></div>
<button name="saveStep" class="btn btn-primary">Save step</button>
</form>
{/if}

<h4>Live runs</h4>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmJourneys"><input type="hidden" name="token" value="{$smarty.get.token|escape}">{if $j}<input type="hidden" name="id_journey" value="{$j.id_pulse_crm_journey|escape:'html':'UTF-8'}">{/if}
  <select name="rstatus" class="form-control input-sm"><option value="">all</option>
    <option value="active" {if $rstatus == 'active'}selected{/if}>active</option><option value="done" {if $rstatus == 'done'}selected{/if}>done</option>
    <option value="cancelled" {if $rstatus == 'cancelled'}selected{/if}>cancelled</option><option value="failed" {if $rstatus == 'failed'}selected{/if}>failed</option></select>
  <button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Journey</th><th>Guest</th><th>Step</th><th>Status</th><th>Next run</th><th>Last error</th><th></th></tr></thead><tbody>
{foreach $runs as $r}<tr class="{if $r.status == 'failed'}danger{elseif $r.status == 'cancelled'}warning{/if}">
  <td>{$r.journey|escape:'html':'UTF-8'}</td><td>{$r.guest|escape:'html':'UTF-8'}</td><td>{$r.step_index|escape:'html':'UTF-8'}</td><td>{$r.status|escape:'html':'UTF-8'}</td><td>{$r.next_run_at|date_format:"%d/%m %H:%M"}</td><td><small>{$r.last_error|escape:'html':'UTF-8'}</small></td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}{if $j}&id_journey={$j.id_pulse_crm_journey|escape:'html':'UTF-8'}{/if}&id_run={$r.id_pulse_crm_journey_run|escape:'html':'UTF-8'}">Log</a>
    {if $r.status == 'active'}<form method="post" class="inline"><input type="hidden" name="id_run_a" value="{$r.id_pulse_crm_journey_run|escape:'html':'UTF-8'}"><button name="cancelRun" class="btn btn-xs btn-link">stop</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="7"><em>No runs.</em></td></tr>{/foreach}
</tbody></table>

{if $logs}
<h4>Log for run {$id_run|escape:'html':'UTF-8'}</h4>
<table class="table table-condensed"><tbody>{foreach $logs as $l}<tr class="{if $l.result == 'failed'}danger{elseif $l.result == 'skipped'}warning{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M"}</td><td>{$l.action|escape:'html':'UTF-8'}</td><td>{$l.result|escape:'html':'UTF-8'}</td><td>{$l.message|escape:'html':'UTF-8'}</td></tr>{/foreach}</tbody></table>
{/if}
</div></div>
</div></div>
