<div class="pulse-fd" id="walkin" data-ajax="{$ajax_url|escape:'html':'UTF-8'}" data-arrivals="{$arrivals_url|escape:'html':'UTF-8'}">
<div class="panel"><h3><i class="icon-plus"></i> New Reservation &mdash; walk-in / phone / day-use</h3>
<div class="row">
 <div class="col-md-4"><h4>1. Dates</h4>
  <div class="form-inline"><input type="date" id="wi-from" class="form-control" value="{$prefill.from|default:$business_date}"> <input type="date" id="wi-to" class="form-control" value="{$prefill.to|default:''}"> <button class="btn btn-default" id="wi-quote">Check availability</button></div>
  <label style="margin-top:8px"><input type="checkbox" id="wi-dayuse"> Day use (same-day, no overnight)</label>
  <div id="wi-dayuse-opts" style="display:none" class="form-inline">Out by <input type="time" id="wi-du-until" class="form-control" value="18:00"> Rate <input id="wi-du-rate" type="number" step="0.01" class="form-control" placeholder="Day-use rate (tax incl)"></div>
  <h4>2. Guest</h4>
  <input id="wi-lookup" class="form-control" placeholder="Search existing guest by name / email / phone"><div id="wi-lookup-res"></div>
  <div class="row"><div class="col-xs-6"><input id="wi-first" class="form-control" placeholder="First name"></div><div class="col-xs-6"><input id="wi-last" class="form-control" placeholder="Last name"></div></div>
  <input id="wi-email" class="form-control" placeholder="Email (optional)"><input id="wi-phone" class="form-control" placeholder="Phone"><input id="wi-nat" class="form-control" placeholder="Nationality (NGA)" maxlength="3">
  <select id="wi-company" class="form-control"><option value="">— No company —</option>{foreach $companies as $c}<option value="{$c.id_pulse_company}" data-disc="{$c.discount_pct}">{$c.name} ({$c.discount_pct}%)</option>{/foreach}</select>
  <label><input type="checkbox" id="wi-billco"> Bill room to company (city ledger)</label>
  <select id="wi-source" class="form-control"><option value="walkin">Walk-in</option><option value="phone">Phone</option><option value="email">Email</option><option value="agent">Agent</option></select>
  <textarea id="wi-comment" class="form-control" placeholder="Remarks"></textarea>
 </div>
 <div class="col-md-5"><h4>3. Rooms &amp; rate</h4><div id="wi-types"><em>Enter dates and check availability</em></div></div>
 <div class="col-md-3"><h4>4. Confirm</h4>
  <div id="wi-summary" class="well well-sm"></div>
  <div class="form-inline">Deposit <input id="wi-deposit" type="number" step="0.01" class="form-control" style="width:110px" value="0"> <select id="wi-depmethod" class="form-control">{foreach $payment_codes as $p}{if $p.code!='CL' && $p.code!='DEP'}<option value="{$p.code}">{$p.name}</option>{/if}{/foreach}</select></div>
  <label style="margin-top:8px"><input type="checkbox" id="wi-checkin"> Check in now</label>
  <div id="wi-idrow" style="display:none"><select id="wi-idtype" class="form-control"><option value="nin">NIN</option><option value="intl_passport">Intl passport</option><option value="drivers_licence">Driver's licence</option><option value="voters_card">Voter's card</option></select><input id="wi-idnum" class="form-control" placeholder="ID number"></div>
  <button class="btn btn-success btn-lg btn-block" id="wi-create" style="margin-top:10px">Create reservation</button>
  <div id="wi-error" class="alert alert-danger" style="display:none"></div>
  <p class="help-block">Orders are created via payment module <code>{$walkin_module|default:'bankwire'}</code> (installed: {$payment_modules|implode:', '}). Change in Front Desk Settings.</p>
 </div>
</div></div></div>
<script>
(function($){ var A=$('#walkin').data('ajax'), sel={}, types=[];
function money(v){return Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2});}
$('#wi-dayuse').change(function(){ $('#wi-dayuse-opts').toggle(this.checked); if(this.checked){ var d=new Date($('#wi-from').val()+'T00:00:00'); d.setDate(d.getDate()+1); $('#wi-to').val(d.toISOString().substr(0,10)); } });
$('#wi-checkin').change(function(){ $('#wi-idrow').toggle(this.checked); });
$('#wi-quote').click(function(){ $.post(A,{ajax:1,action:'Quote',from:$('#wi-from').val(),to:$('#wi-to').val()},function(d){ types=d.types; var h='';
 types.forEach(function(t){ var ok=t.over_allowed>0; h+='<div class="offer" style="'+(ok?'':'opacity:.5')+'"><div><b>'+t.name+'</b><br><small>'+t.available+' free of '+t.physical+(t.over_allowed>t.available?' (+'+(t.over_allowed-t.available)+' overbook)':'')+' · '+money(t.per_night)+'/night · '+t.nights+' night(s) = '+money(t.total)+'</small><br>'+(ok?'<select class="wi-room input-sm" data-type="'+t.id_product+'"><option value="">Auto-assign best room</option>'+t.rooms.map(function(r){return '<option value="'+r.id_room+'">'+r.room_num+' ('+(r.hk_status||'clean').replace(/_/g,' ')+')</option>';}).join('')+'</select> A<input class="wi-ad input-sm" data-type="'+t.id_product+'" value="1" style="width:36px"> C<input class="wi-ch input-sm" data-type="'+t.id_product+'" value="0" style="width:36px"> Rate<input class="wi-rate input-sm" data-type="'+t.id_product+'" placeholder="'+money(t.per_night)+'" style="width:90px">':'')+'</div><div>'+(ok?'<input type="number" min="0" max="'+t.over_allowed+'" value="0" class="wi-qty input-sm" data-type="'+t.id_product+'" style="width:50px">':'Sold out')+'</div></div>'; });
 $('#wi-types').html(h); var pf={$prefill|json_encode}; if(pf.id_product){ $('.wi-qty[data-type='+pf.id_product+']').val(1); if(pf.id_room) $('.wi-room[data-type='+pf.id_product+']').val(pf.id_room); } summary(); },'json'); });
$(document).on('change','.wi-qty,.wi-rate,#wi-company',summary);
function summary(){ var tot=0,n=0,h=''; var disc=parseFloat($('#wi-company option:selected').data('disc')||0); $('.wi-qty').each(function(){ var q=parseInt($(this).val()||0), t=types.filter(function(x){return x.id_product==$(this).data('type');},this)[0]; if(q>0&&t){ var rate=parseFloat($('.wi-rate[data-type='+t.id_product+']').val()||t.per_night); if(disc&&!$('.wi-rate[data-type='+t.id_product+']').val()) rate=rate*(1-disc/100); n+=q; tot+=q*rate*t.nights; h+=q+' × '+t.name+' @ '+money(rate)+'<br>'; } }); $('#wi-summary').html(h+(n?'<b>Total '+money(tot)+'</b>':'<em>No rooms selected</em>')); }
$('#wi-lookup').on('keyup',function(){ var q=$(this).val(); if(q.length<3) return; $.post(A,{ajax:1,action:'CustomerLookup',q:q},function(d){ $('#wi-lookup-res').html(d.customers.map(function(c){ return '<a href="#" class="wi-pick list-group-item" data-c=\''+JSON.stringify(c)+'\'>'+c.firstname+' '+c.lastname+' <small>'+(c.email||'')+' '+(c.phone||'')+'</small> '+(c.vip_level>0?'★':'')+(c.blacklisted==1?' <span class="label label-danger">BLACKLIST</span>':'')+(c.stays>0?' <span class="badge">'+c.stays+' stays</span>':'')+'</a>'; }).join('')); },'json'); });
$(document).on('click','.wi-pick',function(e){ e.preventDefault(); var c=$(this).data('c'); $('#wi-first').val(c.firstname); $('#wi-last').val(c.lastname); $('#wi-email').val(c.email); $('#wi-phone').val(c.phone); $('#wi-lookup-res').empty(); });
$('#wi-create').click(function(){ var rooms=[]; var disc=parseFloat($('#wi-company option:selected').data('disc')||0);
 $('.wi-qty').each(function(){ var q=parseInt($(this).val()||0), tp=$(this).data('type'); for(var i=0;i<q;i++){ var t=types.filter(function(x){return x.id_product==tp;})[0]; var r=$('.wi-rate[data-type='+tp+']').val(); rooms.push({id_product:tp,id_room:i==0?($('.wi-room[data-type='+tp+']').val()||null):null,adults:$('.wi-ad[data-type='+tp+']').val(),children:$('.wi-ch[data-type='+tp+']').val(),rate_override:r?r:(disc?(t.per_night*(1-disc/100)).toFixed(2):null)}); } });
 $('#wi-error').hide(); $('#wi-create').prop('disabled',true);
 $.post(A,{ajax:1,action:'Create',from:$('#wi-from').val(),to:$('#wi-to').val(),rooms:JSON.stringify(rooms),firstname:$('#wi-first').val(),lastname:$('#wi-last').val(),email:$('#wi-email').val(),phone:$('#wi-phone').val(),nationality:$('#wi-nat').val(),id_company:$('#wi-company').val(),bill_to_company:$('#wi-billco').is(':checked')?1:0,source:$('#wi-source').val(),comment:$('#wi-comment').val(),deposit:$('#wi-deposit').val(),deposit_method:$('#wi-depmethod').val(),day_use:$('#wi-dayuse').is(':checked')?1:0,day_use_until:$('#wi-du-until').val(),day_use_rate:$('#wi-du-rate').val(),checkin_now:$('#wi-checkin').is(':checked')?1:0,id_type:$('#wi-idtype').val(),id_number:$('#wi-idnum').val(),issuing_country:$('#wi-nat').val()||'NGA'},function(d){ $('#wi-create').prop('disabled',false); if(!d.ok) return $('#wi-error').text(d.error).show(); showSuccessMessage('Reservation created — order #'+d.id_order); location.href=$('#walkin').data('arrivals')+'&date='+$('#wi-from').val(); },'json'); });
if($('#wi-from').val()&&$('#wi-to').val()) $('#wi-quote').click();
})(jQuery);
</script>
