<div class="pulse-gp"><div class="panel"><h3><i class="icon-film"></i> Channels &amp; VOD</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#gp-chan">Channels ({$channels|count})</a></li><li><a data-toggle="tab" href="#gp-vodlist">Movies ({$vods|count})</a></li><li><a data-toggle="tab" href="#gp-radio">Radio &amp; apps</a></li><li><a data-toggle="tab" href="#gp-plays">Plays &amp; revenue</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="gp-chan">
<p class="help-block">The line-up the headend puts on the LAN. Multicast addresses look like <code>udp://@239.1.1.12:1234</code>; an HLS re-stream from the same headend (<code>http://10.0.0.9:8080/dstv/12.m3u8</code>) also works and is what the TV browser can actually decode without the Tizen player. Adult channels are hidden until the PIN is entered{if !$adult_pin_set} — <strong>no PIN is set, so they stay hidden</strong>{/if}.</p>
<table class="table table-condensed"><thead><tr><th>#</th><th>Logo</th><th>Name</th><th>Stream</th><th>Category</th><th>HD</th><th>Adult</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $channels as $c}<tr class="{if !$c.active}active{/if}"><td>{$c.number|escape:'html':'UTF-8'}</td><td>{if $c.logo}<img src="{if strpos($c.logo,'http')===0}{$c.logo}{else}{$upload_base|escape:'html':'UTF-8'}{$c.logo}{/if}" class="gp-thumb">{/if}</td><td>{$c.name|escape:'html':'UTF-8'}</td><td><small><code>{$c.url|escape:'html':'UTF-8'}</code></small></td><td>{$c.category|escape:'html':'UTF-8'}</td><td>{if $c.hd}✓{/if}</td><td>{if $c.adult}✓{/if}</td><td>{if $c.active}✓{else}—{/if}</td>
<td class="noprint"><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_channel={$c.id_pulse_gp_channel|escape:'html':'UTF-8'}">Edit</a> <form method="post" class="inline"><input type="hidden" name="id_channel" value="{$c.id_pulse_gp_channel|escape:'html':'UTF-8'}"><button name="deleteChannel" class="btn btn-xs btn-link" data-gp-confirm="Remove this channel?">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em>No channels — import the headend list below</em></td></tr>{/foreach}</tbody></table>

<div class="row"><div class="col-md-7"><h4>{if $channel}Edit channel {$channel.number|escape:'html':'UTF-8'}{else}Add a channel{/if}</h4>
<form method="post" enctype="multipart/form-data" class="form-inline"><input type="hidden" name="id_channel" value="{if $channel}{$channel.id_pulse_gp_channel|escape:'html':'UTF-8'}{/if}">
<input name="number" type="number" class="form-control" placeholder="#" style="width:70px" value="{if $channel}{$channel.number|escape:'html':'UTF-8'}{/if}" required>
<input name="name" class="form-control" placeholder="Channel name" value="{if $channel}{$channel.name|escape}{/if}" required>
<input name="url" class="form-control" style="width:280px" placeholder="udp://@239.1.1.12:1234" value="{if $channel}{$channel.url|escape}{/if}" required>
<select name="category" class="form-control">{foreach ['general','news','sport','movies','series','kids','music','documentary','religious','local','adult'] as $cat}<option value="{$cat|escape:'html':'UTF-8'}" {if $channel && $channel.category==$cat}selected{/if}>{$cat|escape:'html':'UTF-8'}</option>{/foreach}</select>
<input name="source" class="form-control" style="width:90px" placeholder="dstv" value="{if $channel}{$channel.source|escape}{else}dstv{/if}">
<input name="sort" type="number" class="form-control" style="width:70px" placeholder="sort" value="{if $channel}{$channel.sort|escape:'html':'UTF-8'}{/if}">
<label><input type="checkbox" name="hd" value="1" {if $channel && $channel.hd}checked{/if}> HD</label>
<label><input type="checkbox" name="adult" value="1" {if $channel && $channel.adult}checked{/if}> Adult</label>
<label><input type="checkbox" name="active" value="1" {if !$channel || $channel.active}checked{/if}> Active</label>
<input type="file" name="logo"> <button name="saveChannel" class="btn btn-primary">Save</button> {if $channel}<a class="btn btn-default" href="{$self_url}">New</a>{/if}</form></div>
<div class="col-md-5"><h4>Import from the headend</h4>
<form method="post"><textarea name="import" class="form-control" rows="5" placeholder="12,SuperSport 3,udp://@239.1.1.12:1234,sport
13,Africa Magic,udp://@239.1.1.13:1234,general
…or paste an M3U playlist"></textarea>
<button name="importChannels" class="btn btn-default" style="margin-top:6px">Import</button></form></div></div>
</div>

<div class="tab-pane" id="gp-vodlist">
<table class="table table-condensed"><thead><tr><th>Poster</th><th>Title</th><th>Category</th><th>Rating</th><th>Year</th><th>Min</th><th>Price</th><th>Adult</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $vods as $v}<tr class="{if !$v.active}active{/if}"><td>{if $v.poster}<img src="{if strpos($v.poster,'http')===0}{$v.poster}{else}{$upload_base|escape:'html':'UTF-8'}{$v.poster}{/if}" class="gp-thumb">{/if}</td><td>{$v.title|escape:'html':'UTF-8'}</td><td>{$v.category|escape:'html':'UTF-8'}</td><td>{$v.rating|escape:'html':'UTF-8'}</td><td>{$v.year|escape:'html':'UTF-8'}</td><td>{$v.duration_min|escape:'html':'UTF-8'}</td><td>{if $v.free}free{else}{displayPrice price=$v.price}{/if}</td><td>{if $v.adult}✓{/if}</td><td>{if $v.active}✓{else}—{/if}</td>
<td class="noprint"><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_vod={$v.id_pulse_gp_vod|escape:'html':'UTF-8'}">Edit</a> <form method="post" class="inline"><input type="hidden" name="id_vod" value="{$v.id_pulse_gp_vod|escape:'html':'UTF-8'}"><button name="deleteVod" class="btn btn-xs btn-link" data-gp-confirm="Remove this title?">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="10"><em>No titles</em></td></tr>{/foreach}</tbody></table>
<h4>{if $vod}Edit “{$vod.title|escape:'html':'UTF-8'}”{else}Add a title{/if}</h4>
<form method="post" enctype="multipart/form-data" class="form-inline"><input type="hidden" name="id_vod" value="{if $vod}{$vod.id_pulse_gp_vod|escape:'html':'UTF-8'}{/if}">
<input name="title" class="form-control" placeholder="Title" value="{if $vod}{$vod.title|escape}{/if}" required>
<input name="stream_url" class="form-control" style="width:260px" placeholder="http://10.0.0.9/vod/film.m3u8" value="{if $vod}{$vod.stream_url|escape}{/if}" required>
<input name="vcategory" class="form-control" style="width:110px" placeholder="movie" value="{if $vod}{$vod.category|escape}{else}movie{/if}">
<input name="rating" class="form-control" style="width:70px" placeholder="PG" value="{if $vod}{$vod.rating|escape}{else}PG{/if}">
<input name="year" type="number" class="form-control" style="width:90px" placeholder="Year" value="{if $vod}{$vod.year|escape:'html':'UTF-8'}{/if}">
<input name="duration_min" type="number" class="form-control" style="width:80px" placeholder="Min" value="{if $vod}{$vod.duration_min|escape:'html':'UTF-8'}{/if}">
<input name="language" class="form-control" style="width:110px" placeholder="English" value="{if $vod}{$vod.language|escape}{else}English{/if}">
<input name="price" class="form-control" style="width:100px" placeholder="₦ price" value="{if $vod}{$vod.price|escape:'html':'UTF-8'}{/if}">
<input name="vsort" type="number" class="form-control" style="width:70px" placeholder="sort" value="{if $vod}{$vod.sort|escape:'html':'UTF-8'}{/if}">
<label><input type="checkbox" name="free" value="1" {if !$vod || $vod.free}checked{/if}> Free</label>
<label><input type="checkbox" name="vadult" value="1" {if $vod && $vod.adult}checked{/if}> Adult</label>
<label><input type="checkbox" name="vactive" value="1" {if !$vod || $vod.active}checked{/if}> Active</label>
<input type="file" name="poster">
<input name="synopsis" class="form-control" style="width:100%;margin-top:6px" placeholder="Synopsis" value="{if $vod}{$vod.synopsis|escape}{/if}">
<button name="saveVod" class="btn btn-primary" style="margin-top:6px">Save title</button> {if $vod}<a class="btn btn-default" href="{$self_url}">New</a>{/if}</form>
<p class="help-block">A paid title posts to the guest folio with charge code <code>{$vod_code|escape:'html':'UTF-8'}</code> the moment it starts, and the same title re-started later the same day is not charged twice.</p>
</div>

<div class="tab-pane" id="gp-radio"><div class="row">
<div class="col-md-6"><h4>Radio streams</h4><form method="post">
<table class="table table-condensed"><tbody>
{foreach $radio as $r}<tr><td><input name="rname[]" class="form-control input-sm" value="{$r.name|escape}"></td><td><input name="rurl[]" class="form-control input-sm" value="{$r.url|escape}"></td><td><input name="rgenre[]" class="form-control input-sm" value="{$r.genre|escape}" style="width:110px"></td></tr>{/foreach}
<tr><td><input name="rname[]" class="form-control input-sm" placeholder="Wazobia FM"></td><td><input name="rurl[]" class="form-control input-sm" placeholder="https://stream…"></td><td><input name="rgenre[]" class="form-control input-sm" placeholder="Talk" style="width:110px"></td></tr>
</tbody></table><button name="saveRadio" class="btn btn-default">Save radio</button></form></div>
<div class="col-md-6"><h4>Games &amp; apps launcher</h4><form method="post">
<table class="table table-condensed"><tbody>
{foreach $apps as $a}<tr><td><input name="aname[]" class="form-control input-sm" value="{$a.name|escape}"></td><td><input name="aurl[]" class="form-control input-sm" value="{$a.url|escape}"></td><td><select name="atype[]" class="form-control input-sm"><option value="app" {if $a.type=='app'}selected{/if}>app</option><option value="game" {if $a.type=='game'}selected{/if}>game</option></select></td></tr>{/foreach}
<tr><td><input name="aname[]" class="form-control input-sm" placeholder="Sudoku"></td><td><input name="aurl[]" class="form-control input-sm" placeholder="https://…"></td><td><select name="atype[]" class="form-control input-sm"><option value="app">app</option><option value="game">game</option></select></td></tr>
</tbody></table><button name="saveApps" class="btn btn-default">Save apps</button></form></div>
</div></div>

<div class="tab-pane" id="gp-plays"><form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulseGuestPortalChannels"><input type="hidden" name="token" value="{$smarty.get.token|escape}"><input type="date" name="from" value="{$from|escape:'html':'UTF-8'}" class="form-control"> <input type="date" name="to" value="{$to|escape:'html':'UTF-8'}" class="form-control"> <button class="btn btn-default">Run</button></form>
<p><strong>{$revenue.n|escape:'html':'UTF-8'}</strong> paid play(s) &middot; {displayPrice price=$revenue.total} posted to folios {$from|escape:'html':'UTF-8'} → {$to|escape:'html':'UTF-8'}</p>
<table class="table table-condensed"><thead><tr><th>When</th><th>Room</th><th>Title</th><th>Price</th><th>Status</th><th>Folio line</th></tr></thead><tbody>
{foreach $plays as $p}<tr><td>{$p.date_add|date_format:"%d/%m %H:%M"}</td><td>{$p.room_num|escape:'html':'UTF-8'}</td><td>{$p.title|escape:'html':'UTF-8'}</td><td>{if $p.price>0}{displayPrice price=$p.price}{else}free{/if}</td><td>{$p.status|escape:'html':'UTF-8'}</td><td>{$p.posted_line|escape:'html':'UTF-8'}</td></tr>
{foreachelse}<tr><td colspan="6"><em>Nothing played in this period</em></td></tr>{/foreach}</tbody></table></div>

</div></div></div>
