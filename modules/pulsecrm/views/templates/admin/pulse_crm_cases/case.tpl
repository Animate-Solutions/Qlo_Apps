<div class="pulse-crm"><div class="panel">
<h3><i class="icon-medkit"></i> {$c.case_no|escape:'html':'UTF-8'} — {$c.title|escape:'html':'UTF-8'} <small class="pull-right"><a href="{$self_url}">&larr; all cases</a></small></h3>
<p class="text-muted">Opened {$c.opened_at|escape:'html':'UTF-8'} from {$c.source|escape:'html':'UTF-8'} · {$c.department|escape:'html':'UTF-8'} · {$c.severity|escape:'html':'UTF-8'}
 {if $c.guest}· guest <b>{$c.guest|escape:'html':'UTF-8'}</b> {$c.email|escape:'html':'UTF-8'}{/if}{if $c.room_num} · room {$c.room_num|escape:'html':'UTF-8'}{/if}
 {if $c.overdue}<span class="badge crm-det">past SLA ({$c.sla_due|escape:'html':'UTF-8'})</span>{else}· SLA {$c.sla_due|escape:'html':'UTF-8'}{/if}
 {if $c.id_pulse_ticket}· linked to ticket #{$c.id_pulse_ticket|escape:'html':'UTF-8'}{/if}</p>

<div class="row"><div class="col-md-6">
<h4>What happened</h4>
<pre class="crm-note">{$c.description|escape:'html'}</pre>
{if $c.response}
<h4>The survey behind it</h4>
<p>NPS <b>{$c.response.nps|escape:'html':'UTF-8'}</b>, satisfaction {$c.response.gss|escape:'html':'UTF-8'}, weakest department {$c.response.department_low|escape:'html':'UTF-8'}, sentiment {$c.response.sentiment|escape:'html':'UTF-8'}.</p>
<pre class="crm-note">{$c.response.comment|escape:'html'}</pre>
{/if}

<h4>Say sorry</h4>
<form method="post"><input type="hidden" name="id_case_a" value="{$c.id_pulse_crm_case|escape:'html':'UTF-8'}">
  <textarea name="apology" class="form-control" rows="4" placeholder="What you are doing about it. Leave blank to send the recovery detail below.">{$c.recovery_detail|escape:'html'}</textarea>
  <br><button name="apologise" class="btn btn-default" {if !$c.id_customer}disabled{/if}>Send the apology</button>
  <span class="help-block">Goes out as a transactional message, so it ignores the marketing opt-out and the quiet window — an apology owed is not marketing.</span>
</form>
</div>

<div class="col-md-6">
<h4>Work the case</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_case_a" value="{$c.id_pulse_crm_case|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-4">Status</label><div class="col-sm-8"><select name="status" class="form-control">
    {foreach ['open','investigating','recovering','escalated','closed'] as $st}<option value="{$st|escape:'html':'UTF-8'}" {if $c.status == $st}selected{/if}>{$st|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Severity</label><div class="col-sm-8"><select name="severity" class="form-control">
    <option value="low" {if $c.severity == 'low'}selected{/if}>low</option><option value="medium" {if $c.severity == 'medium'}selected{/if}>medium</option>
    <option value="high" {if $c.severity == 'high'}selected{/if}>high</option><option value="critical" {if $c.severity == 'critical'}selected{/if}>critical</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><select name="department" class="form-control">
    {foreach ['frontdesk','housekeeping','engineering','fnb','security','management','other'] as $d}<option value="{$d|escape:'html':'UTF-8'}" {if $c.department == $d}selected{/if}>{$d|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Owner</label><div class="col-sm-8"><select name="owner" class="form-control"><option value="">—</option>
    {foreach $employees as $e}<option value="{$e.id_employee|escape:'html':'UTF-8'}" {if $c.owner == $e.id_employee}selected{/if}>{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Root cause</label><div class="col-sm-8"><input name="root_cause" class="form-control" value="{$c.root_cause|escape:'html'}" placeholder="Why it happened, not who did it"></div></div>
  <div class="form-group"><label class="col-sm-4">Recovery</label><div class="col-sm-8"><select name="recovery_action" class="form-control">
    {foreach ['none','apology','comp','discount','upgrade','gift','letter','refund','points'] as $a}<option value="{$a|escape:'html':'UTF-8'}" {if $c.recovery_action == $a}selected{/if}>{$a|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">What you gave</label><div class="col-sm-8"><input name="recovery_detail" class="form-control" value="{$c.recovery_detail|escape:'html'}" placeholder="Dinner for two, one night comped, suite upgrade…"></div></div>
  <div class="form-group"><label class="col-sm-4">What it cost</label><div class="col-sm-8"><input name="recovery_cost" class="form-control" value="{$c.recovery_cost|floatval}"></div></div>
  <div class="form-group"><label class="col-sm-4">Closing note</label><div class="col-sm-8"><textarea name="closing_note" class="form-control" rows="3">{$c.closing_note|escape:'html'}</textarea></div></div>
  <button name="updateCase" class="btn btn-primary">Save</button>
</form>
<p class="help-block">Closing needs a root cause and a closing note. That is deliberate: a case closed with nothing written down teaches the hotel nothing and the same glitch comes back next month.</p>

<h4>Post it to the bill</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_case_a" value="{$c.id_pulse_crm_case|escape:'html':'UTF-8'}">
  <select name="charge_code" class="form-control"><option value="ADJ">ADJ — adjustment</option><option value="LOYR">LOYR — loyalty/goodwill</option></select>
  <button name="postRecovery" class="btn btn-default" {if !$fd || $c.recovery_posted_line}disabled{/if}>Post the credit</button>
  {if $c.recovery_posted_line}<span class="text-muted"> — already posted as folio line {$c.recovery_posted_line|escape:'html':'UTF-8'}</span>{/if}
</form>
<p class="help-block">Only a comp, a discount or a refund posts — a gift or a letter costs money too, but not on this guest's folio, so it stays recorded on the case alone.</p>
</div></div>
</div></div>
