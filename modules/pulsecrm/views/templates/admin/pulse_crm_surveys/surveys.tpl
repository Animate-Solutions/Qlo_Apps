<div class="pulse-crm"><div class="panel"><h3><i class="icon-comments"></i> Surveys</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmSurveys"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control">
  <select name="id_survey" class="form-control"><option value="0">All surveys</option>{foreach $surveys as $sv}<option value="{$sv.id_pulse_crm_survey|escape:'html':'UTF-8'}" {if $s && $s.id_pulse_crm_survey == $sv.id_pulse_crm_survey}selected{/if}>{$sv.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <select name="band" class="form-control"><option value="">All bands</option>
    <option value="promoter" {if $band == 'promoter'}selected{/if}>promoters</option><option value="passive" {if $band == 'passive'}selected{/if}>passives</option><option value="detractor" {if $band == 'detractor'}selected{/if}>detractors</option></select>
  <button class="btn btn-primary">Run</button></form>

<div class="row crm-kpis">
  <div class="col-md-3"><div class="crm-kpi"><span class="v {if $nps.nps < 0}text-danger{elseif $nps.nps >= 40}text-success{/if}">{$nps.nps|escape:'html':'UTF-8'}</span><span class="l">NPS</span><small>{$nps.promoters|escape:'html':'UTF-8'} promoters, {$nps.detractors|escape:'html':'UTF-8'} detractors</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$nps.gss|escape:'html':'UTF-8'}</span><span class="l">Satisfaction /100</span><small>mean of the 1–5 questions</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$rate.pct|escape:'html':'UTF-8'}%</span><span class="l">Response rate</span><small>{$rate.completed|escape:'html':'UTF-8'} of {$rate.invited|escape:'html':'UTF-8'}</small></div></div>
  <div class="col-md-3"><div class="crm-kpi"><span class="v">{$nps.responses|escape:'html':'UTF-8'}</span><span class="l">Completed</span><small>in this window</small></div></div>
</div>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#s-resp">Responses ({$responses|count})</a></li>
  <li><a data-toggle="tab" href="#s-dept">By department</a></li>
  <li><a data-toggle="tab" href="#s-trend">Trend</a></li>
  <li><a data-toggle="tab" href="#s-design">Design</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="s-resp">
<table class="table table-condensed"><thead><tr><th>When</th><th>Survey</th><th>Guest</th><th>Room</th><th>NPS</th><th>Satisfaction</th><th>Weakest</th><th>Sentiment</th><th>Comment</th><th></th></tr></thead><tbody>
{foreach $responses as $r}<tr class="{if $r.nps_band == 'detractor'}danger{elseif $r.nps_band == 'passive'}warning{/if}">
  <td>{$r.completed_at|date_format:"%d/%m %H:%M"}</td><td>{$r.survey_name|escape:'html':'UTF-8'}</td><td>{$r.guest|escape:'html':'UTF-8'}</td><td>{$r.room_num|escape:'html':'UTF-8'}</td>
  <td><b>{$r.nps|escape:'html':'UTF-8'}</b></td><td>{$r.gss|escape:'html':'UTF-8'}</td><td>{$r.department_low|escape:'html':'UTF-8'}</td><td>{$r.sentiment|escape:'html':'UTF-8'}</td><td><small>{$r.comment|truncate:80|escape:'html':'UTF-8'}</small></td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_response={$r.id_pulse_crm_survey_response|escape:'html':'UTF-8'}{if $s}&id_survey={$s.id_pulse_crm_survey|escape:'html':'UTF-8'}{/if}">Open</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nothing came back in this window.</em></td></tr>{/foreach}
</tbody></table>

{if $detail}
<h4>{$detail.survey_name|escape:'html':'UTF-8'} — {$detail.guest|escape:'html':'UTF-8'} on {$detail.completed_at|escape:'html':'UTF-8'}</h4>
<table class="table table-condensed"><tbody>
{foreach $detail.answers as $a}<tr class="{if $a.value_num !== null && $a.value_num <= 2 && $a.type == 'scale5'}danger{/if}"><td>{$a.label|escape:'html':'UTF-8'}</td><td>{if $a.value_num !== null}<b>{$a.value_num|intval}</b>{/if} {$a.value_text|escape:'html':'UTF-8'}</td><td><small class="text-muted">{$a.department|escape:'html':'UTF-8'}</small></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_response_a" value="{$detail.id_pulse_crm_survey_response|escape:'html':'UTF-8'}">
  <button name="openCaseFor" class="btn btn-default">Open a recovery case for this</button></form>
{/if}
</div>

<div class="tab-pane" id="s-dept">
<table class="table table-condensed"><thead><tr><th>Department</th><th>Answers</th><th>Mean /5</th><th>%</th><th>Poor (1–2)</th><th></th></tr></thead><tbody>
{foreach $dept as $d}<tr class="{if $d.avg_score < 3.5}danger{elseif $d.avg_score < 4}warning{/if}"><td>{$d.department|escape:'html':'UTF-8'}</td><td>{$d.answers|escape:'html':'UTF-8'}</td><td>{$d.avg_score|escape:'html':'UTF-8'}</td><td>{$d.pct|escape:'html':'UTF-8'}%</td><td>{$d.poor|escape:'html':'UTF-8'}</td>
  <td><div class="crm-bar"><span style="width:{$d.pct|escape:'html':'UTF-8'}%"></span></div></td></tr>
{foreachelse}<tr><td colspan="6"><em>No scored answers in this window.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-trend">
<table class="table table-condensed"><thead><tr><th>Month</th><th>Responses</th><th>NPS</th><th>Satisfaction</th><th></th></tr></thead><tbody>
{foreach $trend as $t}<tr><td>{$t.ym|escape:'html':'UTF-8'}</td><td>{$t.responses|escape:'html':'UTF-8'}</td><td><b>{$t.nps|escape:'html':'UTF-8'}</b></td><td>{$t.gss|escape:'html':'UTF-8'}</td><td><div class="crm-bar"><span style="width:{if $t.nps > 0}{$t.nps|escape:'html':'UTF-8'}{else}0{/if}%"></span></div></td></tr>
{foreachelse}<tr><td colspan="5"><em>No history yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="s-design">
<div class="row"><div class="col-md-4">
<table class="table table-condensed"><thead><tr><th>Survey</th><th>Touchpoint</th><th>Questions</th></tr></thead><tbody>
{foreach $surveys as $sv}<tr><td><a href="{$self_url}&id_survey={$sv.id_pulse_crm_survey|escape:'html':'UTF-8'}">{$sv.name|escape:'html':'UTF-8'}</a></td><td>{$sv.touchpoint|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$sv.code|escape:'html':'UTF-8'}</td></tr>{/foreach}
</tbody></table>
<h4>{if $s}Edit survey{else}New survey{/if}</h4>
<form method="post"><input type="hidden" name="id_survey_a" value="{if $s}{$s.id_pulse_crm_survey|escape:'html':'UTF-8'}{else}0{/if}">
  <input name="name" class="form-control" placeholder="Name" value="{if $s}{$s.name|escape:'html'}{/if}" required>
  <input name="code" class="form-control" placeholder="Code" value="{if $s}{$s.code|escape:'html'}{/if}" {if $s}readonly{/if}>
  <select name="touchpoint" class="form-control">
    <option value="in_stay" {if $s && $s.touchpoint == 'in_stay'}selected{/if}>In stay</option><option value="post_stay" {if $s && $s.touchpoint == 'post_stay'}selected{/if}>Post stay</option>
    <option value="fnb" {if $s && $s.touchpoint == 'fnb'}selected{/if}>Food &amp; beverage</option><option value="event" {if $s && $s.touchpoint == 'event'}selected{/if}>Event</option>
    <option value="spa" {if $s && $s.touchpoint == 'spa'}selected{/if}>Spa</option><option value="generic" {if $s && $s.touchpoint == 'generic'}selected{/if}>Generic</option></select>
  <textarea name="intro" class="form-control" rows="2" placeholder="Intro shown at the top of the page">{if $s}{$s.intro|escape:'html'}{/if}</textarea>
  <textarea name="thanks" class="form-control" rows="2" placeholder="Thank-you shown after submission">{if $s}{$s.thanks|escape:'html'}{/if}</textarea>
  <div class="row"><div class="col-xs-6"><input type="number" name="low_score_threshold" class="form-control" value="{if $s}{$s.low_score_threshold|escape:'html':'UTF-8'}{else}6{/if}" title="NPS at or below this opens a case"></div>
  <div class="col-xs-6"><input type="number" name="expiry_days" class="form-control" value="{if $s}{$s.expiry_days|escape:'html':'UTF-8'}{else}30{/if}" title="Link lifetime in days"></div></div>
  <label class="checkbox-inline"><input type="checkbox" name="active" value="1" {if !$s || $s.active}checked{/if}> Active</label>
  <button name="saveSurvey" class="btn btn-primary">Save</button>
</form>
</div><div class="col-md-8">
{if $s}
<h4>Questions in &ldquo;{$s.name|escape:'html':'UTF-8'}&rdquo;</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Code</th><th>Type</th><th>Question</th><th>Department</th><th>Required</th><th></th></tr></thead><tbody>
{foreach $s.questions as $q}<tr><td>{$q.sort|escape:'html':'UTF-8'}</td><td>{$q.code|escape:'html':'UTF-8'}</td><td>{$q.type|escape:'html':'UTF-8'}</td><td>{$q.label|escape:'html':'UTF-8'}{if $q.options} <small class="text-muted">({foreach $q.options as $op}{$op|escape:'html':'UTF-8'}{if !$op@last} / {/if}{/foreach})</small>{/if}</td><td>{$q.department|escape:'html':'UTF-8'}</td><td>{if $q.required}yes{/if}</td>
  <td><form method="post" class="inline"><input type="hidden" name="id_question" value="{$q.id_pulse_crm_survey_question|escape:'html':'UTF-8'}"><button name="delQuestion" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em>No questions.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_survey_a" value="{$s.id_pulse_crm_survey|escape:'html':'UTF-8'}"><input type="hidden" name="id_question" value="{(int)$smarty.get.id_question}">
  <input type="number" name="sort" class="form-control" value="{$s.questions|count + 1}" style="width:60px">
  <input name="qcode" class="form-control" placeholder="code" style="width:110px">
  <select name="type" class="form-control"><option value="nps">NPS 0–10</option><option value="scale5">Scale 1–5</option><option value="single">Single choice</option><option value="multi">Multi choice</option><option value="text">Free text</option><option value="bool">Yes / no</option></select>
  <input name="label" class="form-control" placeholder="The question" size="40" required>
  <input name="options" class="form-control" placeholder="Options | separated" size="25">
  <select name="department" class="form-control"><option value="">—</option><option value="frontdesk">frontdesk</option><option value="housekeeping">housekeeping</option><option value="engineering">engineering</option><option value="fnb">fnb</option><option value="security">security</option><option value="management">management</option></select>
  <label class="checkbox-inline"><input type="checkbox" name="required" value="1"> required</label>
  <button name="saveQuestion" class="btn btn-primary">Save question</button></form>

<h4>Send it</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_survey_a" value="{$s.id_pulse_crm_survey|escape:'html':'UTF-8'}">
  <input name="invite_email" class="form-control" placeholder="Guest email" size="30">
  <select name="invite_channel" class="form-control"><option value="email">email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select>
  <button name="sendInvite" class="btn btn-default">Send an invitation</button></form>
<p class="help-block">Each invitation gets its own unguessable link at <code>{$survey_base|escape:'html':'UTF-8'}</code> and expires after {$s.expiry_days|escape:'html':'UTF-8'} days. A score at or below {$s.low_score_threshold|escape:'html':'UTF-8'} opens a recovery case automatically, and if the guest is still in house it opens a ticket too.</p>
{/if}
</div></div>
</div>

</div></div></div>
