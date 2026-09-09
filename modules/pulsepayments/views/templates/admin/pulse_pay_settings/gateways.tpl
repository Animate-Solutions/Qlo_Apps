<div class="pulse-pay"><div class="panel"><h3><i class="icon-cogs"></i> Gateways</h3>
<p class="help-block">Secret keys are encrypted at rest and never shown again — leave a key field blank to keep what is already stored. Paste the webhook URL beside each gateway into its dashboard so payments land even when the guest closes the browser.</p>
{foreach $gateways as $g}
<div class="panel panel-default pp-gw"><div class="panel-heading"><b>{$g.name}</b> <code>{$g.code}</code>
{if $g.active}<span class="badge pp-on">active</span>{else}<span class="badge">off</span>{/if}
{if $g.test_mode}<span class="badge pp-test">test mode</span>{/if}
{if $g.last_error}<span class="text-danger pull-right">last error: {$g.last_error}</span>{elseif $g.last_ok_at}<span class="text-success pull-right">last reached {$g.last_ok_at}</span>{/if}</div>
<div class="panel-body"><form method="post" class="form-horizontal"><input type="hidden" name="code" value="{$g.code}">
<div class="row"><div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Display name</label><div class="col-sm-8"><input name="name" class="form-control input-sm" value="{$g.name|escape}"></div></div>
<div class="form-group"><label class="col-sm-4">API endpoint</label><div class="col-sm-8"><input name="endpoint" class="form-control input-sm" value="{$g.endpoint|escape}" placeholder="leave blank for the vendor default"></div></div>
<div class="form-group"><label class="col-sm-4">Public key</label><div class="col-sm-8"><input name="public_key" class="form-control input-sm" value="{$g.public_key|escape}"></div></div>
<div class="form-group"><label class="col-sm-4">Secret key</label><div class="col-sm-8"><input name="secret_key" class="form-control input-sm" placeholder="{if $g.secret_masked}stored: {$g.secret_masked}{else}not set{/if}" autocomplete="off"></div></div>
<div class="form-group"><label class="col-sm-4">Webhook secret</label><div class="col-sm-8"><input name="webhook_secret" class="form-control input-sm" placeholder="{if $g.webhook_masked}stored: {$g.webhook_masked}{else}not set{/if}" autocomplete="off"></div></div>
<div class="form-group"><label class="col-sm-4">Merchant / product id</label><div class="col-sm-8"><input name="merchant_id" class="form-control input-sm" value="{$g.merchant_id|escape}"></div></div>
<div class="form-group"><label class="col-sm-4">Channels</label><div class="col-sm-8"><input name="channels" class="form-control input-sm" value="{$g.channels|escape}"><span class="help-block">web, desk, pos, portal, link, terminal</span></div></div>
<div class="form-group"><label class="col-sm-4">State</label><div class="col-sm-8"><label><input type="checkbox" name="active" value="1" {if $g.active}checked{/if}> Active</label> &nbsp; <label><input type="checkbox" name="test_mode" value="1" {if $g.test_mode}checked{/if}> Test mode</label></div></div>
</div>
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Fee %</label><div class="col-sm-3"><input name="fee_percent" class="form-control input-sm" value="{$g.fee_percent}"></div><label class="col-sm-2">Flat</label><div class="col-sm-3"><input name="fee_flat" class="form-control input-sm" value="{$g.fee_flat}"></div></div>
<div class="form-group"><label class="col-sm-4">Fee cap</label><div class="col-sm-3"><input name="fee_cap" class="form-control input-sm" value="{$g.fee_cap}"></div><label class="col-sm-2">Flat waived below</label><div class="col-sm-3"><input name="fee_flat_waive_below" class="form-control input-sm" value="{$g.fee_flat_waive_below}"></div></div>
<div class="form-group"><label class="col-sm-4">Capabilities</label><div class="col-sm-8">{foreach $g.caps as $c}<span class="label label-default">{$c}</span> {/foreach}</div></div>
<div class="form-group"><label class="col-sm-4">Webhook URL</label><div class="col-sm-8"><input class="form-control input-sm" value="{$g.webhook_url}" readonly onclick="this.select()"></div></div>
{if $g.code=='interswitch'}
<div class="form-group"><label class="col-sm-4">Pay item id</label><div class="col-sm-8"><input name="x_pay_item_id" class="form-control input-sm" value="{$g.extra_decoded.pay_item_id|default:'101'}"></div></div>
<div class="form-group"><label class="col-sm-4">Merchant code</label><div class="col-sm-8"><input name="x_merchant_code" class="form-control input-sm" value="{$g.extra_decoded.merchant_code|default:''}"></div></div>
<div class="form-group"><label class="col-sm-4">Transaction query URL</label><div class="col-sm-8"><input name="x_query_url" class="form-control input-sm" value="{$g.extra_decoded.query_url|default:'https://webpay.interswitchng.com/collections/api/v1/gettransaction.json'}"></div></div>
{/if}
{if $g.code=='manual'}
<div class="form-group"><label class="col-sm-4">Bank</label><div class="col-sm-8"><input name="x_bank_name" class="form-control input-sm" value="{$g.extra_decoded.bank_name|default:''}"></div></div>
<div class="form-group"><label class="col-sm-4">Account name</label><div class="col-sm-8"><input name="x_account_name" class="form-control input-sm" value="{$g.extra_decoded.account_name|default:''}"></div></div>
<div class="form-group"><label class="col-sm-4">Account number</label><div class="col-sm-8"><input name="x_account_number" class="form-control input-sm" value="{$g.extra_decoded.account_number|default:''}"></div></div>
{/if}
{if $g.code=='flutterwave'}<div class="form-group"><label class="col-sm-4">Checkout logo URL</label><div class="col-sm-8"><input name="x_logo_url" class="form-control input-sm" value="{$g.extra_decoded.logo_url|default:''}"></div></div>{/if}
</div></div>
<button name="saveGateway" class="btn btn-primary btn-sm">Save {$g.name}</button> <button name="testGateway" class="btn btn-default btn-sm">Test connection</button>
</form></div></div>
{/foreach}

<h3><i class="icon-print"></i> Bank POS terminals</h3>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Label</th><th>Bank</th><th>TID</th><th>Station</th><th>Mode</th><th>Active</th><th>Last seen</th><th></th></tr></thead><tbody>
{foreach $terminals as $t}<tr><form method="post"><input type="hidden" name="id_terminal" value="{$t.id_pulse_pay_terminal}">
<td><input name="tcode" class="input-sm" style="width:100px" value="{$t.code|escape}"></td><td><input name="tlabel" class="input-sm" value="{$t.label|escape}"></td><td><input name="tbank" class="input-sm" style="width:110px" value="{$t.bank|escape}"></td>
<td><input name="ttid" class="input-sm" style="width:100px" value="{$t.terminal_id|escape}"><input type="hidden" name="tmid" value="{$t.merchant_id|escape}"></td>
<td><input name="tstation" class="input-sm" style="width:100px" value="{$t.station|escape}"></td>
<td><select name="tmode" class="input-sm"><option value="manual" {if $t.mode=='manual'}selected{/if}>manual</option><option value="claim" {if $t.mode=='claim'}selected{/if}>claim</option></select></td>
<td><input type="checkbox" name="tactive" value="1" {if $t.active}checked{/if}></td><td><small>{$t.last_seen|default:'—'}</small></td>
<td><button name="saveTerminal" class="btn btn-xs btn-default">Save</button></td></form></tr>{/foreach}
<tr><form method="post"><td><input name="tcode" class="input-sm" style="width:100px" placeholder="BAR"></td><td><input name="tlabel" class="input-sm" placeholder="Bar terminal"></td><td><input name="tbank" class="input-sm" style="width:110px" placeholder="OPay"></td><td><input name="ttid" class="input-sm" style="width:100px" placeholder="TID"></td><td><input name="tstation" class="input-sm" style="width:100px" placeholder="bar"></td>
<td><select name="tmode" class="input-sm"><option value="manual">manual</option><option value="claim">claim</option></select></td><td><input type="checkbox" name="tactive" value="1" checked></td><td></td><td><button name="saveTerminal" class="btn btn-xs btn-primary">Add</button></td></form></tr>
</tbody></table>
<p class="help-block"><b>manual</b> — the cashier swipes on the bank terminal and keys the RRN. <b>claim</b> — a terminal app polls <code>{$api_url}/terminal_claim</code> and posts the result back to <code>{$api_url}/terminal_result</code>.</p>

<h3><i class="icon-link"></i> URLs &amp; cron</h3>
<table class="table table-condensed">
<tr><th style="width:220px">Pay page</th><td><code>{$pay_url}?t=&lt;token&gt;</code> or <code>{$pay_url}?c=&lt;short code&gt;</code></td></tr>
<tr><th>JSON API</th><td><code>{$api_url}/&lt;resource&gt;</code> — Bearer token from Pulse Core, scopes <code>pos</code>, <code>portal</code>, <code>desk</code></td></tr>
<tr><th>Sweep cron</th><td><code>php modules/pulsepayments/cron/sweep.php {$cron_token}</code> — every 10 minutes. Verifies pending payments, expires stale links, holds and terminal requests, retries queued captures.</td></tr>
</table>
<form method="post" class="noprint"><button name="newCronToken" class="btn btn-default btn-xs" onclick="return confirm('Regenerate the cron token? Existing cron entries will stop working until updated.')">Regenerate cron token</button></form>
</div></div>
