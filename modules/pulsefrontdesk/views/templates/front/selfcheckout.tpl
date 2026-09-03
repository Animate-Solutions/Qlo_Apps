{extends file='page.tpl'}
{block name='page_content'}
<div class="pulse-guest" style="max-width:720px;margin:0 auto">
{if isset($invalid)}<h2>Link not valid</h2><p>This check-out link has expired. Please visit the front desk.</p>
{elseif isset($done)}<h2>Thank you, {$b.guest}!</h2><p>You are checked out of room {$b.room_num}. Your receipt has been emailed. Please leave your key card in the room or drop it at reception. Safe travels!</p>
{else}
<h2>Express check-out &mdash; {$hotel}</h2><p>{$b.guest} · room {$b.room_num} · {$b.date_from} → {$b.date_to}</p>
{if isset($error)}<div class="alert alert-danger">{$error}</div>{/if}
<table class="table table-striped"><thead><tr><th>Date</th><th>Description</th><th class="text-right">Charge</th><th class="text-right">Payment</th></tr></thead><tbody>
{foreach $lines as $l}<tr><td>{$l.business_date}</td><td>{$l.description}</td><td class="text-right">{if !$l.is_payment}{$currency}{$l.amount_tax_incl|number_format:2}{/if}</td><td class="text-right">{if $l.is_payment}{$currency}{$l.amount_tax_incl|number_format:2}{/if}</td></tr>{/foreach}
<tr><td colspan="3"><b>Balance</b></td><td class="text-right"><b>{$currency}{$folio->balance|number_format:2}</b></td></tr></tbody></table>
{if $folio->balance > 0.009}
  {if $ext.card_auth_ref}<p>Your balance will be charged to the card you authorised at check-in ({$ext.card_auth_ref}).</p>
  {elseif $pay_url}<p><a class="btn btn-default btn-lg" href="{$pay_url}">Pay {$currency}{$folio->balance|number_format:2} online</a> then return to this page.</p>
  {else}<div class="alert alert-warning">There is a balance on your bill. Please settle at the front desk before departure.</div>{/if}
{/if}
<form method="post"><button name="submitCheckout" class="btn btn-primary btn-lg btn-block" {if $folio->balance > 0.009 && !$ext.card_auth_ref}disabled{/if}>Confirm check-out</button></form>
<p class="help-block">Questions about a charge? Dial 0 from your room or see us at reception.</p>
{/if}
</div>
{/block}
