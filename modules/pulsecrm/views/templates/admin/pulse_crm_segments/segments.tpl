<div class="pulse-crm"><div class="panel"><h3><i class="icon-filter"></i> Segments</h3>
<div class="row"><div class="col-md-5">
<form method="post" class="form-inline noprint"><button name="refreshAll" class="btn btn-default btn-sm">Refresh all now</button>
<span class="text-muted"> — the cron does this hourly so campaign sends stay fast.</span></form>
<table class="table table-condensed"><thead><tr><th>Segment</th><th>Guests</th><th>Refreshed</th><th></th></tr></thead><tbody>
{foreach $segments as $sg}<tr class="{if $sg.last_error}danger{elseif !$sg.active}text-muted{/if}">
  <td><a href="{$self_url}&id_segment={$sg.id_pulse_crm_segment|escape:'html':'UTF-8'}">{$sg.name|escape:'html':'UTF-8'}</a>{if $sg.is_system} <span class="badge">built in</span>{/if}<br><small class="text-muted">{$sg.description|escape:'html':'UTF-8'}</small>{if $sg.last_error}<br><small class="text-danger">{$sg.last_error|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{$sg.member_count|escape:'html':'UTF-8'}</td>
  <td><small>{$sg.last_refresh|escape:'html':'UTF-8'}{if $sg.refresh_ms} · {$sg.refresh_ms|escape:'html':'UTF-8'}ms{/if}</small></td>
  <td><form method="post" class="inline"><input type="hidden" name="id_segment_a" value="{$sg.id_pulse_crm_segment|escape:'html':'UTF-8'}">
    <button name="refreshSegment" class="btn btn-xs btn-default">Refresh</button>
    {if !$sg.is_system}<button name="deleteSegment" class="btn btn-xs btn-link" onclick="return confirm('Delete this segment?')">✕</button>{/if}</form></td></tr>
{/foreach}</tbody></table>
</div><div class="col-md-7">
<h4>{if $s}Edit &ldquo;{$s.name|escape:'html':'UTF-8'}&rdquo;{else}New segment{/if}</h4>
<form method="post" id="crm-segment-form">
  <input type="hidden" name="id_segment" value="{if $s}{$s.id_pulse_crm_segment|escape:'html':'UTF-8'}{else}0{/if}">
  <div class="row"><div class="col-md-6"><input name="name" class="form-control" placeholder="Name" value="{if $s}{$s.name|escape:'html'}{/if}" required></div>
  <div class="col-md-6"><input name="code" class="form-control" placeholder="Code (optional)" value="{if $s}{$s.code|escape:'html'}{/if}" {if $s}readonly{/if}></div></div>
  <br><input name="description" class="form-control" placeholder="What is this segment for?" value="{if $s}{$s.description|escape:'html'}{/if}">
  <br><label>Match <select name="match" class="form-control input-sm" style="width:auto;display:inline-block">
    <option value="all" {if $match != 'any'}selected{/if}>all of these rules</option><option value="any" {if $match == 'any'}selected{/if}>any of these rules</option></select></label>
  <table class="table table-condensed" id="crm-rules"><tbody>
  {foreach $rules as $r}
  <tr class="crm-rule"><td><select name="rule_field[]" class="form-control input-sm">{foreach $fields as $key => $f}<option value="{$key|escape:'html':'UTF-8'}" {if $r.field == $key}selected{/if}>{$f.label|escape:'html':'UTF-8'}</option>{/foreach}</select></td>
    <td><select name="rule_op[]" class="form-control input-sm">{foreach $ops as $ok => $ov}<option value="{$ok|escape:'html':'UTF-8'}" {if $r.op == $ok}selected{/if}>{$ok|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select></td>
    <td><input name="rule_value[]" class="form-control input-sm" value="{$r.value|escape:'html'}"></td>
    <td><button type="button" class="btn btn-xs btn-link crm-rule-del">✕</button></td></tr>
  {/foreach}
  <tr class="crm-rule"><td><select name="rule_field[]" class="form-control input-sm"><option value="">— add a rule —</option>{foreach $fields as $key => $f}<option value="{$key|escape:'html':'UTF-8'}">{$f.label|escape:'html':'UTF-8'}</option>{/foreach}</select></td>
    <td><select name="rule_op[]" class="form-control input-sm">{foreach $ops as $ok => $ov}<option value="{$ok|escape:'html':'UTF-8'}">{$ok|replace:'_':' '|escape:'html':'UTF-8'}</option>{/foreach}</select></td>
    <td><input name="rule_value[]" class="form-control input-sm"></td>
    <td><button type="button" class="btn btn-xs btn-link crm-rule-del">✕</button></td></tr>
  </tbody></table>
  <button type="button" class="btn btn-default btn-sm" id="crm-add-rule">Add another rule</button>
  <button name="previewSegment" class="btn btn-default">Preview</button>
  <button name="saveSegment" class="btn btn-primary">Save and refresh</button>
  <label class="checkbox-inline"><input type="checkbox" name="active" value="1" {if !$s || $s.active}checked{/if}> Active</label>
</form>
<p class="help-block">Recency is in days: &ldquo;Days since last stay&rdquo; ≥ 365 is your lapsed list. &ldquo;Days to birthday&rdquo; ≤ 30 catches the ones coming up. Text rules take a comma-separated list with <b>in</b>, or a fragment with <b>contains</b>.</p>

{if $preview}
<h4>Preview — {$preview.count|escape:'html':'UTF-8'} guests</h4>
<table class="table table-condensed"><tbody>{foreach $preview.rows as $r}<tr><td>{$r.firstname|escape:'html':'UTF-8'} {$r.lastname|escape:'html':'UTF-8'}</td><td>{$r.email|escape:'html':'UTF-8'}</td><td>{$r.stays|escape:'html':'UTF-8'} stays</td><td>{displayPrice price=$r.lifetime_revenue}</td><td>{$r.last_stay|escape:'html':'UTF-8'}</td></tr>{/foreach}</tbody></table>
{/if}

{if $s && $members}
<h4>Members ({$s.member_count|escape:'html':'UTF-8'})</h4>
<table class="table table-condensed"><tbody>{foreach $members as $m}<tr><td>{$m.firstname|escape:'html':'UTF-8'} {$m.lastname|escape:'html':'UTF-8'}</td><td>{$m.email|escape:'html':'UTF-8'}</td><td>{$m.stays|escape:'html':'UTF-8'} stays</td><td>{displayPrice price=$m.lifetime_revenue}</td><td>{$m.last_stay|escape:'html':'UTF-8'}</td></tr>{/foreach}</tbody></table>
{/if}
</div></div>
</div></div>
