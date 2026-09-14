<div class="pulse-hotel-switch">
  <i class="icon-building"></i>
  {if $pulse_hotel_current}<span class="ph-name">{$pulse_hotel_current.hotel_name|escape:'html':'UTF-8'}</span>
  {else}<span class="ph-name ph-none">No hotel selected</span>
  {/if}
  {if $pulse_hotel_may_switch}
    <form method="post" action="{$pulse_hotel_switch_url|escape:'html':'UTF-8'}" class="ph-form">
      <input type="hidden" name="token" value="{$pulse_hotel_token|escape:'html':'UTF-8'}">
      <select name="id_hotel" class="ph-select" onchange="this.form.submit()">
        {foreach $pulse_hotel_list as $h}
          <option value="{$h.id_hotel|intval}" {if $pulse_hotel_current && $h.id_hotel == $pulse_hotel_current.id_hotel}
            selected{/if}>{$h.hotel_name|escape:'html':'UTF-8'}</option>
        {/foreach}
      </select>
      <input type="hidden" name="chooseHotel" value="1">
      <noscript><button type="submit" class="btn btn-xs btn-default">Go</button></noscript>
    </form>
  {/if}
</div>