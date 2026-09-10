{extends file='page.tpl'}
{block name='page_content'}
<div class="pulse-guest pulse-pay-page" style="max-width:620px;margin:0 auto">
{if isset($invalid)}
  <h2>Link not valid</h2><p>This payment link has expired or was cancelled. Please contact reception and we will send you a fresh one.</p>
{elseif isset($form_action)}
  <h2>Taking you to your bank…</h2><p>If nothing happens in a few seconds, press the button below.</p>
  <form method="post" action="{$form_action}" id="pp-redirect">{foreach $form_fields as $k => $v}<input type="hidden" name="{$k}" value="{$v|escape}">{/foreach}<button class="btn btn-primary btn-lg">Continue to payment</button></form>
  <script>document.getElementById('pp-redirect').submit();</script>
{else}
  <h2>{$l.title|default:$hotel}</h2>
  <p>{if $l.customer_name}{$l.customer_name} · {/if}Reference <b>{$l.short_code}</b></p>
  {if isset($error)}<div class="alert alert-danger">{$error}</div>{/if}

  {if isset($receipt) && $receipt}
    {if $receipt.state == 'captured' || $receipt.state == 'settled'}
      <div class="alert alert-success"><h3>Payment received — thank you.</h3>
      <p>{$currency}{$receipt.amount_captured|number_format:2} paid on {$receipt.captured_at}.<br>
      Receipt reference <b>{$receipt.reference}</b>{if $receipt.rrn} · RRN {$receipt.rrn}{/if}{if $receipt.card_last4} · card ending {$receipt.card_last4}{/if}.</p>
      <p>Please keep this reference. A copy has been sent to reception and applied to your bill.</p></div>
    {elseif $receipt.state == 'failed'}
      <div class="alert alert-danger">That payment did not go through{if $receipt.failed_reason} — {$receipt.failed_reason}{/if}. Nothing was taken from your account. You can try again below.</div>
    {else}
      <div class="alert alert-warning">We are still confirming this payment with the bank ({$receipt.state}). If money left your account it will be applied automatically — you do not need to pay twice. Reference <b>{$receipt.reference}</b>.</div>
    {/if}
  {/if}

  <table class="table"><tbody>
    <tr><td>Amount due</td><td class="text-right"><b>{$currency}{$outstanding|number_format:2}</b></td></tr>
    {if $l.amount_paid > 0}<tr><td>Already paid</td><td class="text-right">{$currency}{$l.amount_paid|number_format:2}</td></tr>{/if}
    {if $l.note}<tr><td colspan="2"><small>{$l.note}</small></td></tr>{/if}
  </tbody></table>

  {if $usable && $outstanding > 0.009}
    <form method="post">
      {if !$l.amount_locked}<div class="form-group"><label>Amount to pay</label><input name="amount" type="number" step="0.01" min="{$l.min_amount}" value="{$outstanding}" class="form-control"></div>{/if}
      <div class="form-group"><label>Pay with</label><select name="gateway" class="form-control">
      {foreach $gateways as $g}<option value="{$g.code}">{if $g.code == 'manual'}Bank transfer{else}{$g.name}{if $g.test_mode} (test){/if}{/if}</option>{/foreach}
      </select></div>
      <input type="hidden" name="method" value="card">
      <button name="submitPay" value="1" class="btn btn-primary btn-lg btn-block">Pay {$currency}{$outstanding|number_format:2}</button>
    </form>
  {elseif $l.status == 'paid'}
    <div class="alert alert-success">This bill is settled in full. Thank you.</div>
  {else}
    <div class="alert alert-warning">This link is {$l.status}. Please contact reception.</div>
  {/if}

  {if isset($instructions) && $instructions}
    <h4>Bank transfer</h4>
    <table class="table table-condensed"><tbody>
      <tr><td>Bank</td><td>{$instructions.bank}</td></tr>
      <tr><td>Account name</td><td>{$instructions.account_name}</td></tr>
      <tr><td>Account number</td><td><b>{$instructions.account_number}</b></td></tr>
      <tr><td>Amount</td><td>{$currency}{$instructions.amount|number_format:2}</td></tr>
      <tr><td>Narration</td><td><b>{$instructions.narration}</b></td></tr>
    </tbody></table>
    <p class="help-block">{$instructions.note}</p>
  {elseif $bank && $bank.account_number}
    <h4>Prefer a transfer?</h4>
    <p>{$bank.bank} · {$bank.account_name} · <b>{$bank.account_number}</b> — quote <b>{$l.short_code}</b> as the narration and show the receipt at reception.</p>
  {/if}

  <p class="help-block">Payments are processed by our bank's gateway. We never see or store your card number.</p>
{/if}
</div>
{/block}
