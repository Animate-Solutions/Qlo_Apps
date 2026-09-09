<div class="pulse-acc"><div class="panel"><h3><i class="icon-cogs"></i> Posting rules &amp; settings</h3>
{if $unmapped}<div class="alert alert-warning"><strong>{$unmapped|count} items have no posting rule.</strong> Anything the hotel books against them will fail to post and sit in the queue until a rule exists.</div>{/if}
<ul class="nav nav-tabs">
<li class="active"><a data-toggle="tab" href="#s-rules">Posting rules</a></li>
<li><a data-toggle="tab" href="#s-unmapped" {if $unmapped}class="text-danger"{/if}>Unmapped ({$unmapped|count})</a></li>
<li><a data-toggle="tab" href="#s-periods">Periods &amp; year end</a></li>
<li><a data-toggle="tab" href="#s-tax">Tax &amp; hotel details</a></li>
<li><a data-toggle="tab" href="#s-einv">FIRS e-invoicing</a></li>
<li><a data-toggle="tab" href="#s-posting">Posting &amp; cron</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="s-rules">
<form method="post">
{foreach $map_types as $type => $label}
<h4>{$label}</h4>
<table class="table table-condensed"><thead><tr><th style="width:130px">Key</th><th>Description</th><th>Account</th><th>Tax account</th><th>Stock / contra</th><th>Cost centre</th><th class="num">WHT %</th><th></th></tr></thead><tbody>
{foreach $rules as $m}{if $m.map_type == $type}
<tr class="{if !$m.account_code && $type != 'department'}warning{/if}">
<td><strong>{$m.key_value}</strong></td><td>{$m.label|escape}{if $m.note}<br><small class="muted">{$m.note|escape}</small>{/if}</td>
<td><select name="rule_account[{$m.id_pulse_acc_map}]" class="form-control input-sm"><option value="">—</option>
{foreach $accounts as $a}<option value="{$a.code}" {if $m.account_code == $a.code}selected{/if}>{$a.code} {$a.name|escape|truncate:34}</option>{/foreach}</select></td>
<td>{if $type == 'charge_code' || $type == 'expense_category'}<select name="rule_tax[{$m.id_pulse_acc_map}]" class="form-control input-sm"><option value="">—</option>
{foreach $accounts as $a}{if $a.subtype == 'tax'}<option value="{$a.code}" {if $m.tax_account_code == $a.code}selected{/if}>{$a.code} {$a.name|escape|truncate:24}</option>{/if}{/foreach}</select>{/if}</td>
<td>{if $type == 'inv_category'}<select name="rule_contra[{$m.id_pulse_acc_map}]" class="form-control input-sm"><option value="">—</option>
{foreach $accounts as $a}{if $a.subtype == 'inventory'}<option value="{$a.code}" {if $m.contra_account_code == $a.code}selected{/if}>{$a.code} {$a.name|escape|truncate:24}</option>{/if}{/foreach}</select>{/if}</td>
<td><input name="rule_cc[{$m.id_pulse_acc_map}]" class="form-control input-sm" value="{$m.cost_centre|escape}" style="width:110px"></td>
<td class="num">{if $m.wht_rate_pct > 0}{$m.wht_rate_pct}{/if}</td>
<td><a class="btn btn-xs btn-link" href="{$self_url}&amp;deleteRule=1&amp;id_map={$m.id_pulse_acc_map}" onclick="return confirm('Remove this rule?')">✕</a></td></tr>
{/if}{/foreach}
</tbody></table>
{/foreach}
<button name="saveRules" class="btn btn-primary">Save every rule above</button>
</form>
<h4>Add a rule</h4>
<form method="post" class="form-inline">
<select name="map_type" class="form-control">{foreach $map_types as $t => $l}<option value="{$t}">{$t}</option>{/foreach}</select>
<input name="key_value" class="form-control" placeholder="key (e.g. a charge code)" required>
<input name="label" class="form-control" placeholder="description">
<select name="account_code" class="form-control"><option value="">account…</option>{foreach $accounts as $a}<option value="{$a.code}">{$a.code} {$a.name|escape|truncate:30}</option>{/foreach}</select>
<select name="tax_account_code" class="form-control"><option value="">tax account…</option>{foreach $accounts as $a}{if $a.subtype == 'tax'}<option value="{$a.code}">{$a.code}</option>{/if}{/foreach}</select>
<select name="contra_account_code" class="form-control"><option value="">contra…</option>{foreach $accounts as $a}{if $a.subtype == 'inventory' || $a.subtype == 'accum_depreciation'}<option value="{$a.code}">{$a.code}</option>{/if}{/foreach}</select>
<input name="cost_centre" class="form-control" placeholder="cost centre" style="width:110px">
<input name="wht_rate_pct" type="number" step="0.1" class="form-control" placeholder="WHT %" style="width:90px">
<button name="saveRule" class="btn btn-default">Add</button>
</form>
</div>

<div class="tab-pane" id="s-unmapped">
{if !$unmapped}<p class="alert alert-success">Every charge code, expense category, stock category and POS major group has a rule. Nothing will fall through.</p>
{else}<table class="table table-condensed"><thead><tr><th>Kind</th><th>Key</th><th>What it is</th><th>What it needs</th></tr></thead><tbody>
{foreach $unmapped as $u}<tr class="warning"><td>{$u.kind}</td><td><strong>{$u.key|escape}</strong></td><td>{$u.label|escape}</td><td class="muted">{$u.hint}</td></tr>{/foreach}
</tbody></table>{/if}
<p class="help-block">Queue: {$counts.pending} pending, {$counts.failed} failed, {$counts.skipped} skipped, {$counts.posted} posted.</p>
</div>

<div class="tab-pane" id="s-periods">
<form method="post" class="form-inline noprint" style="margin-bottom:10px">
Create periods from <input type="date" name="period_from" class="form-control" value="{$smarty.now|date_format:'%Y-%m-01'}">
for <input name="months" type="number" class="form-control" value="12" style="width:70px"> months
<button name="makePeriods" class="btn btn-default">Create</button></form>
<table class="table table-condensed" style="max-width:800px"><thead><tr><th>Period</th><th>From</th><th>To</th><th>Status</th><th>Closed</th><th>Closing journal</th><th></th></tr></thead><tbody>
{foreach $periods as $p}<tr class="{if $p.status == 'locked'}muted{elseif $p.status == 'closed'}info{/if}">
<td><strong>{$p.code}</strong></td><td>{$p.date_from}</td><td>{$p.date_to}</td><td>{$p.status}</td><td class="muted">{$p.date_closed}</td><td>{if $p.closing_journal}#{$p.closing_journal}{/if}</td>
<td><form method="post" class="form-inline"><input type="hidden" name="period_code" value="{$p.code}">
{if $p.status == 'open'}<label><input type="checkbox" name="with_closing" value="1" checked> closing entry</label>
<button name="closePeriod" class="btn btn-xs btn-warning" onclick="return confirm('Close {$p.code}? Nothing can post into it afterwards.')">Close</button>
{elseif $p.status == 'closed'}<button name="reopenPeriod" class="btn btn-xs btn-default" onclick="return confirm('Reopen {$p.code} and reverse its closing entry?')">Reopen</button>
{else}<span class="muted">locked by year end</span>{/if}</form></td></tr>{/foreach}
</tbody></table>
<h4>Year end</h4>
<form method="post" class="form-inline"><input name="year_s" type="number" class="form-control" value="{$year}" style="width:110px">
<button name="yearEnd" class="btn btn-danger" onclick="return confirm('Close every month of this year, roll the result to retained earnings and lock the twelve periods?')">Run year end</button>
<span class="help-block" style="display:inline-block;margin-left:10px">Closes each open month with its closing entry, moves 3500 to 3400 and locks the year. A locked period can never be reopened.</span></form>
</div>

<div class="tab-pane" id="s-tax">
<form method="post" class="form-horizontal" style="max-width:760px">
<h4>Nigerian tax defaults</h4>
<div class="form-group"><label class="col-sm-5 control-label">VAT rate %</label><div class="col-sm-3"><input name="PULSE_ACC_VAT_PCT" type="number" step="0.1" class="form-control" value="{$cfg.PULSE_ACC_VAT_PCT}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Consumption tax on F&amp;B %</label><div class="col-sm-3"><input name="PULSE_ACC_CONSUMPTION_PCT" type="number" step="0.1" class="form-control" value="{$cfg.PULSE_ACC_CONSUMPTION_PCT}"></div>
<div class="col-sm-4"><span class="help-block">Rivers State hospitality levy — a 12.5% F&amp;B charge splits 7.5 VAT / 5 consumption.</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">WHT services / rent %</label><div class="col-sm-3"><input name="PULSE_ACC_WHT_SERVICES_PCT" type="number" step="0.1" class="form-control" value="{$cfg.PULSE_ACC_WHT_SERVICES_PCT}"></div>
<div class="col-sm-3"><input name="PULSE_ACC_WHT_RENT_PCT" type="number" step="0.1" class="form-control" value="{$cfg.PULSE_ACC_WHT_RENT_PCT}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Withhold only above</label><div class="col-sm-3"><input name="PULSE_ACC_WHT_THRESHOLD" type="number" step="0.01" class="form-control num" value="{$cfg.PULSE_ACC_WHT_THRESHOLD}"></div></div>
<h4>Hotel details on invoices and certificates</h4>
<div class="form-group"><label class="col-sm-5 control-label">Registered name</label><div class="col-sm-7"><input name="PULSE_ACC_HOTEL_NAME" class="form-control" value="{$cfg.PULSE_ACC_HOTEL_NAME|escape}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">TIN</label><div class="col-sm-7"><input name="PULSE_ACC_HOTEL_TIN" class="form-control" value="{$cfg.PULSE_ACC_HOTEL_TIN|escape}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Address</label><div class="col-sm-7"><input name="PULSE_ACC_HOTEL_ADDRESS" class="form-control" value="{$cfg.PULSE_ACC_HOTEL_ADDRESS|escape}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Accounts e-mail</label><div class="col-sm-7"><input name="PULSE_ACC_HOTEL_EMAIL" class="form-control" value="{$cfg.PULSE_ACC_HOTEL_EMAIL|escape}"></div></div>
<h4>Credit control</h4>
<div class="form-group"><label class="col-sm-5 control-label">AR / AP terms (days)</label><div class="col-sm-3"><input name="PULSE_ACC_AR_TERMS_DAYS" type="number" class="form-control" value="{$cfg.PULSE_ACC_AR_TERMS_DAYS}"></div>
<div class="col-sm-3"><input name="PULSE_ACC_AP_TERMS_DAYS" type="number" class="form-control" value="{$cfg.PULSE_ACC_AP_TERMS_DAYS}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Dunning at days overdue</label><div class="col-sm-4"><input name="PULSE_ACC_DUNNING_DAYS" class="form-control" value="{$cfg.PULSE_ACC_DUNNING_DAYS}" placeholder="7,21,45"></div>
<div class="col-sm-3"><input name="PULSE_ACC_STOP_LIST_DAYS" type="number" class="form-control" value="{$cfg.PULSE_ACC_STOP_LIST_DAYS}" title="stop list after this many days"></div></div>
<div class="form-group"><div class="col-sm-offset-5 col-sm-7"><button name="saveSettings" class="btn btn-primary">Save settings</button></div></div>
</form>
</div>

<div class="tab-pane" id="s-einv">
<form method="post" class="form-horizontal" style="max-width:800px">
<p class="help-block">Pulse builds and queues a UBL/BIS Billing 3.0 style payload for every issued invoice. Transmission is off until an endpoint and credentials are entered here — until then the queue is a validated dry run, so no invoice is lost while the property waits for its FIRS/NRS onboarding. See the module README for exactly what a live MBS or NRS connection needs.</p>
<div class="form-group"><label class="col-sm-4 control-label">Enabled</label><div class="col-sm-8"><select name="PULSE_ACC_EINV_ENABLED" class="form-control"><option value="0" {if !$cfg.PULSE_ACC_EINV_ENABLED}selected{/if}>Off — build and queue only</option><option value="1" {if $cfg.PULSE_ACC_EINV_ENABLED}selected{/if}>On — transmit</option></select></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Endpoint base URL</label><div class="col-sm-8"><input name="PULSE_ACC_EINV_ENDPOINT" class="form-control" value="{$cfg.PULSE_ACC_EINV_ENDPOINT|escape}" placeholder="https://…"><span class="help-block">Documents are POSTed to <code>&lt;base&gt;/api/v1/invoice/signing</code>.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Business ID</label><div class="col-sm-8"><input name="PULSE_ACC_EINV_BUSINESS_ID" class="form-control" value="{$cfg.PULSE_ACC_EINV_BUSINESS_ID|escape}"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Service ID</label><div class="col-sm-8"><input name="PULSE_ACC_EINV_SERVICE_ID" class="form-control" value="{$cfg.PULSE_ACC_EINV_SERVICE_ID|escape}"><span class="help-block">The IRN is built as <code>&lt;invoice number&gt;-&lt;service id&gt;-&lt;YYYYMMDD&gt;</code>.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">API key</label><div class="col-sm-8"><input name="PULSE_ACC_EINV_KEY" class="form-control" value="{if $einv_key_set}••••••••••••{/if}" placeholder="sent as x-api-key"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">API secret</label><div class="col-sm-8"><input name="PULSE_ACC_EINV_SECRET" class="form-control" value="{if $einv_secret_set}••••••••••••{/if}" placeholder="sent as x-api-secret, encrypted at rest"></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Timeout (seconds)</label><div class="col-sm-3"><input name="PULSE_ACC_EINV_TIMEOUT" type="number" class="form-control" value="{$cfg.PULSE_ACC_EINV_TIMEOUT}"></div>
<div class="col-sm-5"><span class="help-block">Short, because the link in Port Harcourt is not always there. Failures back off and retry.</span></div></div>
<div class="form-group"><label class="col-sm-4 control-label">Only invoices above</label><div class="col-sm-3"><input name="PULSE_ACC_EINV_MIN_TOTAL" type="number" step="0.01" class="form-control num" value="{$cfg.PULSE_ACC_EINV_MIN_TOTAL}"></div></div>
<div class="form-group"><div class="col-sm-offset-4 col-sm-8"><button name="saveSettings" class="btn btn-primary">Save settings</button></div></div>
</form>
</div>

<div class="tab-pane" id="s-posting">
<form method="post" class="form-horizontal" style="max-width:760px">
<div class="form-group"><label class="col-sm-5 control-label">When to post</label><div class="col-sm-7"><select name="PULSE_ACC_POST_MODE" class="form-control">
<option value="audit" {if $cfg.PULSE_ACC_POST_MODE == 'audit'}selected{/if}>At night audit (recommended)</option>
<option value="manual" {if $cfg.PULSE_ACC_POST_MODE == 'manual'}selected{/if}>Only when the accountant presses Post now</option></select>
<span class="help-block">Operational screens never wait for the ledger — folio and POS events only queue.</span></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Documents per batch</label><div class="col-sm-3"><input name="PULSE_ACC_QUEUE_BATCH" type="number" class="form-control" value="{$cfg.PULSE_ACC_QUEUE_BATCH}"></div>
<div class="col-sm-4"><input name="PULSE_ACC_RETRY_MAX" type="number" class="form-control" value="{$cfg.PULSE_ACC_RETRY_MAX}" title="retries before a document is marked failed"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Bank match window (days)</label><div class="col-sm-3"><input name="PULSE_ACC_BANK_MATCH_DAYS" type="number" class="form-control" value="{$cfg.PULSE_ACC_BANK_MATCH_DAYS}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Default bank account</label><div class="col-sm-7"><select name="PULSE_ACC_DEFAULT_BANK" class="form-control">{foreach $accounts as $a}{if $a.subtype == 'cash'}<option value="{$a.code}" {if $cfg.PULSE_ACC_DEFAULT_BANK == $a.code}selected{/if}>{$a.code} {$a.name|escape}</option>{/if}{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Retained earnings / P&amp;L clearing</label><div class="col-sm-3"><input name="PULSE_ACC_RETAINED_EARNINGS" class="form-control" value="{$cfg.PULSE_ACC_RETAINED_EARNINGS}"></div>
<div class="col-sm-3"><input name="PULSE_ACC_PL_CLEARING" class="form-control" value="{$cfg.PULSE_ACC_PL_CLEARING}"></div></div>
<div class="form-group"><label class="col-sm-5 control-label">Reporting currency</label><div class="col-sm-3"><input name="PULSE_ACC_CURRENCY" class="form-control" value="{$cfg.PULSE_ACC_CURRENCY}" maxlength="3"></div></div>
<div class="form-group"><div class="col-sm-offset-5 col-sm-7"><button name="saveSettings" class="btn btn-primary">Save settings</button></div></div>
</form>
<h4>Cron</h4>
<pre>*/15 * * * *  {$cron_post}
15 3 1-5 * *  {$cron_dep}</pre>
<p class="help-block">The first drains the posting and e-invoice queues and sweeps for anything the events missed. The second runs last month's depreciation; it is safe to fire every day of the window because a period already run is skipped.</p>
<form method="post" class="inline"><button name="newToken" class="btn btn-default btn-xs" onclick="return confirm('Generate a new cron token? Update your crontab afterwards.')">Generate a new token</button></form>
</div>

</div></div></div>
