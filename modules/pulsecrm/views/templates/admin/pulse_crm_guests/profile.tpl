<div class="pulse-crm"><div class="panel">
<h3><i class="icon-user"></i> {$p.firstname|escape:'html':'UTF-8'} {$p.lastname|escape:'html':'UTF-8'}
  {if $p.vip_level > 0}<span class="badge crm-vip">VIP {$p.vip_level|escape:'html':'UTF-8'}</span>{/if}
  {if $p.blacklisted}<span class="badge crm-det">blacklisted</span>{/if}
  {if $p.ext.forgotten}<span class="badge">erased at the guest's request</span>{/if}
  <small class="pull-right"><a href="{$self_url}">&larr; back to search</a></small></h3>
<p class="text-muted">{$p.email|escape:'html':'UTF-8'}{if $p.phone} · {$p.phone|escape:'html':'UTF-8'}{/if}{if $p.company_name} · {$p.company_name|escape:'html':'UTF-8'}{/if}
 — {$p.stays|escape:'html':'UTF-8'} stays, {$p.nights|escape:'html':'UTF-8'} nights, {displayPrice price=$p.lifetime_revenue} lifetime{if $p.last_stay}, last stayed {$p.last_stay|escape:'html':'UTF-8'}{/if}.
 {foreach $p.tags as $t}<span class="badge crm-tag-{$t.colour|escape:'html':'UTF-8'}">{$t.name|escape:'html':'UTF-8'}</span> {/foreach}</p>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#g-prefs">Preferences</a></li>
  <li><a data-toggle="tab" href="#g-occ">Occasions &amp; relationships</a></li>
  <li><a data-toggle="tab" href="#g-consent">Consent</a></li>
  <li><a data-toggle="tab" href="#g-loyalty">Loyalty</a></li>
  <li><a data-toggle="tab" href="#g-feedback">Feedback &amp; cases</a></li>
  <li><a data-toggle="tab" href="#g-marketing">Marketing history</a></li>
  <li><a data-toggle="tab" href="#g-stays">Stays</a></li>
  <li><a data-toggle="tab" href="#g-admin">Profile &amp; erasure</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="g-prefs">
<div class="row"><div class="col-md-7">
<table class="table table-condensed"><thead><tr><th>Category</th><th>Preference</th><th>Source</th><th></th></tr></thead><tbody>
{foreach $p.crm_preferences as $pr}<tr class="{if $pr.is_service_note}danger{/if}"><td>{$pr.category|replace:'_':' '|escape:'html':'UTF-8'}</td><td>{$pr.value|escape:'html':'UTF-8'}{if $pr.is_service_note} <span class="badge crm-det">service note</span>{/if}</td><td><small>{$pr.source|escape:'html':'UTF-8'}, {$pr.date_add|date_format:"%d/%m/%Y"}</small></td>
<td><form method="post" class="inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}"><input type="hidden" name="id_pref" value="{$pr.id_pulse_crm_preference|escape:'html':'UTF-8'}"><button name="delPref" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="4"><em>Nothing recorded. Ask at check-in — it is the cheapest loyalty there is.</em></td></tr>{/foreach}
</tbody></table>
</div><div class="col-md-5">
<form method="post" class="form-horizontal"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-4">Category</label><div class="col-sm-8"><select name="category" class="form-control" id="crm-pref-cat">
    <option value="room_position">Room position</option><option value="floor">Floor</option><option value="pillow">Pillow</option><option value="bed">Bed</option>
    <option value="allergy">Allergy (service note)</option><option value="dietary">Dietary (service note)</option><option value="newspaper">Newspaper</option>
    <option value="transport">Transport</option><option value="amenity">Amenity</option><option value="housekeeping">Housekeeping</option><option value="other">Other</option></select></div></div>
  <div class="form-group"><label class="col-sm-4">From the list</label><div class="col-sm-8"><select name="pcode" class="form-control" id="crm-pref-code"><option value="">—</option>
    {foreach $options as $o}<option value="{$o.code|escape:'html':'UTF-8'}" data-cat="{$o.category|escape:'html':'UTF-8'}">{$o.label|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Or type it</label><div class="col-sm-8"><input name="pvalue" class="form-control" placeholder="e.g. always room 214, hates air freshener"></div></div>
  <button name="savePref" class="btn btn-primary">Add preference</button>
</form>
<p class="help-block">Allergies and dietary notes are flagged as service notes: they show on the arrivals board and go to the kitchen, not into a mailing list.</p>
</div></div>
</div>

<div class="tab-pane" id="g-occ">
<div class="row"><div class="col-md-6">
<h4>Special occasions</h4>
<table class="table table-condensed"><thead><tr><th>Type</th><th>Date</th><th>Days away</th><th>Remind</th><th>Note</th><th></th></tr></thead><tbody>
{foreach $p.occasions as $o}<tr class="{if $o.days_away <= 7}warning{/if}"><td>{$o.type|escape:'html':'UTF-8'}</td><td>{$o.occasion_date|date_format:"%d %B %Y"}</td><td>{$o.days_away|escape:'html':'UTF-8'}</td><td>{$o.remind_days|escape:'html':'UTF-8'}d before</td><td>{$o.note|escape:'html':'UTF-8'}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}"><input type="hidden" name="id_occasion" value="{$o.id_pulse_crm_occasion|escape:'html':'UTF-8'}"><button name="delOccasion" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="6"><em>None recorded.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <select name="otype" class="form-control"><option value="birthday">Birthday</option><option value="anniversary">Anniversary</option><option value="graduation">Graduation</option><option value="promotion">Promotion</option><option value="religious">Religious</option><option value="other">Other</option></select>
  <input type="date" name="odate" class="form-control" required> <input name="onote" class="form-control" placeholder="Note"> <input type="number" name="oremind" value="7" class="form-control" style="width:70px" title="Remind this many days before">
  <button name="saveOccasion" class="btn btn-primary">Add</button></form>
</div><div class="col-md-6">
<h4>Relationships</h4>
<table class="table table-condensed"><tbody>
{foreach $p.relationships as $r}<tr><td>{$r.type|replace:'_':' '|escape:'html':'UTF-8'}</td><td><a href="{$self_url}&id_customer={$r.id_related_customer|escape:'html':'UTF-8'}">{$r.related_name|escape:'html':'UTF-8'}</a><br><small>{$r.related_email|escape:'html':'UTF-8'}</small></td><td>{$r.note|escape:'html':'UTF-8'}</td>
<td><form method="post" class="inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}"><input type="hidden" name="id_relation" value="{$r.id_pulse_crm_relationship|escape:'html':'UTF-8'}"><button name="delRelation" class="btn btn-xs btn-link">✕</button></form></td></tr>
{foreachelse}<tr><td><em>None recorded.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <select name="rtype" class="form-control"><option value="travels_with">Travels with</option><option value="spouse">Spouse</option><option value="partner">Partner</option><option value="child">Child</option><option value="colleague">Colleague</option><option value="assistant_of">Assistant of</option><option value="reports_to">Reports to</option><option value="same_company">Same company</option></select>
  <input name="id_related" class="form-control" placeholder="Customer ID" style="width:120px" required> <input name="rnote" class="form-control" placeholder="Note">
  <button name="saveRelation" class="btn btn-primary">Link</button></form>
<p class="help-block">Find the customer ID on the other guest's CRM page — it is in the address bar.</p>
</div></div>
</div>

<div class="tab-pane" id="g-consent">
<p>Consent governs every campaign and every journey. Nothing is sent on a channel that is not <b>opt in</b>, unless the message is transactional (a receipt, a survey the guest asked for, a loyalty statement).</p>
<form method="post"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
<table class="table table-condensed"><thead><tr><th>Channel</th><th>State</th><th>Recorded</th><th>Source</th><th>Evidence</th><th>Reason if opted out</th></tr></thead><tbody>
{foreach $p.consent as $ch => $c}<tr>
  <td>{$ch|escape:'html':'UTF-8'}</td>
  <td><select name="consent_{$ch|escape:'html':'UTF-8'}" class="form-control input-sm"><option value="">— leave as is —</option>
    <option value="opt_in" {if $c.state == 'opt_in'}selected{/if}>opt in</option>
    <option value="opt_out" {if $c.state == 'opt_out'}selected{/if}>opt out</option>
    <option value="unknown" {if $c.state == 'unknown'}selected{/if}>unknown</option></select></td>
  <td>{$c.date_consent|escape:'html':'UTF-8'}</td><td>{$c.source|escape:'html':'UTF-8'}</td><td><small>{$c.evidence|escape:'html':'UTF-8'}</small></td><td><small>{$c.unsub_reason|escape:'html':'UTF-8'}</small></td></tr>{/foreach}
</tbody></table>
<div class="row"><div class="col-md-4"><input name="consent_source" class="form-control" placeholder="Source (registration_card, phone, portal…)" value="desk"></div>
<div class="col-md-4"><input name="consent_evidence" class="form-control" placeholder="Evidence (signed card ref, call time…)"></div>
<div class="col-md-4"><input name="consent_reason" class="form-control" placeholder="Reason, if opting out"></div></div>
<br><button name="saveConsent" class="btn btn-primary">Record consent</button>
</form>
</div>

<div class="tab-pane" id="g-loyalty">
{if $p.member}
<p><b>{$p.member.program_name|escape:'html':'UTF-8'}</b> — member {$p.member.member_no|escape:'html':'UTF-8'}, card {$p.member.card_no|escape:'html':'UTF-8'}, tier <b>{$p.member.tier_name|escape:'html':'UTF-8'}</b> since {$p.member.tier_since|escape:'html':'UTF-8'}.
Balance <b>{$p.member.points_balance|number_format:0|escape:'html':'UTF-8'} points</b> ({displayPrice price=$p.member.points_balance*$p.member.point_value}).
Qualifying: {$p.member.qualifying_nights|escape:'html':'UTF-8'} nights, {$p.member.qualifying_stays|escape:'html':'UTF-8'} stays, {displayPrice price=$p.member.qualifying_spend}.</p>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Type</th><th>Points</th><th>Balance</th><th>Description</th><th>Expires</th></tr></thead><tbody>
{foreach $p.points as $t}<tr><td>{$t.date_add|date_format:"%d/%m/%Y"}</td><td>{$t.type|escape:'html':'UTF-8'}</td><td class="{if $t.points < 0}text-danger{else}text-success{/if}">{$t.points|escape:'html':'UTF-8'}</td><td>{$t.balance_after|escape:'html':'UTF-8'}</td><td>{$t.description|escape:'html':'UTF-8'}</td><td>{$t.expires_on|escape:'html':'UTF-8'}</td></tr>{/foreach}
</tbody></table>
{else}
<p>This guest is not enrolled.</p>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <select name="id_program" class="form-control">{foreach $programs as $pg}<option value="{$pg.id_pulse_crm_loyalty_program|escape:'html':'UTF-8'}">{$pg.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <button name="enrolMember" class="btn btn-primary">Enrol at the desk</button></form>
<p class="help-block">Enrolling records an email opt-in against the programme terms, and sends the welcome message with the member number.</p>
{/if}
</div>

<div class="tab-pane" id="g-feedback">
<h4>Survey responses</h4>
<table class="table table-condensed"><thead><tr><th>Date</th><th>Survey</th><th>NPS</th><th>Satisfaction</th><th>Weakest</th><th>Comment</th></tr></thead><tbody>
{foreach $p.responses as $r}<tr class="{if $r.nps_band == 'detractor'}danger{/if}"><td>{$r.completed_at|date_format:"%d/%m/%Y"}</td><td>{$r.survey_name|escape:'html':'UTF-8'}</td><td>{$r.nps|escape:'html':'UTF-8'}</td><td>{$r.gss|escape:'html':'UTF-8'}</td><td>{$r.department_low|escape:'html':'UTF-8'}</td><td><small>{$r.comment|truncate:120|escape:'html':'UTF-8'}</small></td></tr>
{foreachelse}<tr><td colspan="6"><em>No feedback yet.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <select name="id_survey" class="form-control">{foreach $surveys as $s}<option value="{$s.id_pulse_crm_survey|escape:'html':'UTF-8'}">{$s.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <button name="sendSurvey" class="btn btn-default">Send a survey now</button></form>
<h4>Recovery cases</h4>
<table class="table table-condensed"><tbody>
{foreach $p.cases as $c}<tr class="{if $c.status != 'closed'}warning{/if}"><td>{$c.case_no|escape:'html':'UTF-8'}</td><td>{$c.opened_at|date_format:"%d/%m/%Y"}</td><td>{$c.department|escape:'html':'UTF-8'}</td><td>{$c.title|escape:'html':'UTF-8'}</td><td>{$c.status|escape:'html':'UTF-8'}</td><td>{displayPrice price=$c.recovery_cost}</td></tr>
{foreachelse}<tr><td><em>None.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="g-marketing">
<div class="row"><div class="col-md-6">
<h4>Segments this guest is in</h4><ul>{foreach $p.segments as $s}<li>{$s.name|escape:'html':'UTF-8'}</li>{foreachelse}<li><em>None</em></li>{/foreach}</ul>
<h4>Journeys</h4><table class="table table-condensed"><tbody>
{foreach $p.journeys as $j}<tr><td>{$j.journey|escape:'html':'UTF-8'}</td><td>step {$j.step_index|escape:'html':'UTF-8'}</td><td>{$j.status|escape:'html':'UTF-8'}</td><td>{$j.next_run_at|escape:'html':'UTF-8'}</td><td><small>{$j.last_error|escape:'html':'UTF-8'}</small></td></tr>{foreachelse}<tr><td><em>None</em></td></tr>{/foreach}
</tbody></table>
</div><div class="col-md-6">
<h4>What we have sent</h4><table class="table table-condensed"><tbody>
{foreach $p.sends as $s}<tr><td>{$s.date_add|date_format:"%d/%m/%Y %H:%M"}</td><td>{$s.channel|escape:'html':'UTF-8'}</td><td>{$s.kind|escape:'html':'UTF-8'}</td><td><small>{$s.reference|escape:'html':'UTF-8'}</small></td></tr>{foreachelse}<tr><td><em>Nothing yet</em></td></tr>{/foreach}
</tbody></table>
<h4>Tags</h4>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <select name="tag_code" class="form-control">{foreach $tags as $t}<option value="{$t.code|escape:'html':'UTF-8'}">{$t.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <button name="addTag" class="btn btn-default btn-sm">Add</button> <button name="delTag" class="btn btn-default btn-sm">Remove</button></form>
</div></div>
</div>

<div class="tab-pane" id="g-stays">
<table class="table table-condensed"><thead><tr><th>From</th><th>To</th><th>Room</th><th>Room type</th><th>Value</th></tr></thead><tbody>
{foreach $p.history as $h}<tr><td>{$h.date_from|escape:'html':'UTF-8'}</td><td>{$h.date_to|escape:'html':'UTF-8'}</td><td>{$h.room_num|escape:'html':'UTF-8'}</td><td>{$h.room_type_name|escape:'html':'UTF-8'}</td><td>{displayPrice price=$h.total_price_tax_incl}</td></tr>
{foreachelse}<tr><td colspan="5"><em>No stays on record.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="g-admin">
<div class="row"><div class="col-md-6">
<h4>Marketing profile</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-4">Source of business</label><div class="col-sm-8"><input name="source_of_business" class="form-control" value="{$p.ext.source_of_business|escape:'html'}" placeholder="direct, booking.com, corporate, walk-in, referral"></div></div>
  <div class="form-group"><label class="col-sm-4">Market segment</label><div class="col-sm-8"><input name="market_segment" class="form-control" value="{$p.ext.market_segment|escape:'html'}" placeholder="corporate, leisure, government, crew, conference"></div></div>
  <div class="form-group"><label class="col-sm-4">Guest type</label><div class="col-sm-8"><input name="guest_type" class="form-control" value="{$p.ext.guest_type|escape:'html'}" placeholder="oil &amp; gas, NGO, diplomat, family"></div></div>
  <div class="form-group"><label class="col-sm-4">Language</label><div class="col-sm-8"><input name="preferred_language" class="form-control" value="{$p.ext.preferred_language|escape:'html'}" placeholder="en"></div></div>
  <div class="form-group"><label class="col-sm-4">Preferred channel</label><div class="col-sm-8"><select name="preferred_channel" class="form-control">
    <option value="email" {if $p.ext.preferred_channel == 'email'}selected{/if}>email</option>
    <option value="sms" {if $p.ext.preferred_channel == 'sms'}selected{/if}>SMS</option>
    <option value="whatsapp" {if $p.ext.preferred_channel == 'whatsapp'}selected{/if}>WhatsApp</option>
    <option value="none" {if $p.ext.preferred_channel == 'none'}selected{/if}>do not contact</option></select></div></div>
  <button name="saveExt" class="btn btn-primary">Save</button>
</form>
</div><div class="col-md-6">
<h4>Front Desk profile</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <div class="form-group"><label class="col-sm-4">VIP level</label><div class="col-sm-8"><input type="number" name="vip_level" class="form-control" value="{$p.vip_level|intval}" min="0" max="5"></div></div>
  <div class="form-group"><label class="col-sm-4">Company</label><div class="col-sm-8"><select name="id_pulse_company" class="form-control"><option value="0">—</option>{foreach $companies as $co}<option value="{$co.id_pulse_company|escape:'html':'UTF-8'}" {if $p.id_pulse_company == $co.id_pulse_company}selected{/if}>{$co.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Nationality</label><div class="col-sm-8"><input name="nationality" class="form-control" value="{$p.nationality|escape:'html'}" maxlength="3" placeholder="NGA"></div></div>
  <div class="form-group"><label class="col-sm-4">Phone</label><div class="col-sm-8"><input name="phone" class="form-control" value="{$p.phone|escape:'html'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Notes</label><div class="col-sm-8"><textarea name="notes" class="form-control" rows="3">{$p.notes|escape:'html'}</textarea></div></div>
  <button name="saveFdProfile" class="btn btn-primary">Save</button>
</form>
</div></div>
<hr>
<h4 class="text-danger">Forget this guest</h4>
<p>Erases preferences, occasions, relationships, tags, segment membership and queued messages, blanks survey comments, cancels live journeys and opts every channel out with the reason on record.
<b>The accounting trail is untouched</b> — folios, invoices, bookings and points transactions all stay, because the hotel still has to be able to prove what it charged.</p>
<form method="post" class="form-inline"><input type="hidden" name="id_customer" value="{$p.id_customer|escape:'html':'UTF-8'}">
  <input name="forget_reason" class="form-control" size="50" placeholder="Reason (their email, the call, the letter)" value="Guest exercised the right to erasure">
  <input name="confirm_forget" class="form-control" placeholder="Type FORGET" style="width:140px">
  <button name="forgetGuest" class="btn btn-danger" onclick="return confirm('Erase the marketing record for this guest? This cannot be undone.')">Erase marketing data</button>
</form>
</div>

</div></div></div>
