<div class="pulse-crm"><div class="panel">
<h3><i class="icon-bullhorn"></i> {$c.name|escape:'html':'UTF-8'} <span class="badge crm-st-{$c.status|escape:'html':'UTF-8'}">{$c.status|escape:'html':'UTF-8'}</span> <small class="pull-right"><a href="{$self_url}">&larr; all campaigns</a></small></h3>
{if $quiet}<div class="alert alert-warning">Quiet hours are in force — pressing Send now will hold the batch until the window closes. The cron will pick it up.</div>{/if}

<div class="row"><div class="col-md-7">
<form method="post" class="form-horizontal"><input type="hidden" name="id_campaign_a" value="{$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-3">Name</label><div class="col-sm-9"><input name="name" class="form-control" value="{$c.name|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Channel</label><div class="col-sm-9"><select name="channel" class="form-control">
    <option value="email" {if $c.channel == 'email'}selected{/if}>Email</option><option value="sms" {if $c.channel == 'sms'}selected{/if}>SMS</option><option value="whatsapp" {if $c.channel == 'whatsapp'}selected{/if}>WhatsApp</option></select></div></div>
  <div class="form-group"><label class="col-sm-3">Segment</label><div class="col-sm-9"><select name="id_pulse_crm_segment" class="form-control">
    {foreach $segments as $s}<option value="{$s.id_pulse_crm_segment|escape:'html':'UTF-8'}" {if $c.id_pulse_crm_segment == $s.id_pulse_crm_segment}selected{/if}>{$s.name|escape:'html':'UTF-8'} ({$s.member_count|escape:'html':'UTF-8'})</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-3">Schedule</label><div class="col-sm-9"><select name="schedule_type" class="form-control">
    <option value="manual" {if $c.schedule_type == 'manual'}selected{/if}>Manual</option>
    <option value="once" {if $c.schedule_type == 'once'}selected{/if}>Once</option>
    <option value="daily" {if $c.schedule_type == 'daily'}selected{/if}>Daily</option>
    <option value="weekly" {if $c.schedule_type == 'weekly'}selected{/if}>Weekly</option>
    <option value="monthly" {if $c.schedule_type == 'monthly'}selected{/if}>Monthly</option></select>
    <input type="datetime-local" name="send_at" class="form-control" value="{$c.send_at|replace:' ':'T'|escape:'html':'UTF-8'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Recurs on</label><div class="col-sm-9"><input type="number" name="recur_dow" class="form-control" value="{$c.recur_dow|escape:'html':'UTF-8'}" min="0" max="6"> <input type="number" name="recur_dom" class="form-control" value="{$c.recur_dom|escape:'html':'UTF-8'}" min="1" max="28"></div></div>
  <div class="form-group"><label class="col-sm-3">Per run</label><div class="col-sm-9"><input type="number" name="throttle_per_run" class="form-control" value="{$c.throttle_per_run|escape:'html':'UTF-8'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Quiet hours</label><div class="col-sm-9"><input name="quiet_from" class="form-control" value="{$c.quiet_from|escape:'html':'UTF-8'}"> <input name="quiet_to" class="form-control" value="{$c.quiet_to|escape:'html':'UTF-8'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Subject</label><div class="col-sm-9"><input name="subject" class="form-control" value="{$c.subject|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Body</label><div class="col-sm-9"><textarea name="body" class="form-control" rows="10">{$c.body|escape:'html'}</textarea></div></div>
  <div class="form-group"><label class="col-sm-3">A/B split %</label><div class="col-sm-9"><input type="number" name="ab_split_pct" class="form-control" value="{$c.ab_split_pct|escape:'html':'UTF-8'}" min="0" max="50"></div></div>
  <div class="form-group"><label class="col-sm-3">Subject B</label><div class="col-sm-9"><input name="subject_b" class="form-control" value="{$c.subject_b|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-3">Body B</label><div class="col-sm-9"><textarea name="body_b" class="form-control" rows="6">{$c.body_b|escape:'html'}</textarea></div></div>
  <button name="saveCampaign" class="btn btn-primary">Save</button>
</form>
<p class="help-block">Merge tags: {foreach $tags as $t}<code>{ldelim}{$t|escape:'html':'UTF-8'}{rdelim}</code> {/foreach} — anything else is stripped before the message goes out, so a guest never sees a raw tag.</p>
</div>

<div class="col-md-5">
<h4>Run it</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_campaign_a" value="{$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">
  <button name="queueCampaign" class="btn btn-default">1. Build the list</button>
  <input type="number" name="slice" class="form-control" placeholder="slice" style="width:90px" value="{$c.throttle_per_run|escape:'html':'UTF-8'}">
  <button name="sendCampaign" class="btn btn-primary">2. Send a slice</button>
</form>
<form method="post" class="form-inline" style="margin-top:8px"><input type="hidden" name="id_campaign_a" value="{$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">
  <input name="test_email" class="form-control" placeholder="Test to this guest's email" size="30"> <button name="testCampaign" class="btn btn-default btn-sm">Send a test</button>
</form>
<form method="post" class="form-inline" style="margin-top:8px"><input type="hidden" name="id_campaign_a" value="{$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">
  <select name="status" class="form-control input-sm"><option value="draft">draft</option><option value="scheduled">scheduled</option><option value="paused">paused</option><option value="cancelled">cancelled</option></select>
  <button name="setCampaignStatus" class="btn btn-default btn-sm">Set status</button>
  <button name="deleteCampaign" class="btn btn-link btn-sm" onclick="return confirm('Delete this campaign and its recipient log?')">Delete</button>
</form>

<h4>Results</h4>
<table class="table table-condensed"><thead><tr><th>Variant</th><th>List</th><th>Sent</th><th>Open</th><th>Click</th><th>Unsub</th><th>Failed</th><th>Skipped</th></tr></thead><tbody>
{foreach $c.stats as $st}<tr><td>{$st.variant|upper|escape:'html':'UTF-8'}</td><td>{$st.total|escape:'html':'UTF-8'}</td><td>{$st.sent|escape:'html':'UTF-8'}</td><td>{$st.opened|escape:'html':'UTF-8'} ({$st.open_pct|escape:'html':'UTF-8'}%)</td><td>{$st.clicked|escape:'html':'UTF-8'} ({$st.click_pct|escape:'html':'UTF-8'}%)</td><td>{$st.unsub|escape:'html':'UTF-8'} ({$st.unsub_pct|escape:'html':'UTF-8'}%)</td><td>{$st.failed|escape:'html':'UTF-8'}</td><td>{$st.skipped|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing queued yet.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">Opens are counted by a tracking pixel — a guest reading with images off is invisible, so treat the open rate as a floor, not a fact. Clicks are exact.</p>
</div></div>

<h4>Recipients</h4>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmCampaigns"><input type="hidden" name="token" value="{$smarty.get.token|escape}"><input type="hidden" name="id_campaign" value="{$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">
  <select name="rstatus" class="form-control input-sm"><option value="">all</option>
  <option value="queued" {if $rstatus == 'queued'}selected{/if}>queued</option><option value="sent" {if $rstatus == 'sent'}selected{/if}>sent</option>
  <option value="opened" {if $rstatus == 'opened'}selected{/if}>opened</option><option value="clicked" {if $rstatus == 'clicked'}selected{/if}>clicked</option>
  <option value="unsubscribed" {if $rstatus == 'unsubscribed'}selected{/if}>unsubscribed</option><option value="failed" {if $rstatus == 'failed'}selected{/if}>failed</option>
  <option value="skipped" {if $rstatus == 'skipped'}selected{/if}>skipped</option></select>
  <button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Guest</th><th>To</th><th>Variant</th><th>Status</th><th>Why skipped</th><th>Sent</th><th>Opened</th><th>Clicked</th></tr></thead><tbody>
{foreach $recipients as $r}<tr class="{if $r.status == 'failed'}danger{elseif $r.status == 'unsubscribed'}warning{/if}">
  <td>{$r.guest|escape:'html':'UTF-8'}</td><td><small>{$r.to_addr|escape:'html':'UTF-8'}</small></td><td>{$r.variant|upper|escape:'html':'UTF-8'}</td><td>{$r.status|escape:'html':'UTF-8'}</td><td><small>{$r.skip_reason|escape:'html':'UTF-8'}{$r.error|escape:'html':'UTF-8'}</small></td>
  <td>{$r.date_sent|date_format:"%d/%m %H:%M"}</td><td>{if $r.open_count}{$r.open_count|escape:'html':'UTF-8'}× {$r.date_opened|date_format:"%d/%m %H:%M"}{/if}</td><td>{if $r.click_count}{$r.click_count|escape:'html':'UTF-8'}× {$r.date_clicked|date_format:"%d/%m %H:%M"}{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em>The list is empty — press &ldquo;Build the list&rdquo;.</em></td></tr>{/foreach}
</tbody></table>
</div></div>
