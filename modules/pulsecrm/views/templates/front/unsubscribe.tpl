{extends file='page.tpl'}
{block name='page_content'}
<div class="pulse-guest pulse-crm-unsub" style="max-width:520px;margin:0 auto;padding:16px;font:16px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
{if !$r}
  <h2>That link is not valid</h2>
  <p>We could not match this link to a mailing. If you would like to stop hearing from us, reply to any of our emails with the word STOP and we will take you off the list by hand.</p>
{elseif $already || (isset($done) && $done)}
  <h2>You are unsubscribed</h2>
  <p>You will not receive another marketing message from {$hotel}. Anything to do with a booking you have made — a confirmation, a receipt, a reply from reception — still reaches you, because that is not marketing.</p>
  {if isset($reason) && $reason}<p style="color:#666">You told us: &ldquo;{$reason|escape:'html'}&rdquo;. Thank you — it is read.</p>{/if}
{else}
  <h2>Stop hearing from {$hotel}?</h2>
  <p>One click and we take you off the list. We would be grateful to know why, but it is not required.</p>
  <form method="post" action="{$action|escape:'html'}">
    <p><select name="reason" style="width:100%;padding:12px;border:1px solid #cfd6dd;border-radius:6px;font:inherit">
      <option value="">I would rather not say</option>
      <option value="too_often">Too many emails</option>
      <option value="not_relevant">Not relevant to me</option>
      <option value="never_signed_up">I never signed up</option>
      <option value="bad_stay">I was not happy with my stay</option>
      <option value="no_longer_travel">I no longer travel to Port Harcourt</option>
    </select></p>
    <button name="submitUnsub" value="1" style="width:100%;padding:16px;font-size:17px;font-weight:600;color:#fff;background:#c0392b;border:0;border-radius:8px;cursor:pointer">Unsubscribe me</button>
  </form>
{/if}
</div>
{/block}
