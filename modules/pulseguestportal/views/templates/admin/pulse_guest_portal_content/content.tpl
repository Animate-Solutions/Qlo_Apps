<div class="pulse-gp"><div class="panel"><h3><i class="icon-book"></i> Portal content</h3>
<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#gp-pages">Directory ({$pages|count})</a></li><li><a data-toggle="tab" href="#gp-promos">Promotions ({$promos|count})</a></li><li><a data-toggle="tab" href="#gp-welcome">Welcome screen</a></li><li><a data-toggle="tab" href="#gp-allerg">Allergen notes</a></li></ul>
<div class="tab-content">

<div class="tab-pane active" id="gp-pages">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Category</th><th>Title ({$default_lang|escape:'html':'UTF-8'})</th><th>Hours</th><th>Ext</th><th>Languages</th><th>Sort</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $pages as $p}<tr><td><code>{$p.code|escape:'html':'UTF-8'}</code></td><td>{$p.category|escape:'html':'UTF-8'}</td><td>{if $p.image}<img src="{$upload_base|escape:'html':'UTF-8'}{$p.image}" class="gp-thumb"> {/if}{$p.title|escape:'html':'UTF-8'}</td><td>{$p.opens|escape:'html':'UTF-8'}</td><td>{$p.extension|escape:'html':'UTF-8'}</td><td><small>{$p.langs|escape:'html':'UTF-8'}</small></td><td>{$p.sort|escape:'html':'UTF-8'}</td><td>{if $p.active}✓{else}—{/if}</td>
<td class="noprint"><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_page={$p.id_pulse_gp_page|escape:'html':'UTF-8'}">Edit</a> <form method="post" class="inline"><input type="hidden" name="id_page" value="{$p.id_pulse_gp_page|escape:'html':'UTF-8'}"><button name="deletePage" class="btn btn-xs btn-link" data-gp-confirm="Delete this page?">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="9"><em>No directory pages yet — the seed script creates a full Port Harcourt set</em></td></tr>{/foreach}</tbody></table>

<h4>{if $page}Edit “{$page.code|escape:'html':'UTF-8'}”{else}New directory page{/if}</h4>
<form method="post" enctype="multipart/form-data" class="form-horizontal">
<input type="hidden" name="id_page" value="{if $page}{$page.id_pulse_gp_page|escape:'html':'UTF-8'}{/if}">
<div class="row"><div class="col-md-4">
<div class="form-group"><label class="col-sm-4">Code</label><div class="col-sm-8"><input name="code" class="form-control" value="{if $page}{$page.code|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Category</label><div class="col-sm-8"><select name="category" class="form-control">{foreach $categories as $k => $v}<option value="{$k|escape:'html':'UTF-8'}" {if $page && $page.category==$k}selected{/if}>{$v|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Icon</label><div class="col-sm-8"><input name="icon" class="form-control" value="{if $page}{$page.icon|escape}{/if}" placeholder="spa, gym, pool…"></div></div>
<div class="form-group"><label class="col-sm-4">Phone / ext</label><div class="col-sm-8"><input name="phone" class="form-control" value="{if $page}{$page.phone|escape}{/if}"><input name="extension" class="form-control" value="{if $page}{$page.extension|escape}{/if}" placeholder="Room-phone extension"></div></div>
<div class="form-group"><label class="col-sm-4">Hours / location</label><div class="col-sm-8"><input name="opens" class="form-control" value="{if $page}{$page.opens|escape}{/if}" placeholder="06:30 – 22:00"><input name="location" class="form-control" value="{if $page}{$page.location|escape}{/if}" placeholder="Ground floor"></div></div>
<div class="form-group"><label class="col-sm-4">Image</label><div class="col-sm-8"><input type="file" name="image">{if $page && $page.image}<br><img src="{$upload_base|escape:'html':'UTF-8'}{$page.image}" class="gp-thumb">{/if}</div></div>
<div class="form-group"><label class="col-sm-4">Room types</label><div class="col-sm-8"><select name="room_types[]" class="form-control" multiple size="4">{foreach $room_types as $rt}<option value="{$rt.id_product|escape:'html':'UTF-8'}" {if $page && in_array($rt.id_product, $page.room_type_ids)}selected{/if}>{$rt.name|escape:'html':'UTF-8'}</option>{/foreach}</select><span class="help-block">Leave empty for every room</span></div></div>
<div class="form-group"><label class="col-sm-4">Sort / active</label><div class="col-sm-8"><input name="sort" class="form-control" value="{if $page}{$page.sort|escape:'html':'UTF-8'}{else}0{/if}" style="width:80px;display:inline"> <label><input type="checkbox" name="active" value="1" {if !$page || $page.active}checked{/if}> Active</label></div></div>
</div>
<div class="col-md-8">{foreach $langs as $code => $name}
<fieldset style="border-top:1px solid #eee;padding-top:8px"><legend style="font-size:14px">{$name|escape:'html':'UTF-8'} ({$code|escape:'html':'UTF-8'})</legend>
<input name="title_{$code|escape:'html':'UTF-8'}" class="form-control" placeholder="Title" value="{if $page && isset($page.lang[$code])}{$page.lang[$code].title|escape}{/if}">
<input name="summary_{$code|escape:'html':'UTF-8'}" class="form-control" placeholder="One-line summary" value="{if $page && isset($page.lang[$code])}{$page.lang[$code].summary|escape}{/if}">
<textarea name="body_{$code|escape:'html':'UTF-8'}" class="form-control" rows="3" placeholder="Body">{if $page && isset($page.lang[$code])}{$page.lang[$code].body|escape}{/if}</textarea>
</fieldset>{/foreach}
<button name="savePage" class="btn btn-primary" style="margin-top:10px">Save page</button> {if $page}<a class="btn btn-default" href="{$self_url}">New page</a>{/if}</div></div></form>
</div>

<div class="tab-pane" id="gp-promos">
<table class="table table-condensed"><thead><tr><th>Code</th><th>Title</th><th>Where</th><th>Hours</th><th>Dates</th><th>Sort</th><th>Active</th><th></th></tr></thead><tbody>
{foreach $promos as $p}<tr><td><code>{$p.code|escape:'html':'UTF-8'}</code></td><td>{if $p.image}<img src="{$upload_base|escape:'html':'UTF-8'}{$p.image}" class="gp-thumb"> {/if}{$p.title|escape:'html':'UTF-8'}</td><td>{$p.placement|escape:'html':'UTF-8'}{if $p.target} → {$p.target|escape:'html':'UTF-8'}{/if}</td><td>{$p.day_start|truncate:5:''|escape:'html':'UTF-8'} – {$p.day_end|truncate:5:''|escape:'html':'UTF-8'}</td><td>{$p.date_from|escape:'html':'UTF-8'}{if $p.date_to} → {$p.date_to|escape:'html':'UTF-8'}{/if}</td><td>{$p.sort|escape:'html':'UTF-8'}</td><td>{if $p.active}✓{else}—{/if}</td>
<td class="noprint"><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_promo={$p.id_pulse_gp_promo|escape:'html':'UTF-8'}">Edit</a> <form method="post" class="inline"><input type="hidden" name="id_promo" value="{$p.id_pulse_gp_promo|escape:'html':'UTF-8'}"><button name="deletePromo" class="btn btn-xs btn-link" data-gp-confirm="Delete this promotion?">✕</button></form></td></tr>
{foreachelse}<tr><td colspan="8"><em>No promotions</em></td></tr>{/foreach}</tbody></table>

<h4>{if $promo}Edit “{$promo.code|escape:'html':'UTF-8'}”{else}New promotion{/if}</h4>
<form method="post" enctype="multipart/form-data" class="form-horizontal"><input type="hidden" name="id_promo" value="{if $promo}{$promo.id_pulse_gp_promo|escape:'html':'UTF-8'}{/if}">
<div class="row"><div class="col-md-4">
<div class="form-group"><label class="col-sm-4">Code</label><div class="col-sm-8"><input name="pcode" class="form-control" value="{if $promo}{$promo.code|escape}{/if}" required></div></div>
<div class="form-group"><label class="col-sm-4">Placement</label><div class="col-sm-8"><select name="placement" class="form-control">{foreach ['home','dining','entertainment','directory','checkout'] as $pl}<option value="{$pl|escape:'html':'UTF-8'}" {if $promo && $promo.placement==$pl}selected{/if}>{$pl|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Opens section</label><div class="col-sm-8"><input name="target" class="form-control" value="{if $promo}{$promo.target|escape}{/if}" placeholder="dining, directory, vod…"></div></div>
<div class="form-group"><label class="col-sm-4">Shown between</label><div class="col-sm-8"><input name="day_start" class="form-control" value="{if $promo}{$promo.day_start|escape:'html':'UTF-8'}{else}00:00:00{/if}" style="width:110px;display:inline"> <input name="day_end" class="form-control" value="{if $promo}{$promo.day_end|escape:'html':'UTF-8'}{else}23:59:59{/if}" style="width:110px;display:inline"></div></div>
<div class="form-group"><label class="col-sm-4">Dates</label><div class="col-sm-8"><input type="date" name="date_from" class="form-control" value="{if $promo}{$promo.date_from|escape:'html':'UTF-8'}{/if}" style="width:150px;display:inline"> <input type="date" name="date_to" class="form-control" value="{if $promo}{$promo.date_to|escape:'html':'UTF-8'}{/if}" style="width:150px;display:inline"></div></div>
<div class="form-group"><label class="col-sm-4">Image</label><div class="col-sm-8"><input type="file" name="pimage">{if $promo && $promo.image}<br><img src="{$upload_base|escape:'html':'UTF-8'}{$promo.image}" class="gp-thumb">{/if}</div></div>
<div class="form-group"><label class="col-sm-4">Room types</label><div class="col-sm-8"><select name="proom_types[]" class="form-control" multiple size="4">{foreach $room_types as $rt}<option value="{$rt.id_product|escape:'html':'UTF-8'}" {if $promo && in_array($rt.id_product, $promo.room_type_ids)}selected{/if}>{$rt.name|escape:'html':'UTF-8'}</option>{/foreach}</select></div></div>
<div class="form-group"><label class="col-sm-4">Sort / active</label><div class="col-sm-8"><input name="psort" class="form-control" value="{if $promo}{$promo.sort|escape:'html':'UTF-8'}{else}0{/if}" style="width:80px;display:inline"> <label><input type="checkbox" name="pactive" value="1" {if !$promo || $promo.active}checked{/if}> Active</label></div></div>
</div>
<div class="col-md-8">{foreach $langs as $code => $name}
<fieldset style="border-top:1px solid #eee;padding-top:8px"><legend style="font-size:14px">{$name|escape:'html':'UTF-8'} ({$code|escape:'html':'UTF-8'})</legend>
<input name="ptitle_{$code|escape:'html':'UTF-8'}" class="form-control" placeholder="Title" value="{if $promo && isset($promo.lang[$code])}{$promo.lang[$code].title|escape}{/if}">
<input name="pbody_{$code|escape:'html':'UTF-8'}" class="form-control" placeholder="One line" value="{if $promo && isset($promo.lang[$code])}{$promo.lang[$code].body|escape}{/if}">
<input name="pcta_{$code|escape:'html':'UTF-8'}" class="form-control" placeholder="Button text" value="{if $promo && isset($promo.lang[$code])}{$promo.lang[$code].cta|escape}{/if}">
</fieldset>{/foreach}
<button name="savePromo" class="btn btn-primary" style="margin-top:10px">Save promotion</button> {if $promo}<a class="btn btn-default" href="{$self_url}">New promotion</a>{/if}</div></div></form>
</div>

<div class="tab-pane" id="gp-welcome"><form method="post">
<p class="help-block">Placeholders: <code>{literal}{guest}{/literal}</code> <code>{literal}{room}{/literal}</code> <code>{literal}{hotel}{/literal}</code>. A language with no text falls back to the default one.</p>
{foreach $langs as $code => $name}<div class="form-group"><label>{$name|escape:'html':'UTF-8'} ({$code|escape:'html':'UTF-8'})</label><textarea name="welcome_{$code|escape:'html':'UTF-8'}" class="form-control" rows="3">{$welcome[$code]|escape}</textarea></div>{/foreach}
<button name="saveWelcome" class="btn btn-primary">Save welcome text</button></form></div>

<div class="tab-pane" id="gp-allerg"><form method="post">
<p class="help-block">Shown on the dining screen under the dish name. Keep it short: “contains peanuts”, “fish”, “dairy”.</p>
<table class="table table-condensed"><thead><tr><th>Dish</th><th>Allergen note</th></tr></thead><tbody>
{foreach $pos_items as $i}<tr><td>{$i.name|escape:'html':'UTF-8'}</td><td><input name="allergen[{$i.id|escape:'html':'UTF-8'}]" class="form-control input-sm" value="{if isset($allergens[$i.id])}{$allergens[$i.id]|escape}{/if}"></td></tr>
{foreachelse}<tr><td colspan="2"><em>Pulse POS is not installed — there is no menu to annotate</em></td></tr>{/foreach}</tbody></table>
{if $pos_items}<button name="saveAllergens" class="btn btn-primary">Save allergen notes</button>{/if}</form></div>

</div></div></div>
