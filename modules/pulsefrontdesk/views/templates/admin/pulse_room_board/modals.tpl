{* Shared drawer + modals used by the board and the arrivals screen *}
<div id="room-drawer" class="panel" style="display:none">
  <h3><span id="rd-title"></span> <button class="btn btn-default btn-xs pull-right" id="rd-close">&times;</button></h3>
  <div class="row">
    <div class="col-md-5">
      <h4>Housekeeping status</h4>
      <div class="btn-group-vertical" id="rd-hk">{foreach $hk_statuses as $s}<button class="btn btn-default btn-sm hk-set" data-status="{$s}">{$s|replace:'_':' '|ucfirst}</button>{/foreach}</div>
      <div id="rd-ooo" style="display:none;margin-top:6px"><input class="form-control input-sm" id="rd-ooo-reason" placeholder="Reason"><input type="date" class="form-control input-sm" id="rd-ooo-until"></div>
      <h4>Add HK task</h4>
      <select id="rd-task-type" class="form-control input-sm"><option>clean</option><option>turndown</option><option>inspect</option><option>maintenance</option><option>minibar</option><option>linen</option><option>deep_clean</option></select>
      <select id="rd-task-who" class="form-control input-sm"><option value="">Unassigned</option>{foreach $attendants as $e}<option value="{$e.id_employee}">{$e.firstname} {$e.lastname}</option>{/foreach}</select>
      <input class="form-control input-sm" id="rd-task-note" placeholder="Note"><button class="btn btn-default btn-sm" id="rd-task-add">Add task</button>
    </div>
    <div class="col-md-7" id="rd-guest"></div>
  </div>
</div>

<div class="modal fade" id="ci-modal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h4>Check in <span id="ci-guest"></span></h4></div>
  <div class="modal-body form-horizontal">
    <input type="hidden" id="ci-booking">
    <div class="form-group"><label class="col-sm-4">Room</label><div class="col-sm-8"><select id="ci-room" class="form-control"></select></div></div>
    <div class="form-group"><label class="col-sm-4">ID type</label><div class="col-sm-8"><select id="ci-idtype" class="form-control"><option value="nin">NIN</option><option value="intl_passport">International passport</option><option value="drivers_licence">Driver's licence</option><option value="voters_card">Voter's card</option><option value="passport">Passport (foreign)</option><option value="other">Other</option></select></div></div>
    <div class="form-group"><label class="col-sm-4">ID number</label><div class="col-sm-8"><input id="ci-idnum" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Issuing country</label><div class="col-sm-8"><input id="ci-country" class="form-control" value="NGA" maxlength="3"></div></div>
    <div class="form-group"><label class="col-sm-4">ID expiry</label><div class="col-sm-8"><input id="ci-expiry" type="date" class="form-control"></div></div>
    <div class="form-group"><label class="col-sm-4">Deposit now</label><div class="col-sm-4"><input id="ci-deposit" class="form-control" type="number" step="0.01" value="0"></div><div class="col-sm-4"><select id="ci-deposit-method" class="form-control">{foreach $payment_codes as $p}<option value="{$p.code}">{$p.name}</option>{/foreach}</select></div></div>
    <div class="form-group"><label class="col-sm-4">Card hold (pre-auth)</label><div class="col-sm-8"><input id="ci-preauth" class="form-control" type="number" step="0.01" value="0"><small id="ci-preauth-note"></small></div></div>
    <div class="form-group"><label class="col-sm-4">Upsell offers</label><div class="col-sm-8" id="ci-upsells"><em>loading…</em></div></div>
    <div class="form-group"><label class="col-sm-4">Registration card</label><div class="col-sm-8"><div class="well well-sm" id="ci-terms" style="max-height:80px;overflow:auto;font-size:11px"></div><canvas class="sig-pad" id="ci-sig" data-target="#ci-sig-val"></canvas><input type="hidden" id="ci-sig-val"><button type="button" class="btn btn-default btn-xs sig-clear" data-pad="#ci-sig">Clear</button> <small>Guest signs on the tablet; leave blank if already signed online.</small></div></div>
    <div class="form-group"><div class="col-sm-8 col-sm-offset-4"><label><input type="checkbox" id="ci-early"> Early check-in</label> &nbsp; <label><input type="checkbox" id="ci-dirty"> Override dirty room</label> &nbsp; <label><input type="checkbox" id="ci-blacklist"> Manager override (blacklist)</label></div></div>
    <div id="ci-error" class="alert alert-danger" style="display:none"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-success" id="ci-submit">Check in</button></div>
</div></div></div>

<div class="modal fade" id="co-modal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h4>Check out <span id="co-guest"></span></h4></div>
  <div class="modal-body form-horizontal">
    <input type="hidden" id="co-booking">
    <div id="co-folio"></div>
    <div class="form-group"><label class="col-sm-4">Late checkout fee</label><div class="col-sm-8"><input id="co-late" class="form-control" type="number" step="0.01" value="0"></div></div>
    <div class="form-group"><label class="col-sm-4">Settle balance by</label><div class="col-sm-8"><select id="co-method" class="form-control"><option value="">— no balance —</option><option value="CAPTURE">Capture card on file</option><option value="FX">Foreign currency cash</option>{foreach $payment_codes as $p}<option value="{$p.code}">{$p.name}</option>{/foreach}</select></div></div>
    <div class="form-group" id="co-fx-row" style="display:none"><label class="col-sm-4">Foreign amount</label><div class="col-sm-4"><input id="co-fx-amount" class="form-control" type="number" step="0.01"></div><div class="col-sm-4"><select id="co-fx-iso" class="form-control">{if isset($currencies)}{foreach $currencies as $cu}<option value="{$cu.iso_code}">{$cu.iso_code}</option>{/foreach}{/if}</select></div></div>
    <div class="form-group" id="co-company-row" style="display:none"><label class="col-sm-4">Company</label><div class="col-sm-8"><select id="co-company" class="form-control"></select></div></div>
    <div class="form-group"><label class="col-sm-4">Refund credit by</label><div class="col-sm-8"><select id="co-refund" class="form-control"><option value="">—</option><option value="cash">Cash</option><option value="transfer">Bank transfer</option></select></div></div>
    <div id="co-error" class="alert alert-danger" style="display:none"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" id="co-submit">Check out &amp; close folio</button></div>
</div></div></div>

<div class="modal fade" id="mv-modal"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h4>Move room</h4></div>
  <div class="modal-body"><input type="hidden" id="mv-booking"><select id="mv-room" class="form-control"></select><input id="mv-reason" class="form-control" placeholder="Reason" style="margin-top:6px"><div id="mv-error" class="alert alert-danger" style="display:none"></div></div>
  <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" id="mv-submit">Move</button></div>
</div></div></div>
<div class="modal fade" id="ex-modal"><div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header"><h4>Extend / shorten stay</h4></div><div class="modal-body"><input type="hidden" id="ex-booking"><label>New departure date</label><input type="date" id="ex-to" class="form-control"><div id="ex-error" class="alert alert-danger" style="display:none"></div></div><div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-primary" id="ex-submit">Apply</button></div></div></div></div>
<script>var pulseCompanies = {if isset($companies)}{$companies|json_encode}{else}[]{/if};</script>
