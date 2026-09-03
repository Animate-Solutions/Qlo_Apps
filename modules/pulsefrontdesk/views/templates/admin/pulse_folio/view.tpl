<div class="pulse-fd">
<div class="panel">
  <h3><i class="icon-file-text"></i> Folio {$folio->folio_no} <span class="label label-{if $folio->status=='open'}success{else}default{/if}">{$folio->status}</span>
    <span class="pull-right noprint"><a class="btn btn-default btn-sm" href="javascript:window.print()"><i class="icon-print"></i> Print / PDF</a> <form method="post" class="inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><button name="emailFolio" class="btn btn-default btn-sm"><i class="icon-envelope"></i> Email to guest</button></form></span></h3>
  {if $regcard}<p class="noprint"><span class="label label-success">Registration card signed</span> {$regcard.date_add} via {$regcard.channel} (terms v{$regcard.terms_version}) <a href="#" onclick="$('#regsig').toggle();return false">view signature</a><br><img id="regsig" src="{$regcard.signature}" style="display:none;max-height:80px;border:1px solid #ccc"></p>{/if}
  <div class="row">
    <div class="col-md-6"><strong>{$hotel}</strong><br>
      {if $booking}Guest: <strong>{$booking.guest}</strong> &middot; Room {$booking.room_num} ({$booking.room_type_name})<br>Stay {$booking.date_from} → {$booking.date_to} &middot; {$booking.nights} night(s) &middot; Order {$booking.order_ref}{if $booking.company_name}<br>Company: {$booking.company_name}{/if}{else}Type: {$folio->type}{/if}</div>
    <div class="col-md-6 text-right"><table class="table table-condensed pull-right" style="width:auto"><tr><td>Charges</td><td>{displayPrice price=$folio->total_charges}</td></tr><tr><td>Payments</td><td>{displayPrice price=$folio->total_payments}</td></tr><tr class="{if $folio->balance>0}danger{/if}"><td><strong>Balance</strong></td><td><strong>{displayPrice price=$folio->balance}</strong></td></tr></table></div>
  </div>
  <table class="table table-striped folio-lines"><thead><tr><th>Date</th><th>Biz date</th><th>Dept</th><th>Description</th><th>Qty</th><th>Unit (excl)</th><th>Tax %</th><th class="text-right">Charge</th><th class="text-right">Payment</th><th>By</th><th class="noprint"></th></tr></thead><tbody>
  {foreach $lines as $l}<tr class="{if $l.voided}voided{/if}"><td>{$l.date_add|date_format:"%d/%m %H:%M"}</td><td>{$l.business_date}</td><td>{$l.department}</td><td>{$l.description}{if $l.voided}<br><small>VOID: {$l.void_reason}</small>{/if}{if $l.payment_method} <small>({$l.payment_method})</small>{/if}</td><td>{$l.qty}</td><td>{$l.unit_price_tax_excl}</td><td>{$l.tax_rate}</td>
    <td class="text-right">{if !$l.is_payment}{displayPrice price=$l.amount_tax_incl}{/if}</td><td class="text-right">{if $l.is_payment}{displayPrice price=$l.amount_tax_incl}{/if}</td><td>{$l.firstname|substr:0:1}. {$l.lastname}</td>
    <td class="noprint">{if !$l.voided && $folio->status=='open'}
      <form method="post" class="inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><input type="hidden" name="id_line" value="{$l.id_pulse_folio_line}"><input name="reason" placeholder="void reason" class="input-xs" size="10"><button name="voidLine" class="btn btn-xs btn-danger" onclick="return confirm('Void this line?')">Void</button></form>
      <form method="post" class="inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><input type="hidden" name="id_line" value="{$l.id_pulse_folio_line}"><select name="to_folio" class="input-xs">{foreach $open_folios as $o}<option value="{$o.id_pulse_folio}">{$o.folio_no} ({$o.type})</option>{/foreach}</select><button name="transferLine" class="btn btn-xs btn-default">Transfer</button></form>{/if}</td></tr>{/foreach}
  </tbody></table>
</div>
{if $folio->status=='open'}
<div class="row noprint">
  <div class="col-md-6"><div class="panel"><h3>Post charge</h3>
    <form method="post" class="form-horizontal"><input type="hidden" name="id_pulse_folio" value="{$folio->id}">
      <div class="form-group"><label class="col-sm-3">Code</label><div class="col-sm-9"><select name="code" class="form-control" id="cc-select">{foreach $charge_codes as $c}<option value="{$c.code}" data-price="{$c.default_price}" data-tax="{$c.tax_rate}" data-name="{$c.name}">{$c.code} — {$c.name} ({$c.department})</option>{/foreach}</select></div></div>
      <div class="form-group"><label class="col-sm-3">Description</label><div class="col-sm-9"><input name="description" id="cc-desc" class="form-control" required></div></div>
      <div class="form-group"><label class="col-sm-3">Qty × Unit (excl)</label><div class="col-sm-4"><input name="qty" class="form-control" value="1"></div><div class="col-sm-5"><input name="unit_price" id="cc-price" class="form-control" type="number" step="0.01" required></div></div>
      <div class="form-group"><label class="col-sm-3">Tax %</label><div class="col-sm-9"><input name="tax_rate" id="cc-tax" class="form-control" placeholder="default from code"></div></div>
      <button name="postLine" class="btn btn-default pull-right">Post charge</button></form></div></div>
  <div class="col-md-6"><div class="panel"><h3>Take payment</h3>
    <form method="post" class="form-horizontal"><input type="hidden" name="id_pulse_folio" value="{$folio->id}">
      <div class="form-group"><label class="col-sm-3">Method</label><div class="col-sm-9"><select name="code" class="form-control" id="pm-select">{foreach $payment_codes as $c}{if $c.code!='CL' && $c.code!='DEP'}<option value="{$c.code}">{$c.name}</option>{/if}{/foreach}</select></div></div>
      <div class="form-group"><label class="col-sm-3">Reference</label><div class="col-sm-9"><input name="description" class="form-control" placeholder="Receipt / transfer ref" required></div></div>
      <div class="form-group"><label class="col-sm-3">Amount</label><div class="col-sm-9"><input name="unit_price" class="form-control" type="number" step="0.01" value="{$folio->balance|max:0}" required></div></div>
      <input type="hidden" name="qty" value="1"><input type="hidden" name="tax_rate" value="0"><input type="hidden" name="payment_method" id="pm-method" value="cash">
      <button name="postLine" class="btn btn-success pull-right">Record payment</button></form>
    <hr>
    <form method="post" class="form-inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}">
      <select name="id_company" class="form-control">{foreach $companies as $c}<option value="{$c.id_pulse_company}">{$c.name} (bal {$c.ledger_balance}{if $c.credit_limit>0} / lim {$c.credit_limit}{/if})</option>{/foreach}</select>
      <button name="settleCL" class="btn btn-default" {if $folio->balance<=0}disabled{/if}>Route balance to city ledger</button></form>
    <hr>
    <form method="post" class="form-inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><input type="hidden" name="code" value="CASH"><input name="amount_foreign" type="number" step="0.01" class="form-control" placeholder="Foreign amount" style="width:130px"> <select name="iso" class="form-control">{foreach $currencies as $cu}<option value="{$cu.iso_code}">{$cu.iso_code}</option>{/foreach}</select> <button name="postFx" class="btn btn-default">Post foreign-currency cash</button></form>
    <hr>
    <h4>Routing rules</h4>{foreach $rules as $r}<span class="label label-default">{$r.department} → {$r.target}{if $r.folio_no} ({$r.folio_no}){/if}</span> {/foreach}
    <form method="post" class="form-inline"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><select name="department" class="form-control"><option value="*">All</option><option value="rooms">Rooms</option><option value="fnb">F&amp;B</option><option value="minibar">Minibar</option><option value="laundry">Laundry</option><option value="telephone">Telephone</option><option value="spa">Spa</option><option value="misc">Misc</option></select> → <select name="target" class="form-control"><option value="company">Company folio</option><option value="master">Group master</option><option value="guest">Guest (this folio)</option></select> or folio <select name="to_folio" class="form-control"><option value="">—</option>{foreach $open_folios as $o}<option value="{$o.id_pulse_folio}">{$o.folio_no}</option>{/foreach}</select> <button name="addBookingRule" class="btn btn-default btn-sm">Add rule</button></form>
    <hr>
    <form method="post"><input type="hidden" name="id_pulse_folio" value="{$folio->id}"><button name="closeFolio" class="btn btn-primary" {if $folio->balance|abs > 0.009}disabled title="Balance must be zero"{/if}>Close folio</button></form>
  </div></div>
</div>
{/if}
</div>
<script>
$(function(){ $('#cc-select').change(function(){ var o=$(this).find(':selected'); $('#cc-desc').val(o.data('name')); $('#cc-price').val(o.data('price')); $('#cc-tax').attr('placeholder','default '+o.data('tax')+'%'); }).trigger('change');
  $('#pm-select').change(function(){ $('#pm-method').val($(this).val().toLowerCase()); }).trigger('change'); });
</script>
