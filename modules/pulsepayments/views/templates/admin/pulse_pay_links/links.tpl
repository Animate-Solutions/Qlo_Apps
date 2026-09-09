<div class="pulse-pay"><div class="panel"><h3><i class="icon-link"></i> Payment links</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#l-list">Links ({$rows|count})</a></li><li><a data-toggle="tab" href="#l-new">New link</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="l-list">
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulsePayLinks"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
<select name="status" class="form-control input-sm"><option value="">All</option>{foreach from=array('open','partly_paid','paid','expired','cancelled') item=s}<option value="{$s}" {if $status==$s}selected{/if}>{$s}</option>{/foreach}</select> <button class="btn btn-default btn-sm">Filter</button></form>
<table class="table table-condensed"><thead><tr><th>Code</th><th>Title</th><th>Guest</th><th>Purpose</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th>Uses</th><th>Expires</th><th>Status</th><th>Link</th><th></th></tr></thead><tbody>
{foreach $rows as $l}<tr class="{if $l.status=='paid'}success{elseif $l.status=='expired' || $l.status=='cancelled'}danger{elseif $l.status=='partly_paid'}warning{/if}">
<td><b>{$l.short_code}</b></td><td>{$l.title}</td><td>{$l.customer_name}<br><small class="text-muted">{$l.customer_email}</small></td><td>{$l.purpose}</td>
<td class="text-right">{displayPrice price=$l.amount}</td><td class="text-right">{displayPrice price=$l.amount_paid}</td><td>{$l.uses}/{$l.max_uses}</td><td>{$l.expires_at|date_format:"%d/%m %H:%M"}</td><td>{$l.status}</td>
<td><input class="form-control input-sm pp-copy" value="{$l.url}" readonly onclick="this.select()" style="width:260px"></td>
<td class="noprint"><form method="post" class="form-inline"><input type="hidden" name="id_link" value="{$l.id_pulse_pay_link}"><input type="hidden" name="token_ref" value="{$l.token}">
{if $comms && $l.customer_email}<button name="sendLink" class="btn btn-xs btn-default">Email</button>{/if}
{if $l.status=='open' || $l.status=='partly_paid'}<button name="cancelLink" class="btn btn-xs btn-link" onclick="return confirm('Cancel this link?')">✕</button>{/if}</form></td></tr>
{foreachelse}<tr><td colspan="11"><em>No payment links yet</em></td></tr>{/foreach}</tbody></table>
</div>

<div class="tab-pane" id="l-new"><form method="post" class="form-horizontal"><div class="row">
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Purpose</label><div class="col-sm-8"><select name="purpose" class="form-control"><option value="deposit">Pre-arrival deposit</option><option value="folio">Guest folio balance</option><option value="invoice">City-ledger invoice</option><option value="pos">Restaurant check</option><option value="other">Other</option></select></div></div>
<div class="form-group"><label class="col-sm-4">In-house folio</label><div class="col-sm-8"><select name="id_pulse_folio" class="form-control" id="pp-folio"><option value="">— none —</option>{foreach $inhouse as $f}<option value="{$f.id_pulse_folio}" data-booking="{$f.id_htl_booking}" data-balance="{$f.balance}" data-guest="{$f.guest}">{$f.room_num} · {$f.guest} · {$f.folio_no} ({$f.balance})</option>{/foreach}</select><input type="hidden" name="id_htl_booking" id="pp-booking"></div></div>
<div class="form-group"><label class="col-sm-4">Amount</label><div class="col-sm-8"><input name="amount" id="pp-amount" type="number" step="0.01" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-4">Title</label><div class="col-sm-8"><input name="title" class="form-control" placeholder="Deposit for your stay"></div></div>
<div class="form-group"><label class="col-sm-4">Note</label><div class="col-sm-8"><input name="note" class="form-control"></div></div>
</div>
<div class="col-md-6">
<div class="form-group"><label class="col-sm-4">Guest name</label><div class="col-sm-8"><input name="customer_name" id="pp-guest" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">E-mail</label><div class="col-sm-8"><input name="customer_email" type="email" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-4">Phone</label><div class="col-sm-8"><input name="customer_phone" class="form-control" placeholder="+234…"></div></div>
<div class="form-group"><label class="col-sm-4">Gateway</label><div class="col-sm-8"><select name="gateway" class="form-control"><option value="">Guest chooses</option>{foreach $gateways as $g}<option value="{$g.code}">{$g.name}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Valid for (hours)</label><div class="col-sm-8"><input name="expires_hours" type="number" class="form-control" value="{$default_hours}"></div></div>
<div class="form-group"><label class="col-sm-4">Uses</label><div class="col-sm-8"><input name="max_uses" type="number" min="1" class="form-control" value="1"><label><input type="checkbox" name="amount_locked" value="1" checked> Lock the amount</label> {if $comms}<label><input type="checkbox" name="send_now" value="1"> E-mail it now</label>{/if}</div></div>
</div></div>
<button name="createLink" class="btn btn-primary btn-lg">Create link</button>
</form></div>

</div></div></div>
