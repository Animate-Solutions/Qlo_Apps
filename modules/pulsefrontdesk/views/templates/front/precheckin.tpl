{extends file='page.tpl'}
{block name='page_content'}
<div class="pulse-guest" style="max-width:720px;margin:0 auto">
{if isset($invalid)}<h2>Link not valid</h2><p>This pre-check-in link has expired or is incorrect. Please contact the hotel.</p>
{elseif isset($done)}<h2>You're all set, {$b.guest}!</h2><p>Your registration is complete. On arrival, go straight to the desk to collect your key for room {$b.room_num}. We look forward to welcoming you on {$b.date_from}.</p>
{else}
<h2>Welcome to {$hotel}, {$b.guest}</h2>
<p>Complete your registration now and skip the paperwork at the desk. Stay: <b>{$b.date_from} → {$b.date_to}</b> · {$b.room_type_name} · {$b.nights} night(s)</p>
{if isset($error)}<div class="alert alert-danger">{$error}</div>{/if}
<form method="post" enctype="multipart/form-data" class="form-horizontal" id="pc-form">
<fieldset><legend>Your details</legend>
<div class="form-group"><label class="col-sm-3">Phone</label><div class="col-sm-9"><input name="phone" class="form-control" value="{$profile.phone}" required></div></div>
<div class="form-group"><label class="col-sm-3">Nationality</label><div class="col-sm-9"><input name="nationality" class="form-control" value="{$profile.nationality|default:'NGA'}" maxlength="3"></div></div>
<div class="form-group"><label class="col-sm-3">Home address</label><div class="col-sm-9"><input name="address" class="form-control" value="{$profile.address}"></div></div></fieldset>
<fieldset><legend>Identification (required by law)</legend>
<div class="form-group"><label class="col-sm-3">ID type</label><div class="col-sm-9"><select name="id_type" class="form-control"><option value="nin">NIN</option><option value="intl_passport">International passport</option><option value="drivers_licence">Driver's licence</option><option value="voters_card">Voter's card</option><option value="passport">Passport (foreign)</option></select></div></div>
<div class="form-group"><label class="col-sm-3">ID number</label><div class="col-sm-9"><input name="id_number" class="form-control" required></div></div>
<div class="form-group"><label class="col-sm-3">Issuing country</label><div class="col-sm-9"><input name="issuing_country" class="form-control" value="NGA" maxlength="3"></div></div>
<div class="form-group"><label class="col-sm-3">Expiry</label><div class="col-sm-9"><input name="expiry" type="date" class="form-control"></div></div>
<div class="form-group"><label class="col-sm-3">Photo of ID</label><div class="col-sm-9"><input name="id_scan" type="file" accept="image/*,.pdf" capture="environment"><small>JPG/PNG/PDF, max 5MB</small></div></div></fieldset>
<fieldset><legend>Preferences</legend>
<div class="form-group"><label class="col-sm-3">Pillow</label><div class="col-sm-9"><input name="pref_pillow" class="form-control" value="{$profile.preferences.pillow|default:''}" placeholder="soft / firm"></div></div>
<div class="form-group"><label class="col-sm-3">Floor</label><div class="col-sm-9"><input name="pref_floor" class="form-control" value="{$profile.preferences.floor|default:''}" placeholder="high / low"></div></div>
<div class="form-group"><label class="col-sm-3">Dietary</label><div class="col-sm-9"><input name="pref_dietary" class="form-control" value="{$profile.preferences.dietary|default:''}"></div></div>
<div class="form-group"><label class="col-sm-3">Anything else</label><div class="col-sm-9"><input name="pref_other" class="form-control" value="{$profile.preferences.other|default:''}"></div></div></fieldset>
{if $offers}<fieldset><legend>Make your stay even better</legend>
{foreach $offers as $o}<div class="offer"><label><input type="checkbox" name="upsell[]" value='{$o|json_encode|escape:'html'}'> {$o.name}</label><b>{$currency}{$o.total_excl|number_format:2} <small>+tax</small></b></div>{/foreach}</fieldset>{/if}
{if $preauth_available}<fieldset><legend>Card guarantee</legend><p>Authorise an incidental hold on your card so you can use express check-out on departure.</p><div class="form-group"><label class="col-sm-3">Hold amount</label><div class="col-sm-9"><input name="preauth" type="number" step="0.01" class="form-control" value="0"></div></div></fieldset>{/if}
<fieldset><legend>Registration &amp; signature</legend>
<div class="well" style="max-height:140px;overflow:auto;font-size:13px">{$terms|nl2br}</div>
<label><input type="checkbox" name="accept_terms" value="1" required> I accept the terms above</label>
<div class="form-group"><label class="col-sm-3">Full name</label><div class="col-sm-9"><input name="signed_name" class="form-control" value="{$b.guest}" required></div></div>
<p>Sign with your finger or mouse:</p><canvas class="sig-pad" id="pc-sig" data-target="#pc-sig-val"></canvas><input type="hidden" name="signature" id="pc-sig-val"><button type="button" class="btn btn-default btn-sm sig-clear" data-pad="#pc-sig">Clear</button></fieldset>
<button name="submitPrecheckin" class="btn btn-primary btn-lg btn-block" style="margin-top:12px">Complete registration</button>
</form>
<script src="{$module_dir}views/js/signature.js"></script>
<style>.sig-pad{border:1px dashed #999;background:#fff;width:100%;height:140px;touch-action:none}.offer{border:1px solid #ddd;border-radius:4px;padding:8px 12px;margin:6px 0;display:flex;justify-content:space-between}</style>
{/if}
</div>
{/block}
