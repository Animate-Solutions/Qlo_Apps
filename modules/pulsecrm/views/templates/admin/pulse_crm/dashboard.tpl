<div class="pulse-crm"><div class="panel"><h3><i class="icon-heart"></i> CRM — {$business_date|escape:'html':'UTF-8'}</h3>
<div class="row crm-kpis">
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v {if $k.nps < 0}text-danger{elseif $k.nps >= 40}text-success{/if}">{$k.nps|escape:'html':'UTF-8'}</span><span class="l">NPS (90 days)</span><small>{$k.nps_responses|escape:'html':'UTF-8'} responses · {$k.promoters|escape:'html':'UTF-8'}P / {$k.detractors|escape:'html':'UTF-8'}D</small></div></div>
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v">{$k.members|escape:'html':'UTF-8'}</span><span class="l">Loyalty members</span><small>{$k.points_out|number_format:0|escape:'html':'UTF-8'} points out</small></div></div>
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v">{displayPrice price=$k.liability}</span><span class="l">Points liability</span><small>if every point were redeemed</small></div></div>
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v {if $k.cases_open}text-danger{/if}">{$k.cases_open|escape:'html':'UTF-8'}</span><span class="l">Open recovery cases</span><small>{displayPrice price=$k.recovery_cost} spent in 90 days</small></div></div>
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v">{$k.reviews_avg|escape:'html':'UTF-8'}%</span><span class="l">Review score</span><small>{$k.reviews_unanswered|escape:'html':'UTF-8'} awaiting a reply</small></div></div>
  <div class="col-md-2 col-sm-4"><div class="crm-kpi"><span class="v">{$k.opt_in_email|escape:'html':'UTF-8'}</span><span class="l">Email opt-ins</span><small>{$k.opt_out_email|escape:'html':'UTF-8'} opted out · {$k.segments|escape:'html':'UTF-8'} segments</small></div></div>
</div>

<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#c-arrivals">Arrivals today ({$arrivals|count})</a></li>
  <li><a data-toggle="tab" href="#c-feedback">Feedback</a></li>
  <li><a data-toggle="tab" href="#c-cases">Recovery ({$cases|count})</a></li>
  <li><a data-toggle="tab" href="#c-marketing">Marketing</a></li>
  <li><a data-toggle="tab" href="#c-loyalty">Loyalty</a></li>
  <li><a data-toggle="tab" href="#c-reviews">Reviews</a></li>
  <li><a data-toggle="tab" href="#c-sales">Sales</a></li>
</ul>
<div class="tab-content">

<div class="tab-pane active" id="c-arrivals">
{if !$fd}<div class="alert alert-info">Front Desk is not installed, so there is no arrivals list to decorate. Everything else on this screen still works.</div>{/if}
<table class="table table-condensed"><thead><tr><th>Room</th><th>Guest</th><th>Nights</th><th>Flags</th><th>Preferences</th><th>Service notes</th><th>Loyalty</th><th></th></tr></thead><tbody>
{foreach $arrivals as $a}
<tr class="{if $a.open_cases}danger{elseif $a.occasions}warning{elseif $a.vip_level > 0}success{/if}">
  <td><b>{$a.room_num|escape:'html':'UTF-8'}</b></td>
  <td><a href="{$guests_url}&id_customer={$a.id_customer|escape:'html':'UTF-8'}">{$a.guest|escape:'html':'UTF-8'}</a>{if $a.company_name}<br><small class="text-muted">{$a.company_name|escape:'html':'UTF-8'}</small>{/if}</td>
  <td>{$a.nights|escape:'html':'UTF-8'}</td>
  <td>{if $a.vip_level > 0}<span class="badge crm-vip">VIP {$a.vip_level|escape:'html':'UTF-8'}</span> {/if}
      {foreach $a.occasions as $o}<span class="badge crm-occ">{$o.type|escape:'html':'UTF-8'}{if $o.days_away == 0} today{elseif $o.days_away <= 7} in {$o.days_away|escape:'html':'UTF-8'}d{/if}</span> {/foreach}
      {if $a.nps_band == 'detractor'}<span class="badge crm-det">detractor</span>{elseif $a.nps_band == 'promoter'}<span class="badge crm-pro">promoter</span>{/if}
      {if $a.open_cases}<span class="badge crm-det">{$a.open_cases|escape:'html':'UTF-8'} open case{if $a.open_cases > 1}s{/if}</span>{/if}</td>
  <td><small>{foreach $a.prefs as $p}{$p.value|escape:'html':'UTF-8'}{if !$p@last} · {/if}{/foreach}</small></td>
  <td><small class="text-danger">{foreach $a.service_notes as $p}{$p.value|escape:'html':'UTF-8'}{if !$p@last} · {/if}{/foreach}</small></td>
  <td>{if $a.tier}{$a.tier|escape:'html':'UTF-8'} · {$a.points|number_format:0|escape:'html':'UTF-8'} pts<br><small class="text-muted">{$a.member_no|escape:'html':'UTF-8'}</small>{/if}</td>
  <td><a class="btn btn-xs btn-default" href="{$guests_url}&id_customer={$a.id_customer|escape:'html':'UTF-8'}">CRM tab</a></td>
</tr>
{foreachelse}<tr><td colspan="8"><em>No arrivals booked for {$business_date|escape:'html':'UTF-8'}.</em></td></tr>{/foreach}
</tbody></table>
{if $occasions}<h4>Occasions coming up</h4><table class="table table-condensed"><tbody>{foreach $occasions as $o}<tr><td>{$o.guest|escape:'html':'UTF-8'}</td><td>{$o.type|escape:'html':'UTF-8'}</td><td>{$o.occasion_date|date_format:"%d %B"}</td><td>{if $o.days_away == 0}<b>today</b>{else}in {$o.days_away|escape:'html':'UTF-8'} days{/if}</td><td><small>{$o.note|escape:'html':'UTF-8'}</small></td><td><a class="btn btn-xs btn-default" href="{$guests_url}&id_customer={$o.id_customer|escape:'html':'UTF-8'}">Open</a></td></tr>{/foreach}</tbody></table>{/if}
</div>

<div class="tab-pane" id="c-feedback">
<form method="get" class="form-inline noprint"><input type="hidden" name="controller" value="AdminPulseCrm"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
  <input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control"> <button class="btn btn-default">Run</button></form>
<p>Response rate <b>{$rate.pct|escape:'html':'UTF-8'}%</b> — {$rate.completed|escape:'html':'UTF-8'} completed of {$rate.invited|escape:'html':'UTF-8'} invited.</p>
<div class="row"><div class="col-md-6">
<h4>NPS by month</h4><table class="table table-condensed"><thead><tr><th>Month</th><th>Responses</th><th>NPS</th><th>Satisfaction</th><th></th></tr></thead><tbody>
{foreach $trend as $t}<tr><td>{$t.ym|escape:'html':'UTF-8'}</td><td>{$t.responses|escape:'html':'UTF-8'}</td><td><b>{$t.nps|escape:'html':'UTF-8'}</b></td><td>{$t.gss|escape:'html':'UTF-8'}</td><td><div class="crm-bar"><span style="width:{if $t.nps > 0}{$t.nps|escape:'html':'UTF-8'}{else}0{/if}%"></span></div></td></tr>{foreachelse}<tr><td colspan="5"><em>No completed surveys yet.</em></td></tr>{/foreach}
</tbody></table></div>
<div class="col-md-6"><h4>Department scores</h4><table class="table table-condensed"><thead><tr><th>Department</th><th>Answers</th><th>Mean /5</th><th>%</th><th>Poor (1-2)</th></tr></thead><tbody>
{foreach $dept as $d}<tr class="{if $d.avg_score < 3.5}danger{elseif $d.avg_score < 4}warning{/if}"><td>{$d.department|escape:'html':'UTF-8'}</td><td>{$d.answers|escape:'html':'UTF-8'}</td><td>{$d.avg_score|escape:'html':'UTF-8'}</td><td>{$d.pct|escape:'html':'UTF-8'}%</td><td>{$d.poor|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="5"><em>Nothing scored in this window.</em></td></tr>{/foreach}
</tbody></table></div></div>
</div>

<div class="tab-pane" id="c-cases">
<table class="table table-condensed"><thead><tr><th>Case</th><th>Opened</th><th>Severity</th><th>Department</th><th>Guest</th><th>Room</th><th>Title</th><th>Owner</th><th>SLA</th><th></th></tr></thead><tbody>
{foreach $cases as $c}<tr class="{if $c.overdue}danger{elseif $c.severity == 'critical' || $c.severity == 'high'}warning{/if}">
  <td>{$c.case_no|escape:'html':'UTF-8'}</td><td>{$c.opened_at|date_format:"%d/%m %H:%M"}</td><td>{$c.severity|escape:'html':'UTF-8'}</td><td>{$c.department|escape:'html':'UTF-8'}</td><td>{$c.guest|escape:'html':'UTF-8'}</td><td>{$c.room_num|escape:'html':'UTF-8'}</td>
  <td>{$c.title|escape:'html':'UTF-8'}</td><td>{$c.owner_name|escape:'html':'UTF-8'}</td><td>{if $c.overdue}<b class="text-danger">overdue</b>{else}{$c.sla_due|date_format:"%d/%m %H:%M"}{/if}</td>
  <td><a class="btn btn-xs btn-default" href="{$cases_url|escape:'html':'UTF-8'}&id_case={$c.id_pulse_crm_case|escape:'html':'UTF-8'}">Open</a></td></tr>
{foreachelse}<tr><td colspan="10"><em>Nothing open. Enjoy it while it lasts.</em></td></tr>{/foreach}
</tbody></table>
<h4>Cost of recovery by department</h4><table class="table table-condensed"><thead><tr><th>Department</th><th>Cases</th><th>Closed</th><th>Open</th><th>Cost</th><th>Average</th><th>Avg hours to close</th></tr></thead><tbody>
{foreach $cost as $c}<tr><td>{$c.department|escape:'html':'UTF-8'}</td><td>{$c.cases|escape:'html':'UTF-8'}</td><td>{$c.closed|escape:'html':'UTF-8'}</td><td>{$c.open|escape:'html':'UTF-8'}</td><td>{displayPrice price=$c.cost}</td><td>{displayPrice price=$c.avg_cost}</td><td>{$c.avg_hours|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="7"><em>No cases in this window.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="c-marketing">
<form method="post" class="form-inline noprint"><button name="refreshSegments" class="btn btn-default btn-sm">Refresh segments now</button> <button name="advanceJourneys" class="btn btn-default btn-sm">Advance journeys now</button>
<span class="text-muted"> — normally the cron does both; these buttons are for when you cannot wait.</span></form>
<h4>Campaign performance</h4><table class="table table-condensed"><thead><tr><th>Campaign</th><th>Channel</th><th>Segment</th><th>Status</th><th>Sent</th><th>Opened</th><th>Clicked</th><th>Unsub</th><th>Skipped</th><th>Failed</th></tr></thead><tbody>
{foreach $campaigns as $c}<tr>
  <td><a href="{$campaigns_url|escape:'html':'UTF-8'}&id_campaign={$c.id_pulse_crm_campaign|escape:'html':'UTF-8'}">{$c.name|escape:'html':'UTF-8'}</a></td><td>{$c.channel|escape:'html':'UTF-8'}</td><td>{$c.segment_name|escape:'html':'UTF-8'}</td><td>{$c.status|escape:'html':'UTF-8'}</td>
  <td>{$c.count_sent|escape:'html':'UTF-8'}</td><td>{$c.count_opened|escape:'html':'UTF-8'}{if $c.count_sent > 0} <small class="text-muted">({($c.count_opened/$c.count_sent*100)|string_format:"%.0f"}%)</small>{/if}</td>
  <td>{$c.count_clicked|escape:'html':'UTF-8'}{if $c.count_sent > 0} <small class="text-muted">({($c.count_clicked/$c.count_sent*100)|string_format:"%.0f"}%)</small>{/if}</td>
  <td>{$c.count_unsub|escape:'html':'UTF-8'}</td><td>{$c.count_skipped|escape:'html':'UTF-8'}</td><td>{$c.count_failed|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="10"><em>No campaigns have gone out yet.</em></td></tr>{/foreach}
</tbody></table>
<p class="text-muted">{$k.journeys_active|escape:'html':'UTF-8'} guests are part-way through a journey right now.</p>
</div>

<div class="tab-pane" id="c-loyalty">
<table class="table table-condensed"><thead><tr><th>Tier</th><th>Members</th><th>Points outstanding</th><th>Liability</th></tr></thead><tbody>
{foreach $liability as $l}<tr><td>{$l.tier|escape:'html':'UTF-8'}</td><td>{$l.members|escape:'html':'UTF-8'}</td><td>{$l.points|number_format:0|escape:'html':'UTF-8'}</td><td>{displayPrice price=$l.value}</td></tr>{foreachelse}<tr><td colspan="4"><em>Nobody is enrolled yet.</em></td></tr>{/foreach}
</tbody></table>
</div>

<div class="tab-pane" id="c-reviews">
<table class="table table-condensed"><thead><tr><th>Source</th><th>Reviews</th><th>Average</th><th>%</th><th>Responded</th><th>Response rate</th><th>Avg hours</th><th>Negative</th></tr></thead><tbody>
{foreach $by_source as $s}<tr><td>{$s.source|escape:'html':'UTF-8'}</td><td>{$s.reviews|escape:'html':'UTF-8'}</td><td>{$s.avg_rating|escape:'html':'UTF-8'} / {$s.scale|escape:'html':'UTF-8'}</td><td>{$s.pct|escape:'html':'UTF-8'}%</td><td>{$s.responded|escape:'html':'UTF-8'}</td><td>{$s.response_rate|escape:'html':'UTF-8'}%</td><td>{$s.avg_response_hours|escape:'html':'UTF-8'}</td><td>{$s.negative|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="8"><em>No reviews recorded.</em></td></tr>{/foreach}
</tbody></table>
{if $needs_reply}<h4>Needs a reply</h4><table class="table table-condensed"><tbody>
{foreach $needs_reply as $r}<tr class="{if $r.sentiment == 'negative'}danger{/if}"><td>{$r.review_date|escape:'html':'UTF-8'}</td><td>{$r.source|escape:'html':'UTF-8'}</td><td>{$r.rating|escape:'html':'UTF-8'}/{$r.rating_scale|escape:'html':'UTF-8'}</td><td>{$r.author|escape:'html':'UTF-8'}</td><td>{$r.title|truncate:60|escape:'html':'UTF-8'}</td><td><a class="btn btn-xs btn-default" href="{$reviews_url|escape:'html':'UTF-8'}&id_review={$r.id_pulse_crm_review|escape:'html':'UTF-8'}">Reply</a></td></tr>{/foreach}
</tbody></table>{/if}
</div>

<div class="tab-pane" id="c-sales">
<h4>Follow-ups due</h4><table class="table table-condensed"><tbody>
{foreach $follow_ups as $f}<tr class="{if $f.follow_up_at < $smarty.now|date_format:'%Y-%m-%d %H:%M:%S'}warning{/if}"><td>{$f.follow_up_at|date_format:"%d/%m"}</td><td>{$f.account|escape:'html':'UTF-8'}</td><td>{$f.type|escape:'html':'UTF-8'}</td><td>{$f.subject|escape:'html':'UTF-8'}</td><td>{$f.who|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td><em>No follow-ups outstanding.</em></td></tr>{/foreach}
</tbody></table>
</div>

</div></div></div>
