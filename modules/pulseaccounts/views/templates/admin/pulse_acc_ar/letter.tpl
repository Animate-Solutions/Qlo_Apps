<div class="pulse-acc">
{if !$letter}<div class="alert alert-danger">That letter does not exist. <a href="{$self_url}">Back to receivables</a></div>{else}
<div class="panel"><h3><i class="icon-envelope"></i> Dunning letter — {$letter.company_name|escape} (level {$letter.level})</h3>
<div class="letter">{$letter.body nofilter}</div>
<div class="noprint" style="margin-top:12px">
<a class="btn btn-default" href="javascript:window.print()">Print</a>
{if $letter.status != 'sent'}<form method="post" class="inline"><input type="hidden" name="id_dunning_s" value="{$letter.id_pulse_acc_dunning}">
<button name="sendLetter" class="btn btn-primary">E-mail and mark sent</button></form>{else}<span class="badge" style="background:#27ae60">sent {$letter.sent_at}</span>{/if}
<a class="btn btn-default" href="{$self_url}">Back</a>
</div>
</div>
{/if}</div>
