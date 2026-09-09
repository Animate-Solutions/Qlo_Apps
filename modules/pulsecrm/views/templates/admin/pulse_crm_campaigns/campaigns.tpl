<div class="pulse-crm"><div class="panel"><h3><i class="icon-bullhorn"></i> Campaigns</h3>
{if $quiet}<div class="alert alert-warning">It is quiet hours ({$quiet_from|escape:'html':'UTF-8'}–{$quiet_to|escape:'html':'UTF-8'}, Africa/Lagos). Sends are held until {$quiet_to|escape:'html':'UTF-8'}; you can still compose and queue.</div>{/if}
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#cm-list">All campaigns ({$campaigns|count})</a></li><li><a data-toggle="tab" href="#cm-new">New campaign</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="cm-list">
<table class="table table-condensed"><thead><tr><th>Campaign</th><th>Channel</th><th>Segment</th><th>Schedule</th><th>Status</th><th>Queued</th><th>Sent</th><th>Open</th><th>Click</th><th>Unsub</th><th>Skipped</th><th></th></tr></thead><tbody>
{foreach $campaigns as $c}<tr>
  <td><a href="{$self_url}&id_campaign={$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">{$c.name|escape:'html':'UTF-8'}</a></td><td>{$c.channel|escape:'html':'UTF-8'}</td><td>{$c.segment_name|escape:'html':'UTF-8'} {if $c.member_count}<small class="text-muted">({$c.member_count|escape:'html':'UTF-8'})</small>{/if}</td>
  <td>{$c.schedule_type|escape:'html':'UTF-8'}{if $c.send_at} {$c.send_at|date_format:"%d/%m %H:%M"}{/if}</td>
  <td><span class="badge crm-st-{$c.status|escape:'html':'UTF-8'}">{$c.status|escape:'html':'UTF-8'}</span></td>
  <td>{$c.count_queued|escape:'html':'UTF-8'}</td><td>{$c.count_sent|escape:'html':'UTF-8'}</td>
  <td>{$c.count_opened|escape:'html':'UTF-8'}{if $c.count_sent > 0} <small class="text-muted">({($c.count_opened/$c.count_sent*100)|string_format:"%.0f"}%)</small>{/if}</td>
  <td>{$c.count_clicked|escape:'html':'UTF-8'}{if $c.count_sent > 0} <small class="text-muted">({($c.count_clicked/$c.count_sent*100)|string_format:"%.0f"}%)</small>{/if}</td>
  <td>{$c.count_unsub|escape:'html':'UTF-8'}</td><td>{$c.count_skipped|escape:'html':'UTF-8'}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_campaign={$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="12"><em>No campaigns yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="cm-new">
<form method="post" class="form-horizontal">
  <div class="row"><div class="col-md-6">
    <div class="form-group"><label class="col-sm-3">Name</label><div class="col-sm-9"><input name="name" class="form-control" required></div></div>
    <div class="form-group"><label class="col-sm-3">Channel</label><div class="col-sm-9"><select name="channel" class="form-control"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></div></div>
    <div class="form-group"><label class="col-sm-3">Segment</label><div class="col-sm-9"><select name="id_pulse_crm_segment" class="form-control" required><option value="">—</option>{foreach $segments as $s}<option value="{$s.id_pulse_crm_segment|escape:'html':'UTF-8'}">{$s.name|escape:'html':'UTF-8'} ({$s.member_count|escape:'html':'UTF-8'})</option>{/foreach}</select></div></div>
    <div class="form-group"><label class="col-sm-3">Schedule</label><div class="col-sm-9"><select name="schedule_type" class="form-control"><option value="manual">Manual — I press send</option><option value="once">Once at</option><option value="daily">Every day</option><option value="weekly">Every week</option><option value="monthly">Every month</option></select>
      <input type="datetime-local" name="send_at" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-3">Recurs on</label><div class="col-sm-9"><input type="number" name="recur_dow" class="form-control" value="1" min="0" max="6" placeholder="Day of week (0=Sun)"> <input type="number" name="recur_dom" class="form-control" value="1" min="1" max="28" placeholder="Day of month"></div></div>
    <div class="form-group"><label class="col-sm-3">Per run</label><div class="col-sm-9"><input type="number" name="throttle_per_run" class="form-control" value="100" min="1"><span class="help-block">Keep this modest on a shared host and on a link that drops.</span></div></div>
    <div class="form-group"><label class="col-sm-3">Quiet hours</label><div class="col-sm-9"><input name="quiet_from" class="form-control" value="{$quiet_from|escape:'html':'UTF-8'}"> <input name="quiet_to" class="form-control" value="{$quiet_to|escape:'html':'UTF-8'}"></div></div>
  </div><div class="col-md-6">
    <div class="form-group"><label class="col-sm-2">Subject</label><div class="col-sm-10"><input name="subject" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-2">Body</label><div class="col-sm-10"><textarea name="body" class="form-control" rows="8" placeholder="Dear {ldelim}first_name{rdelim}, ..."></textarea></div></div>
    <div class="form-group"><label class="col-sm-2">A/B split</label><div class="col-sm-10"><input type="number" name="ab_split_pct" class="form-control" value="0" min="0" max="50"> % of the list gets variant B</div></div>
    <div class="form-group"><label class="col-sm-2">Subject B</label><div class="col-sm-10"><input name="subject_b" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-2">Body B</label><div class="col-sm-10"><textarea name="body_b" class="form-control" rows="5"></textarea></div></div>
    <p class="help-block">Merge tags: {foreach $tags as $t}<code>{ldelim}{$t|escape:'html':'UTF-8'}{rdelim}</code> {/foreach}</p>
  </div></div>
  <button name="saveCampaign" class="btn btn-primary btn-lg">Create campaign</button>
</form>
</div>

</div></div></div>
