<div class="pulse-hotel-access">
  <div class="row">
    <div class="col-md-7">
      <div class="panel">
        <h3><i class="icon-users"></i> Who may work in which hotel</h3>
        <table class="table table-condensed">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Role</th>
              <th>Hotels</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {foreach $employees as $e}
              <tr class="{if !$e.active}text-muted{elseif !$e.hotels}danger{/if}">
                <td>{$e.firstname|escape:'html':'UTF-8'} {$e.lastname|escape:'html':'UTF-8'}<br><small
                    class="text-muted">{$e.email|escape:'html':'UTF-8'}</small></td>
                <td>{$e.profile|escape:'html':'UTF-8'}</td>
                <td>{if $e.hotels}{$e.hotels|intval}{else}<strong>none — cannot sign in</strong>{/if}</td>
                <td><a class="btn btn-xs btn-default" href="{$self_url}&amp;id_employee={$e.id_employee|intval}">Edit</a>
                </td>
              </tr>
            {foreachelse}<tr>
                <td colspan="4"><em>No employees</em></td>
            </tr>{/foreach}
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-md-5">
      {if $edit_employee}
        <div class="panel">
          <h3>{$edit_employee->firstname|escape:'html':'UTF-8'} {$edit_employee->lastname|escape:'html':'UTF-8'}</h3>
          <form method="post">
            <input type="hidden" name="id_employee" value="{$edit_employee->id|intval}">
            <table class="table table-condensed">
              <thead>
                <tr>
                  <th>Hotel</th>
                  <th>Access</th>
                  <th>Default</th>
                </tr>
              </thead>
              <tbody>
                {foreach $hotels as $h}
                  {assign var=has value=false}{assign var=isdef value=false}
                  {foreach $edit_access as $a}
                    {if $a.id_hotel == $h.id_hotel}
                      {assign var=has value=true}
                      {if $a.is_default}
                        {assign var=isdef value=true}
                      {/if}
                    {/if}
                  {/foreach}
                  <tr class="{if !$h.active}text-muted{/if}">
                    <td>{$h.hotel_name|escape:'html':'UTF-8'}{if !$h.active} <small>(inactive)</small>{/if}</td>
                    <td><input type="checkbox" name="hotels[]" value="{$h.id_hotel|intval}" {if $has} checked{/if}></td>
                    <td><input type="radio" name="default_hotel" value="{$h.id_hotel|intval}" {if $isdef} checked{/if}></td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
            <label><input type="checkbox" name="can_switch" value="1" checked> May switch hotel during a session</label>
            <p class="help-block">Unticking this pins the person to their default hotel for the whole session — useful for
              a front-desk clerk who should never be posting into another property by accident.</p>
            <button name="saveAccess" class="btn btn-primary">Save access</button>
            <a class="btn btn-default" href="{$self_url}">Cancel</a>
          </form>
        </div>
      {else}
        <div class="panel">
          <h3>Editing</h3>
          <p class="text-muted">Pick an employee on the left to grant or revoke hotels.</p>
          <h4>Fallback</h4>
          <form method="post" class="form-inline">
            <select name="fallback" class="form-control input-sm">
              <option value="1" {if $profile_fallback} selected{/if}>On — honour the QloApps role→hotel table where no
                grant exists</option>
              <option value="0" {if !$profile_fallback} selected{/if}>Off — an employee with no explicit grant cannot sign
                in</option>
            </select>
            <button name="setFallback" class="btn btn-default btn-sm">Save</button>
          </form>
          <p class="help-block">Leave this on while you migrate, then turn it off so access is explicit.</p>
        </div>
      {/if}
    </div>
  </div>

  {if $unseeded}
    <div class="panel">
      <h3><i class="icon-warning-sign"></i> Properties that are not set up yet</h3>
      <p>Operations are separate per property, so each one needs its own charge codes, chart of accounts,
        menu and pay elements. Until a property has them it cannot take a booking or post a charge.
        Copy the setup across from a property that is already running; nothing transactional comes with it —
        no folios, no journals, no staff, no guests.</p>
      <table class="table table-condensed">
        <thead>
          <tr>
            <th>Property</th>
            <th>Charge codes</th>
            <th>Accounts</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {foreach $unseeded as $u}
            <tr>
              <td>{$u.hotel_name|default:"Hotel `$u.id`"|escape:'html':'UTF-8'}</td>
              <td>{$u.charge_codes|intval}</td>
              <td>{$u.accounts|intval}</td>
              <td>
                <form method="post" class="form-inline">
                  <input type="hidden" name="target_hotel" value="{$u.id|intval}" />
                  <select name="source_hotel" class="form-control input-sm">
                    {foreach $hotels as $h}
                      {if $h.id_hotel != $u.id}
                        <option value="{$h.id_hotel|intval}">copy from
                          {$h.hotel_name|default:"Hotel `$h.id_hotel`"|escape:'html':'UTF-8'}</option>
                      {/if}
                    {/foreach}
                  </select>
                  <button name="seedPreview" value="1" class="btn btn-default btn-sm">Show me what it would copy</button>
                  <button name="seedHotel" value="1" class="btn btn-primary btn-sm"
                    onclick="return confirm('Copy the setup into this property?');">Copy</button>
                </form>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>

      {if $seed_preview}
        <h4>What would be copied into property {$seed_preview.target|intval} from property {$seed_preview.source|intval}
        </h4>
        <table class="table table-condensed">
          <thead>
            <tr>
              <th>Table</th>
              <th>Rows</th>
            </tr>
          </thead>
          <tbody>{foreach $seed_preview.tables as $t => $n}<tr>
                <td>{$t|escape:'html':'UTF-8'}</td>
                <td>{$n|intval}</td>
            </tr>{/foreach}</tbody>
        </table>
      {/if}
    </div>
  {/if}

  <div class="panel">
    <h3><i class="icon-time"></i> Recent hotel activity</h3>
    <table class="table table-condensed">
      <thead>
        <tr>
          <th>When</th>
          <th>Who</th>
          <th>Event</th>
          <th>Hotel</th>
          <th>Screen</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        {foreach $activity as $a}
          <tr class="{if $a.event == 'refused' || $a.event == 'no_access'}danger{/if}">
            <td>{$a.date_add|escape:'html':'UTF-8'}</td>
            <td>{$a.who|escape:'html':'UTF-8'}</td>
            <td>{$a.event|escape:'html':'UTF-8'}</td>
            <td>{$a.hotel_name|default:'—'|escape:'html':'UTF-8'}</td>
            <td>{$a.controller|escape:'html':'UTF-8'}</td>
            <td>{$a.detail|escape:'html':'UTF-8'}</td>
          </tr>
        {foreachelse}<tr>
            <td colspan="6"><em>Nothing yet</em></td>
        </tr>{/foreach}
      </tbody>
    </table>
  </div>
</div>