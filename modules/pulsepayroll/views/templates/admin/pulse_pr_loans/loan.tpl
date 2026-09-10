<div class="pulse-pr"><div class="panel">
<h3><i class="icon-credit-card"></i> {$l.loan_no} &mdash; {$l.employee_name} <small>{$l.staff_no}, {$l.department}</small>
<span class="pr-status pr-{$l.status} pull-right">{$l.status|replace:'_':' '}</span></h3>
<a class="btn btn-xs btn-default" href="{$self_url}">Back to the loans</a>
<div class="row pr-tiles">
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$l.principal}</span><span class="pr-l">principal</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$l.interest_amount}</span><span class="pr-l">interest at {$l.interest_pct|floatval}%</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$l.total_repayable}</span><span class="pr-l">total repayable</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$l.recovered}</span><span class="pr-l">recovered</span></div></div>
<div class="col-md-2"><div class="pr-tile {if $l.balance > 0}warning{/if}"><span class="pr-n">{displayPrice price=$l.balance}</span><span class="pr-l">balance</span></div></div>
<div class="col-md-2"><div class="pr-tile"><span class="pr-n">{displayPrice price=$l.instalment_amount}</span><span class="pr-l">per period &times; {$l.instalments}</span></div></div>
</div>
<p><strong>Purpose.</strong> {$l.purpose|default:'—'} {if $l.note}<br><strong>Note.</strong> {$l.note}{/if}</p>
<form method="post" class="form-inline pr-actions"><input type="hidden" name="id_loan_a" value="{$l.id_pulse_pr_loan}"><input type="hidden" name="id_loan" value="{$l.id_pulse_pr_loan}">
<select name="status_to" class="form-control input-sm">{foreach ['approved','disbursed','repaying','settled','rejected','cancelled','written_off'] as $s}<option value="{$s}" {if $l.status==$s}selected{/if}>{$s|replace:'_':' '}</option>{/foreach}</select>
<input name="note" class="form-control input-sm" placeholder="note">
<button name="loanStatus" class="btn btn-primary btn-sm">Set status</button>
<button name="rebuildSchedule" class="btn btn-default btn-sm" onclick="return confirm('Rebuild the unpaid part of the schedule? Instalments already recovered are untouched.')">Rebuild the schedule</button>
</form>
<p class="text-muted"><small>A loan must be approved before it can be disbursed, and only a disbursed or repaying loan is recovered by a payroll run.</small></p>
</div>

<div class="panel"><h4>Repayment schedule</h4>
<table class="table table-condensed"><thead><tr><th>#</th><th>Period</th><th>Due</th><th>Paid</th><th>Outstanding</th><th>Status</th><th>Taken on payslip</th><th></th></tr></thead><tbody>
{foreach $l.schedule as $s}<tr class="{if $s.status=='paid'}success{elseif $s.status=='part'}warning{elseif $s.status=='deferred'}text-muted{/if}">
<td>{$s.seq}</td><td>{$s.period}</td><td>{displayPrice price=$s.due_amount}</td><td>{displayPrice price=$s.paid_amount}</td>
<td>{displayPrice price=$s.due_amount-$s.paid_amount}</td><td>{$s.status}</td><td>{if $s.id_pulse_pr_payslip}#{$s.id_pulse_pr_payslip}{else}—{/if}</td>
<td>{if $s.status=='due'}<form method="post" class="inline"><input type="hidden" name="id_loan" value="{$l.id_pulse_pr_loan}"><input type="hidden" name="id_loan_a" value="{$l.id_pulse_pr_loan}"><input type="hidden" name="id_schedule" value="{$s.id_pulse_pr_loan_schedule}">
<button name="deferInstalment" class="btn btn-xs btn-link" onclick="return confirm('Defer this instalment? It will not be recovered until you set it back to due.')">Defer</button></form>{/if}</td></tr>
{foreachelse}<tr><td colspan="8"><em>No schedule.</em></td></tr>{/foreach}
</tbody></table>
</div></div>
