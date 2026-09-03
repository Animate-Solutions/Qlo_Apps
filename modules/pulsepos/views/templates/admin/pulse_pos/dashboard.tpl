<div class="panel">
  <h3><i class="icon-cogs"></i> {$module_name|escape:'html':'UTF-8'} &mdash; benchmark: {$benchmark|escape:'html':'UTF-8'}</h3>
  <p class="alert alert-info">Scaffold installed. Feature parity targets:</p>
  <ul>
  {foreach from=$parity item=p}<li>{$p|escape:'html':'UTF-8'}</li>{/foreach}
  </ul>
</div>
