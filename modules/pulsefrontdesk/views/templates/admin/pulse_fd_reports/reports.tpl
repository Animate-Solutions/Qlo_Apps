<div class="pulse-fd"><div class="panel"><h3><i class="icon-bar-chart"></i> Front Desk Reports</h3>
  <form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminPulseFdReports"><input type="hidden" name="token" value="{$smarty.get.token|escape}">
    <select name="report" class="form-control">{foreach $reports as $k=>$v}<option value="{$k}" {if $k==$report}selected{/if}>{$v}</option>{/foreach}</select>
    <input type="date" name="from" class="form-control" value="{$from}"> <input type="date" name="to" class="form-control" value="{$to}">
    <button class="btn btn-default">Run</button> <button class="btn btn-default" name="export" value="1"><i class="icon-download"></i> CSV</button></form>
  <h4>{$reports[$report]} &middot; {$from} → {$to}</h4>
  <table class="table table-striped table-condensed"><thead><tr>{foreach $columns as $c}<th>{$c|replace:'_':' '}</th>{/foreach}</tr></thead><tbody>
  {foreach $rows as $r}<tr>{foreach $r as $v}<td>{$v}</td>{/foreach}</tr>{foreachelse}<tr><td colspan="{$columns|count|max:1}"><em>No data for this range — has night audit been run?</em></td></tr>{/foreach}</tbody></table>
</div></div>
