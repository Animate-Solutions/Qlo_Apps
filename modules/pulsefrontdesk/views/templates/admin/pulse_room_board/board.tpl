<div class="pulse-fd" id="pulse-board" data-ajax="{$ajax_url|escape:'html':'UTF-8'}" data-folio="{$folio_url|escape:'html':'UTF-8'}" data-date="{$business_date}">
<div class="panel">
  <h3><i class="icon-th"></i> Room Board &mdash; {$business_date}
    <span class="pull-right">
      {if $hotels|count > 1}<select id="board-hotel" class="form-control input-sm inline"><option value="">All properties</option>{foreach $hotels as $h}<option value="{$h.id}">{$h.hotel_name}</option>{/foreach}</select>{/if}
      <button class="btn btn-default btn-sm" id="board-refresh"><i class="icon-refresh"></i></button>
    </span></h3>
  <div class="legend">
    <span class="sw hk-vacant_clean"></span>Vacant clean <span class="sw hk-vacant_inspected"></span>Inspected <span class="sw hk-vacant_dirty"></span>Vacant dirty
    <span class="sw hk-occupied_clean"></span>Occupied <span class="sw hk-occupied_dirty"></span>Occupied dirty <span class="sw hk-out_of_order"></span>OOO/OOS
    &nbsp;&nbsp; <i class="icon-sign-in"></i> arriving &nbsp; <i class="icon-sign-out"></i> departing &nbsp; <i class="icon-star"></i> VIP &nbsp; <i class="icon-wrench"></i> open HK task
  </div>
  <div id="board-grid"><p><i class="icon-spinner icon-spin"></i> Loading rooms…</p></div>
</div>
{include file="./modals.tpl"}
</div>
