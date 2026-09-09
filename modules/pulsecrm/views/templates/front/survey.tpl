{extends file='page.tpl'}
{block name='page_content'}
<div class="pulse-guest pulse-crm-survey">
{if $invalid}
  <h2>That link is not valid</h2>
  <p>It may have been mistyped, or the survey has been withdrawn. Please contact reception and we will send you a fresh one.</p>
{elseif $already}
  <h2>Thank you — we already have your answers</h2>
  <p>{$r.thanks|default:'Thank you for taking the time.'}</p>
{elseif $expired}
  <h2>This survey has closed</h2>
  <p>The link expired on {$r.expires_on}. If there is still something you would like us to know, reply to the email and it will reach the general manager.</p>
{elseif isset($done) && $done}
  <h2>Thank you</h2>
  <p>{$r.thanks|default:'Thank you for taking the time.'}</p>
  {if $low}<p class="crm-low">You told us something went wrong. A duty manager has been alerted and will contact you — you do not need to do anything else.</p>{/if}
{else}
  <h2>{$r.survey_name}</h2>
  <p class="crm-intro">{$r.intro}</p>
  {if isset($error)}<div class="alert alert-danger">{$error}</div>{/if}
  <form method="post" action="{$action|escape:'html'}">
  {foreach $r.questions as $q}
    <div class="crm-q">
      <label class="crm-q-label">{$q.label}{if $q.required} <span class="crm-req">*</span>{/if}</label>
      {if $q.type == 'nps'}
        <div class="crm-nps">{section name=n start=0 loop=11}<label><input type="radio" name="q_{$q.code}" value="{$smarty.section.n.index}" {if $q.required}required{/if}><span>{$smarty.section.n.index}</span></label>{/section}</div>
        <div class="crm-nps-legend"><span>Not at all likely</span><span>Extremely likely</span></div>
      {elseif $q.type == 'scale5'}
        <div class="crm-scale">{section name=s start=1 loop=6}<label><input type="radio" name="q_{$q.code}" value="{$smarty.section.s.index}" {if $q.required}required{/if}><span>{$smarty.section.s.index}</span></label>{/section}</div>
        <div class="crm-nps-legend"><span>Poor</span><span>Excellent</span></div>
      {elseif $q.type == 'bool'}
        <label class="crm-inline"><input type="radio" name="q_{$q.code}" value="1"> Yes</label>
        <label class="crm-inline"><input type="radio" name="q_{$q.code}" value="0"> No</label>
      {elseif $q.type == 'single'}
        {foreach $q.options as $o}<label class="crm-inline"><input type="radio" name="q_{$q.code}" value="{$o|escape:'html'}"> {$o}</label>{/foreach}
      {elseif $q.type == 'multi'}
        {foreach $q.options as $o}<label class="crm-inline"><input type="checkbox" name="q_{$q.code}[]" value="{$o|escape:'html'}"> {$o}</label>{/foreach}
      {else}
        <textarea name="q_{$q.code}" rows="3" class="crm-text" placeholder="In your own words"></textarea>
      {/if}
    </div>
  {/foreach}
  <button name="submitSurvey" value="1" class="crm-submit">Send my answers</button>
  <p class="crm-foot">{$hotel} · Your answers go straight to the general manager. We do not share them.</p>
  </form>
{/if}
</div>
{literal}<style>
.pulse-crm-survey{max-width:560px;margin:0 auto;padding:16px;font:16px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#222}
.pulse-crm-survey h2{font-size:22px;margin:0 0 8px}
.crm-intro{color:#555;margin-bottom:20px}
.crm-q{margin:0 0 24px;padding-bottom:16px;border-bottom:1px solid #eee}
.crm-q-label{display:block;font-weight:600;margin-bottom:10px}
.crm-req{color:#c00}
.crm-nps,.crm-scale{display:flex;flex-wrap:wrap;gap:6px}
.crm-nps label,.crm-scale label{flex:1 1 auto;min-width:40px;text-align:center;cursor:pointer}
.crm-nps input,.crm-scale input{position:absolute;opacity:0;width:0;height:0}
.crm-nps span,.crm-scale span{display:block;padding:12px 0;border:1px solid #cfd6dd;border-radius:6px;background:#fff}
.crm-nps input:checked+span,.crm-scale input:checked+span{background:#2c7be5;border-color:#2c7be5;color:#fff;font-weight:700}
.crm-nps-legend{display:flex;justify-content:space-between;font-size:12px;color:#888;margin-top:6px}
.crm-inline{display:block;padding:8px 0}
.crm-text{width:100%;padding:10px;border:1px solid #cfd6dd;border-radius:6px;font:inherit}
.crm-submit{width:100%;padding:16px;font-size:17px;font-weight:600;color:#fff;background:#2c7be5;border:0;border-radius:8px;cursor:pointer}
.crm-foot{font-size:12px;color:#999;text-align:center;margin-top:16px}
.crm-low{padding:12px;background:#fff4e5;border-left:4px solid #f0a020;border-radius:4px}
@media(max-width:420px){.crm-nps label{min-width:32px}.crm-nps span,.crm-scale span{padding:10px 0;font-size:14px}}
</style>{/literal}
{/block}
