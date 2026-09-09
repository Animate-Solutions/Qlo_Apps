<div class="pulse-pr"><div class="panel"><h3><i class="icon-bar-chart"></i> Payroll reports</h3>
<form method="get" class="form-inline pr-filter"><input type="hidden" name="controller" value="AdminPulsePrReports"><input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}">
<select name="r" class="form-control input-sm">{foreach $reports as $k => $v}<option value="{$k}" {if $r==$k}selected{/if}>{$v}</option>{/foreach}</select>
<input name="period" class="form-control input-sm" value="{$p.period}" placeholder="2026-08" style="width:110px">
<input name="year" type="number" class="form-control input-sm" value="{$p.year}" style="width:90px">
<select name="department" class="form-control input-sm"><option value="">All departments</option>{foreach $departments as $d}<option value="{$d.department}" {if $p.department==$d.department}selected{/if}>{$d.department}</option>{/foreach}</select>
<select name="id_run" class="form-control input-sm"><option value="">Latest run for the period</option>{foreach $runs as $ru}<option value="{$ru.id_pulse_pr_run}" {if $p.id_run==$ru.id_pulse_pr_run}selected{/if}>{$ru.run_no} — {$ru.period} ({$ru.run_type|replace:'_':' '})</option>{/foreach}</select>
<button class="btn btn-default btn-sm">Run</button>
<a class="btn btn-default btn-sm" href="{$self_url}&r={$r}&period={$p.period}&year={$p.year}&department={$p.department|escape:'url'}&id_run={$p.id_run}&export=1">Export CSV</a>
</form>

{if $r=='register'}
  <h4>Payroll register</h4>
  {if $data.rows}
  <div class="table-responsive"><table class="table table-condensed pr-register"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Days</th>
  {foreach $data.columns as $c}<th class="{if $c.type=='deduction'}pr-ded{/if}">{$c.element_code}</th>{/foreach}
  <th>Gross</th><th>Deductions</th><th>Net</th><th>Employer cost</th></tr></thead><tbody>
  {foreach $data.rows as $row}<tr><td>{$row.staff_no}</td><td>{$row.name}</td><td>{$row.department}</td><td>{$row.days|floatval}</td>
  {foreach $data.columns as $code => $c}<td class="text-right {if $c.type=='deduction'}pr-ded{/if}">{if $row[$code]}{$row[$code]|number_format:2}{/if}</td>{/foreach}
  <td class="text-right">{$row.gross|number_format:2}</td><td class="text-right">{$row.deductions|number_format:2}</td><td class="text-right"><strong>{$row.net|number_format:2}</strong></td><td class="text-right">{$row.employer_cost|number_format:2}</td></tr>{/foreach}
  <tr class="active"><td colspan="4"><strong>TOTAL</strong></td>
  {foreach $data.columns as $code => $c}<td class="text-right"><strong>{if $data.totals[$code]}{$data.totals[$code]|number_format:2}{/if}</strong></td>{/foreach}
  <td class="text-right"><strong>{$data.totals.gross|number_format:2}</strong></td><td class="text-right"><strong>{$data.totals.deductions|number_format:2}</strong></td>
  <td class="text-right"><strong>{$data.totals.net|number_format:2}</strong></td><td class="text-right"><strong>{$data.totals.employer_cost|number_format:2}</strong></td></tr>
  </tbody></table></div>
  {else}<p><em>Nothing calculated for this run.</em></p>{/if}

{elseif $r=='headcount'}
  <h4>Headcount reconciliation &mdash; {$data.period}</h4>
  <table class="table table-condensed" style="max-width:520px"><tbody>
  <tr><td>Employees paid</td><td class="text-right"><strong>{$data.paid}</strong></td></tr>
  <tr><td>On the roster (monthly, not casual)</td><td class="text-right"><strong>{$data.roster}</strong></td></tr>
  <tr class="{if $data.difference != 0}warning{/if}"><td>Difference</td><td class="text-right"><strong>{$data.difference}</strong></td></tr>
  <tr><td>Casual engagements paid weekly</td><td class="text-right">{$data.casual_engagements}</td></tr>
  </tbody></table>
  {if $data.not_paid}<h5>On the roster but not in this period's payroll</h5>
  <table class="table table-condensed"><thead><tr><th>Staff no</th><th>Name</th><th>Dept</th><th>Status</th><th>Why</th></tr></thead><tbody>
  {foreach $data.not_paid as $x}<tr class="warning"><td>{$x.staff_no}</td><td>{$x.name}</td><td>{$x.department}</td><td>{$x.status}</td><td>{if $x.on_hold}on hold — {$x.hold_reason}{else}not picked up by any run{/if}</td></tr>{/foreach}
  </tbody></table>{/if}
  <div class="row"><div class="col-md-6">{if $data.joiners}<h5>Joiners</h5><table class="table table-condensed"><tbody>{foreach $data.joiners as $j}<tr><td>{$j.staff_no}</td><td>{$j.name}</td><td>{$j.department}</td><td>{$j.hire_date}</td></tr>{/foreach}</tbody></table>{/if}</div>
  <div class="col-md-6">{if $data.leavers}<h5>Leavers</h5><table class="table table-condensed"><tbody>{foreach $data.leavers as $j}<tr><td>{$j.staff_no}</td><td>{$j.name}</td><td>{$j.department}</td><td>{$j.exit_date}</td></tr>{/foreach}</tbody></table>{/if}</div></div>

{elseif $r=='kpi'}
  <h4>Labour cost &mdash; {$data.period}</h4>
  <div class="row pr-tiles">
  <div class="col-md-3"><div class="pr-tile"><span class="pr-n">{displayPrice price=$data.total_labour_cost}</span><span class="pr-l">total labour cost</span></div></div>
  <div class="col-md-3"><div class="pr-tile"><span class="pr-n">{$data.occupied_room_nights}</span><span class="pr-l">occupied room nights</span></div></div>
  <div class="col-md-3"><div class="pr-tile"><span class="pr-n">{if $data.cost_per_occupied_room === null}n/a{else}{displayPrice price=$data.cost_per_occupied_room}{/if}</span><span class="pr-l">cost per occupied room</span></div></div>
  <div class="col-md-3"><div class="pr-tile"><span class="pr-n">{if $data.labour_pct_of_revenue === null}n/a{else}{$data.labour_pct_of_revenue}%{/if}</span><span class="pr-l">labour as a share of revenue</span></div></div>
  </div>
  {if !$fd}<div class="alert alert-info">Front Desk is not installed, so occupancy is unknown and cost per occupied room cannot be calculated.</div>{/if}
  {if !$acc}<div class="alert alert-info">Pulse Accounts is not installed, so revenue is unknown and the labour percentage cannot be calculated.</div>{/if}
  <table class="table table-condensed"><tbody>
  <tr><td>Monthly payroll (gross + employer contributions)</td><td class="text-right">{displayPrice price=$data.payroll_cost}</td></tr>
  <tr><td>Casual and weekly pay</td><td class="text-right">{displayPrice price=$data.casual_cost}</td></tr>
  <tr class="active"><td><strong>Total</strong></td><td class="text-right"><strong>{displayPrice price=$data.total_labour_cost}</strong></td></tr>
  </tbody></table>
  <h5>By department</h5>
  <table class="table table-condensed"><thead><tr><th>Department</th><th>Staff</th><th>Gross</th><th>Employer cost</th><th>Total</th></tr></thead><tbody>
  {foreach $data.departments as $d}<tr><td>{$d.department}</td><td>{$d.headcount}</td><td>{displayPrice price=$d.gross}</td><td>{displayPrice price=$d.employer_cost}</td><td><strong>{displayPrice price=$d.total_cost}</strong></td></tr>{/foreach}
  </tbody></table>

{elseif $r=='bank'}
  <h4>Bank payment summary</h4>
  <table class="table table-condensed"><thead><tr><th>Bank</th><th>Code</th><th>Beneficiaries</th><th class="text-right">Amount</th></tr></thead><tbody>
  {foreach $data.by_bank as $b}<tr><td>{$b.bank_name}</td><td>{$b.bank_code}</td><td>{$b.n}</td><td class="text-right">{displayPrice price=$b.total}</td></tr>{/foreach}
  <tr class="warning"><td>Cash / cheque</td><td>—</td><td>{$data.cash.n}</td><td class="text-right">{displayPrice price=$data.cash.total}</td></tr>
  <tr class="active"><td colspan="3"><strong>Total net</strong></td><td class="text-right"><strong>{displayPrice price=$data.total}</strong></td></tr>
  </tbody></table>

{elseif $data}
  <h4>{$reports[$r]}</h4>
  <div class="table-responsive"><table class="table table-condensed"><thead><tr>{foreach $data[0] as $k => $v}<th>{$k|replace:'_':' '}</th>{/foreach}</tr></thead><tbody>
  {foreach $data as $row}<tr>{foreach $row as $v}<td>{$v}</td>{/foreach}</tr>{/foreach}
  </tbody></table></div>
{else}
  <p><em>Nothing to show for this period.</em></p>
{/if}
</div></div>
