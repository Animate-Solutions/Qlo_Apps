<div class="pulse-crm"><div class="panel"><h3><i class="icon-star-half-o"></i> Reviews</h3>
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrmReviews"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control">
  <select name="source" class="form-control"><option value="">All sources</option>{foreach $sources as $s}<option value="{$s|escape:'html':'UTF-8'}" {if $source == $s}selected{/if}>{$s|escape:'html':'UTF-8'}</option>{/foreach}</select>
  <label class="checkbox-inline"><input type="checkbox" name="unanswered" value="1" {if $unanswered}checked{/if}> only unanswered</label>
  <button class="btn btn-primary">Run</button></form>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#v-queue">Needs a reply ({$needs_reply|count})</a></li>
  <li><a data-toggle="tab" href="#v-all">All reviews ({$reviews|count})</a></li>
  <li><a data-toggle="tab" href="#v-dash">By source &amp; trend</a></li>
  <li><a data-toggle="tab" href="#v-add">Add or import</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="v-queue">
<table class="table table-condensed"><thead><tr><th>Date</th><th>Source</th><th>Rating</th><th>Author</th><th>Review</th><th>Department</th><th>Sentiment</th><th></th></tr></thead><tbody>
{foreach $needs_reply as $rv}<tr class="{if $rv.sentiment == 'negative'}danger{elseif $rv.rating_pct < 70}warning{/if}">
  <td>{$rv.review_date|escape:'html':'UTF-8'}</td><td>{$rv.source|escape:'html':'UTF-8'}</td><td><b>{$rv.rating|escape:'html':'UTF-8'}</b>/{$rv.rating_scale|escape:'html':'UTF-8'}</td><td>{$rv.author|escape:'html':'UTF-8'}</td>
  <td>{if $rv.title}<b>{$rv.title|escape:'html':'UTF-8'}</b><br>{/if}<small>{$rv.body|truncate:160|escape:'html':'UTF-8'}</small></td><td>{$rv.department|escape:'html':'UTF-8'}</td><td>{$rv.sentiment|escape:'html':'UTF-8'}</td>
  <td><a class="btn btn-xs btn-primary" href="{$self_url}&id_review={$rv.id_pulse_crm_review|escape:'html':'UTF-8'}">Reply</a></td></tr>
{foreachelse}<tr><td colspan="8"><em>Every review has an answer. That is rarer than it sounds.</em></td></tr>{/foreach}
</tbody></table>

{if $r}
<h4>{$r.source|escape:'html':'UTF-8'} — {$r.rating|escape:'html':'UTF-8'}/{$r.rating_scale|escape:'html':'UTF-8'} on {$r.review_date|escape:'html':'UTF-8'} by {$r.author|escape:'html':'UTF-8'}</h4>
{if $r.title}<p><b>{$r.title|escape:'html':'UTF-8'}</b></p>{/if}
<pre class="crm-note">{$r.body|escape:'html'}</pre>
{if $r.url}<p><a href="{$r.url|escape:'html'}" target="_blank" rel="noopener">Open it on {$r.source|escape:'html':'UTF-8'}</a></p>{/if}
<form method="post"><input type="hidden" name="id_review_a" value="{$r.id_pulse_crm_review|escape:'html':'UTF-8'}">
  <textarea name="response_text" class="form-control" rows="5" placeholder="Thank them by name, name the thing that went wrong, say what changed. Do not argue in public.">{$r.response_text|escape:'html'}</textarea>
  <br><button name="respondReview" class="btn btn-primary">Record the reply</button>
  <button name="deleteReview" class="btn btn-link" onclick="return confirm('Delete this review from the register?')">Delete</button>
</form>
<p class="help-block">Pulse records the reply and the time it took; posting it on the portal itself is still a human job, because none of TripAdvisor, Google or Booking.com will let a PMS post on your behalf without their partner agreement.</p>
{/if}
</div>

<div class="tab-pane" id="v-all">
<table class="table table-condensed"><thead><tr><th>Date</th><th>Source</th><th>Rating</th><th>Author</th><th>Title</th><th>Department</th><th>Sentiment</th><th>Replied</th><th></th></tr></thead><tbody>
{foreach $reviews as $rv}<tr class="{if $rv.sentiment == 'negative'}danger{/if}">
  <td>{$rv.review_date|escape:'html':'UTF-8'}</td><td>{$rv.source|escape:'html':'UTF-8'}</td><td>{$rv.rating|escape:'html':'UTF-8'}/{$rv.rating_scale|escape:'html':'UTF-8'}</td><td>{$rv.author|escape:'html':'UTF-8'}</td><td>{$rv.title|truncate:60|escape:'html':'UTF-8'}</td>
  <td>{$rv.department|escape:'html':'UTF-8'}</td><td>{$rv.sentiment|escape:'html':'UTF-8'}</td><td>{if $rv.responded}{$rv.responded_at|date_format:"%d/%m/%Y"}<br><small>{$rv.responder|escape:'html':'UTF-8'}</small>{else}<span class="badge crm-det">no</span>{/if}</td>
  <td><a class="btn btn-xs btn-default" href="{$self_url}&id_review={$rv.id_pulse_crm_review|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="9"><em>No reviews in this window.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="v-dash">
<table class="table table-condensed"><thead><tr><th>Source</th><th>Reviews</th><th>Average</th><th>Normalised</th><th>Replied</th><th>Response rate</th><th>Avg hours to reply</th><th>Negative</th></tr></thead><tbody>
{foreach $by_source as $s}<tr><td><b>{$s.source|escape:'html':'UTF-8'}</b></td><td>{$s.reviews|escape:'html':'UTF-8'}</td><td>{$s.avg_rating|escape:'html':'UTF-8'} / {$s.scale|escape:'html':'UTF-8'}</td><td>{$s.pct|escape:'html':'UTF-8'}%</td><td>{$s.responded|escape:'html':'UTF-8'}</td>
  <td>{$s.response_rate|escape:'html':'UTF-8'}%</td><td>{$s.avg_response_hours|escape:'html':'UTF-8'}</td><td>{$s.negative|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="8"><em>Nothing recorded.</em></td></tr>{/foreach}
</tbody></table>
<p>Median time to reply: <b>{if $median_hours !== null}{$median_hours|escape:'html':'UTF-8'} hours{else}—{/if}</b>. The median is the honest number here; one review left unanswered for a year ruins the mean.</p>
<h4>Trend</h4>
<table class="table table-condensed"><thead><tr><th>Month</th><th>Reviews</th><th>Score</th><th>Negative</th><th>Replied</th><th></th></tr></thead><tbody>
{foreach $trend as $t}<tr><td>{$t.ym|escape:'html':'UTF-8'}</td><td>{$t.reviews|escape:'html':'UTF-8'}</td><td>{$t.pct|escape:'html':'UTF-8'}%</td><td>{$t.negative|escape:'html':'UTF-8'}</td><td>{$t.responded|escape:'html':'UTF-8'}</td><td><div class="crm-bar"><span style="width:{$t.pct|escape:'html':'UTF-8'}%"></span></div></td></tr>
{foreachelse}<tr><td colspan="6"><em>No history yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="v-add">
<div class="row"><div class="col-md-5">
<h4>Add one by hand</h4>
<form method="post" class="form-horizontal"><input type="hidden" name="id_review_a" value="{if $r}{$r.id_pulse_crm_review|escape:'html':'UTF-8'}{else}0{/if}">
  <div class="form-group"><label class="col-sm-4">Source</label><div class="col-sm-8"><select name="source_new" class="form-control">{foreach $sources as $s}<option value="{$s|escape:'html':'UTF-8'}">{$s|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
  <div class="form-group"><label class="col-sm-4">Rating</label><div class="col-sm-8"><input name="rating" class="form-control" placeholder="4.5"> out of <input type="number" name="rating_scale" class="form-control" value="5" style="width:70px;display:inline-block"></div></div>
  <div class="form-group"><label class="col-sm-4">Date</label><div class="col-sm-8"><input type="date" name="review_date" class="form-control" value="{$smarty.now|date_format:'%Y-%m-%d'}"></div></div>
  <div class="form-group"><label class="col-sm-4">Author</label><div class="col-sm-8"><input name="author" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-4">Title</label><div class="col-sm-8"><input name="title" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-4">Review</label><div class="col-sm-8"><textarea name="body" class="form-control" rows="5"></textarea></div></div>
  <div class="form-group"><label class="col-sm-4">Link</label><div class="col-sm-8"><input name="url" class="form-control"></div></div>
  <div class="form-group"><label class="col-sm-4">External id</label><div class="col-sm-8"><input name="external_id" class="form-control" placeholder="stops a re-import creating a duplicate"></div></div>
  <div class="form-group"><label class="col-sm-4">Department</label><div class="col-sm-8"><input name="department" class="form-control" placeholder="leave blank and Pulse guesses from the text"></div></div>
  <button name="saveReview" class="btn btn-primary">Save review</button>
</form>
</div><div class="col-md-7">
<h4>Import an export</h4>
<p>Paste or upload the CSV or JSON your portal gives you. Column names are matched loosely — <code>rating</code>, <code>score</code>, <code>reviewer_score</code> and <code>overall</code> all work, as do Booking.com's separate <code>positive</code> and <code>negative</code> fields. A row with the same source and external id updates rather than duplicates.</p>
<form method="post" enctype="multipart/form-data">
  <div class="row"><div class="col-md-5"><select name="import_source" class="form-control">{foreach $sources as $s}<option value="{$s|escape:'html':'UTF-8'}">{$s|escape:'html':'UTF-8'}</option>{/foreach}</select></div>
  <div class="col-md-4"><select name="import_format" class="form-control"><option value="auto">detect the format</option><option value="csv">CSV</option><option value="json">JSON</option></select></div>
  <div class="col-md-3"><input type="file" name="import_file" class="form-control"></div></div>
  <br><textarea name="import_text" class="form-control" rows="10" placeholder="…or paste the file contents here"></textarea>
  <br><button name="importReviews" class="btn btn-primary">Import</button>
</form>
{if $import_result}
<div class="alert alert-info">{$import_result.created|escape:'html':'UTF-8'} created, {$import_result.updated|escape:'html':'UTF-8'} updated, {$import_result.skipped|escape:'html':'UTF-8'} skipped.
  {if $import_result.errors}<ul>{foreach $import_result.errors as $e}<li>{$e|escape:'html':'UTF-8'}</li>{/foreach}</ul>{/if}</div>
{/if}
<p class="help-block">A review that comes in at two stars or below opens a service-recovery case automatically, routed to the department the text is about.</p>
</div></div>
</div>

</div></div></div>
