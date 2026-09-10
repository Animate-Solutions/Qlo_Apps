<div class="pulse-kc" id="pulse-kc-desk" data-ajax="{$ajax_url|escape:'html':'UTF-8'}" data-agent="{$local_agent|escape:'html':'UTF-8'}" data-agent-port="{$agent_port|escape:'html':'UTF-8'}" data-encoder="{if $current_encoder}{$current_encoder.id_pulse_kc_encoder|escape:'html':'UTF-8'}{/if}">
<div class="panel">
  <h3><i class="icon-key"></i> Key Desk &mdash; {$business_date|escape:'html':'UTF-8'}
    <span class="pull-right">
      <form method="post" class="form-inline inline">
        <select name="id_encoder" class="form-control input-sm" id="kc-encoder">
          {foreach $encoders as $e}{if $e.active}<option value="{$e.id_pulse_kc_encoder|escape:'html':'UTF-8'}" data-local="{$e.local_only|escape:'html':'UTF-8'}" data-ref="{$e.encoder_ref|escape:'html':'UTF-8'}" {if $current_encoder && $e.id_pulse_kc_encoder == $current_encoder.id_pulse_kc_encoder}selected{/if}>{$e.name|escape:'html':'UTF-8'} — {$e.status|escape:'html':'UTF-8'}{if $e.test_mode} (test){/if}</option>{/if}{/foreach}
        </select>
        <button name="setWorkstation" class="btn btn-default btn-sm" title="Remember this encoder for me">Use this encoder</button>
      </form>
      <button class="btn btn-default btn-sm" id="kc-test"><i class="icon-plug"></i> Test</button>
      <button class="btn btn-default btn-sm" id="kc-read"><i class="icon-credit-card"></i> Read card</button>
    </span></h3>

  <div class="row kpis">
    <div class="col-md-2"><span>{$kpi.issued_today|escape:'html':'UTF-8'}</span>Issued today</div>
    <div class="col-md-2"><span>{$kpi.active|escape:'html':'UTF-8'}</span>Live cards</div>
    <div class="col-md-2"><span>{$kpi.mobile|escape:'html':'UTF-8'}</span>Mobile keys</div>
    <div class="col-md-2 {if $kpi.failed}text-danger{/if}"><span>{$kpi.failed|escape:'html':'UTF-8'}</span>Failed</div>
    <div class="col-md-2 {if $kpi.encoders_offline}text-danger{/if}"><span>{$kpi.encoders_offline|escape:'html':'UTF-8'}</span>Encoders offline</div>
    <div class="col-md-2 {if $kpi.low_battery}text-warning{/if}"><span>{$kpi.low_battery|escape:'html':'UTF-8'}</span>Flat locks</div>
  </div>
  <div id="kc-encoder-state" class="alert alert-info" style="display:none"></div>
  {if $kpi.queued}<div class="alert alert-warning">{$kpi.queued|escape:'html':'UTF-8'} key operation(s) are queued for an encoder that was unreachable. They retry automatically; the Encoders screen can force a run now.</div>{/if}

  <form method="get" class="form-inline kc-search">
    <input type="hidden" name="controller" value="AdminPulseKeycard"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
    <input name="q" value="{$q|escape:'html':'UTF-8'}" class="form-control input-lg" id="kc-q" placeholder="Room number or guest name — press Enter" autofocus>
    <button class="btn btn-primary btn-lg">Find</button>
  </form>

  <table class="table table-condensed kc-results"><thead><tr><th>Room</th><th>Guest</th><th>Type</th><th>Stay</th><th>Live keys</th><th></th></tr></thead><tbody>
  {foreach $results as $r}<tr{if $booking && $r.id_htl_booking == $booking.id} class="info"{/if}>
    <td class="kc-room">{$r.room_num|escape:'html':'UTF-8'}</td><td>{$r.guest|escape:'html':'UTF-8'}</td><td>{$r.room_type|escape:'html':'UTF-8'}</td><td>{$r.date_from|escape:'html':'UTF-8'} → {$r.date_to|escape:'html':'UTF-8'}</td>
    <td>{if $r.active_keys}<span class="badge">{$r.active_keys|escape:'html':'UTF-8'}</span>{else}<span class="text-muted">none</span>{/if}</td>
    <td><a class="btn btn-primary btn-sm" href="{$self_url}&q={$q|escape:'url'}&id_htl_booking={$r.id_htl_booking|escape:'html':'UTF-8'}">Open</a></td>
  </tr>{foreachelse}<tr><td colspan="6"><em>{if $q}No in-house guest matches "{$q|escape:'html':'UTF-8'}"{else}Type a room number or a guest name{/if}</em></td></tr>{/foreach}
  </tbody></table>

  {if $arrivals}<h4>Arriving today, no key cut yet ({$arrivals|count})</h4>
  <table class="table table-condensed"><tbody>{foreach $arrivals as $a}<tr><td class="kc-room">{$a.room_num|escape:'html':'UTF-8'}</td><td>{$a.guest|escape:'html':'UTF-8'}</td><td>{$a.date_from|escape:'html':'UTF-8'} → {$a.date_to|escape:'html':'UTF-8'}</td>
    <td><form method="post" class="inline"><input type="hidden" name="id_htl_booking" value="{$a.id_htl_booking|escape:'html':'UTF-8'}"><input type="hidden" name="rooms[]" value="{$a.id_room|escape:'html':'UTF-8'}"><input type="hidden" name="type" value="guest"><input type="hidden" name="id_encoder" value="{if $current_encoder}{$current_encoder.id_pulse_kc_encoder|escape:'html':'UTF-8'}{/if}">{foreach $default_doors as $d}<input type="hidden" name="doors[]" value="{$d|escape:'html':'UTF-8'}">{/foreach}<button name="issueKey" class="btn btn-success btn-sm">Cut key now</button></form></td></tr>{/foreach}</tbody></table>{/if}
</div>

{if $booking}
<div class="panel kc-stay">
  <h3>Room {$booking.room_num|escape:'html':'UTF-8'} &mdash; {$booking.guest|escape:'html':'UTF-8'} <small>{$booking.date_from|escape:'html':'UTF-8'} → {$booking.date_to|escape:'html':'UTF-8'} · folio {$booking.folio_no|default:'—'|escape:'html':'UTF-8'}</small></h3>
  <div class="row">
    <div class="col-md-5">
      <h4>Cut a key</h4>
      <form method="post" class="form-horizontal kc-issue">
        <input type="hidden" name="id_htl_booking" value="{$booking.id|escape:'html':'UTF-8'}">
        <input type="hidden" name="id_encoder" value="{if $current_encoder}{$current_encoder.id_pulse_kc_encoder|escape:'html':'UTF-8'}{/if}">
        <div class="form-group"><label class="col-sm-4">Key type</label><div class="col-sm-8"><select name="type" class="form-control">
          <option value="guest">Guest key (new — kills earlier cards on the lock)</option>
          <option value="duplicate">Duplicate (second card, same stay)</option>
          <option value="one_shot">One shot (maintenance / inspection, single entry)</option>
        </select></div></div>
        <div class="form-group"><label class="col-sm-4">Rooms</label><div class="col-sm-8">
          <label class="checkbox-inline"><input type="checkbox" name="rooms[]" value="{$booking.id_room|escape:'html':'UTF-8'}" checked> {$booking.room_num|escape:'html':'UTF-8'}</label>
          <p class="help-block">A family taking two rooms: tick the second room on its own row in the search list and cut the key from there, or add its id here.</p>
        </div></div>
        <div class="form-group"><label class="col-sm-4">Common doors</label><div class="col-sm-8">
          {foreach $doors as $d}{if $d.type != 'room'}<label class="checkbox-inline"><input type="checkbox" name="doors[]" value="{$d.id_pulse_kc_door|escape:'html':'UTF-8'}" {if $d.is_default}checked{/if}> {$d.name|escape:'html':'UTF-8'}</label>{/if}{/foreach}
        </div></div>
        <div class="form-group"><label class="col-sm-4">Valid from</label><div class="col-sm-8"><input name="valid_from" class="form-control" value="{$booking.suggested_from|escape:'html':'UTF-8'}"></div></div>
        <div class="form-group"><label class="col-sm-4">Valid to</label><div class="col-sm-8"><input name="valid_to" class="form-control" value="{$booking.suggested_to|escape:'html':'UTF-8'}"></div></div>
        <div class="form-group"><div class="col-sm-8 col-sm-offset-4">
          <label><input type="checkbox" name="override_deadbolt" value="1"> Deadbolt override</label>
          <label><input type="checkbox" name="override_dnd" value="1"> Do-not-disturb override</label>
        </div></div>
        <div class="form-group"><div class="col-sm-8 col-sm-offset-4">
          <button name="issueKey" class="btn btn-success btn-lg btn-block" accesskey="k"><i class="icon-key"></i> Put the card on the encoder and press here</button>
        </div></div>
      </form>
      {if $mobile_enabled}
      <form method="post" class="form-inline kc-mobile"><input type="hidden" name="id_htl_booking" value="{$booking.id|escape:'html':'UTF-8'}">
        <select name="channel" class="form-control input-sm"><option value="both">BLE + QR</option><option value="ble">BLE only</option><option value="qr">QR only</option></select>
        <button name="issueMobile" class="btn btn-info btn-sm"><i class="icon-mobile"></i> Send a mobile key to the guest</button>
      </form>{/if}
      <form method="post" class="form-inline kc-fallback"><input type="hidden" name="id_room" value="{$booking.id_room|escape:'html':'UTF-8'}"><input type="hidden" name="id_htl_booking" value="{$booking.id|escape:'html':'UTF-8'}">
        <input name="note" class="form-control input-sm" placeholder="Mechanical key number / reason">
        <button name="mechanicalKey" class="btn btn-warning btn-sm">Encoder down — log a mechanical key</button>
      </form>
      <form method="post" class="form-inline kc-fallback"><input type="hidden" name="id_room" value="{$booking.id_room|escape:'html':'UTF-8'}">
        <input name="reason" class="form-control input-sm" placeholder="Reason (card lost…)">
        <button name="cancelRoomKeys" class="btn btn-danger btn-sm" onclick="return confirm('Cancel every live card for room {$booking.room_num|escape:'html':'UTF-8'}?')">Cancel ALL keys for this room</button>
      </form>
    </div>

    <div class="col-md-7">
      <h4>Keys on this stay</h4>
      <table class="table table-condensed"><thead><tr><th>Key</th><th>Type</th><th>Card</th><th>Seq</th><th>Valid to</th><th>Status</th><th></th></tr></thead><tbody>
      {foreach $booking.keys as $k}<tr class="{if $k.status=='failed'}danger{elseif $k.status=='issued'}success{/if}">
        <td>{$k.key_no|escape:'html':'UTF-8'}{if $k.mechanical} <span class="label label-warning">metal</span>{/if}{if $k.mobile} <span class="label label-info">mobile</span>{/if}</td>
        <td>{$k.type|escape:'html':'UTF-8'}</td><td><code>{$k.card_serial|default:'—'|escape:'html':'UTF-8'}</code></td><td>{$k.sequence|escape:'html':'UTF-8'}</td><td>{$k.valid_to|date_format:"%d/%m %H:%M"}</td>
        <td>{$k.status|escape:'html':'UTF-8'}{if $k.last_error}<br><small class="text-danger">{$k.last_error|truncate:60|escape:'html':'UTF-8'}</small>{/if}</td>
        <td class="kc-actions"><form method="post" class="inline"><input type="hidden" name="id_key" value="{$k.id_pulse_kc_key|escape:'html':'UTF-8'}"><input type="hidden" name="id_encoder" value="{if $current_encoder}{$current_encoder.id_pulse_kc_encoder|escape:'html':'UTF-8'}{/if}">
          {if $k.status=='issued'}<button name="duplicateKey" class="btn btn-xs btn-default">Duplicate</button>
          <button name="reissueKey" class="btn btn-xs btn-warning" onclick="return confirm('Kill this card and cut a replacement?')">Lost — re-issue</button>
          <button name="cancelKey" class="btn btn-xs btn-danger" onclick="return confirm('Cancel this key?')">Cancel</button>{/if}
          {if $k.status=='failed'}<button name="retryKey" class="btn btn-xs btn-primary">Retry encode</button>{/if}
          {if $k.status=='issued'}<input name="valid_to" class="input-sm" style="width:150px" value="{$k.valid_to|escape:'html':'UTF-8'}"><button name="extendKey" class="btn btn-xs btn-info">Extend</button>{/if}
        </form></td></tr>
      {foreachelse}<tr><td colspan="7"><em>No key has been cut for this stay yet</em></td></tr>{/foreach}
      </tbody></table>

      {if $booking.mobile_keys}<h4>Mobile keys</h4>
      <table class="table table-condensed"><thead><tr><th>Issued</th><th>Channel</th><th>Device</th><th>Valid to</th><th>Refreshes</th><th>Status</th><th></th></tr></thead><tbody>
      {foreach $booking.mobile_keys as $m}<tr><td>{$m.date_add|date_format:"%d/%m %H:%M"}</td><td>{$m.channel|escape:'html':'UTF-8'}</td>
        <td>{if $m.device_fingerprint}<span class="label label-default" title="{$m.device_label|escape:'html':'UTF-8'}">bound</span>{else}<em>not bound yet</em>{/if}</td>
        <td>{$m.valid_to|date_format:"%d/%m %H:%M"}</td><td>{$m.refresh_count|escape:'html':'UTF-8'}</td><td>{$m.status|escape:'html':'UTF-8'} <small class="text-muted">{$m.delivered_via|escape:'html':'UTF-8'}</small></td>
        <td>{if $m.status != 'revoked'}<form method="post" class="inline"><input type="hidden" name="id_mobile" value="{$m.id_pulse_kc_mobile_key|escape:'html':'UTF-8'}"><button name="revokeMobile" class="btn btn-xs btn-danger">Revoke</button></form>{/if}</td></tr>{/foreach}
      </tbody></table>{/if}

      <h4>Recent door events on this room</h4>
      <table class="table table-condensed"><tbody>
      {foreach $booking.audit as $a}<tr class="{if $a.result=='denied'}warning{/if}"><td>{$a.opened_at|date_format:"%d/%m %H:%M"}</td><td>{$a.door_name|escape:'html':'UTF-8'}</td><td>{$a.event|replace:'_':' '|escape:'html':'UTF-8'}</td>
        <td>{$a.holder|default:$a.card_serial|escape:'html':'UTF-8'}</td><td>{$a.result|escape:'html':'UTF-8'}</td></tr>
      {foreachelse}<tr><td><em>No lock audit pulled for this room yet</em></td></tr>{/foreach}
      </tbody></table>
    </div>
  </div>
</div>
{/if}

<div class="modal fade" id="kc-card-modal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h4>Card on the encoder</h4></div>
  <div class="modal-body" id="kc-card-body"></div>
  <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Close</button></div>
</div></div></div>
</div>
