<div class="pulse-hotel-picker">
  <div class="panel">
    <h3><i class="icon-building"></i> Which hotel are you working in?</h3>
    <p class="text-muted">Signed in as <strong>{$employee_name|escape:'html':'UTF-8'}</strong>. Everything you see and
      everything you post belongs to the hotel you choose here. Business date {$business_date|escape:'html':'UTF-8'}.
    </p>
    <form method="post">
      <input type="hidden" name="back" value="{$back|escape:'html':'UTF-8'}">
      <div class="ph-grid">
        {foreach $hotels as $h}
          <button type="submit" name="chooseHotel" value="1" class="ph-card{if $current == $h.id_hotel} ph-current{/if}">
            <input type="hidden" name="id_hotel" value="{$h.id_hotel|intval}">
            <span class="ph-card-name">{$h.hotel_name|escape:'html':'UTF-8'}</span>
            {if $current == $h.id_hotel}<span class="ph-badge">current</span>{/if}
            {if $h.is_default}<span class="ph-badge ph-default">your default</span>{/if}
          </button>
        {/foreach}
      </div>
    </form>
    <form method="post" class="ph-signout"><button name="signOut" class="btn btn-default btn-sm">Sign out</button>
    </form>
  </div>
</div>
{literal}<script>
    // Each card is its own submit button carrying its own hotel id; make the whole card clickable.
    document.addEventListener('click', function(e) {
      var c = e.target.closest ? e.target.closest('.ph-card') : null;
      if (c && e.target !== c) { c.click(); }
    });
</script>{/literal}