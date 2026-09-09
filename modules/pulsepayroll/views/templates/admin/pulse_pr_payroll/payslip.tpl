<div class="pulse-pr">
{if !$doc}<div class="alert alert-danger">Payslip not found.</div>
{else}
<div class="panel pr-payslip">
<h3><i class="icon-file-text-o"></i> Payslip &mdash; {$doc.payslip.employee_name}, {$doc.payslip.period}
<a class="btn btn-xs btn-default pull-right" href="{$self_url}&id_run={$doc.payslip.id_pulse_pr_run}">Back to the run</a></h3>

<div class="row pr-slip-head">
<div class="col-md-4"><table class="table table-condensed">
<tr><td>Staff number</td><td><strong>{$doc.payslip.staff_no}</strong></td></tr>
<tr><td>Department</td><td>{$doc.payslip.department}{if $doc.payslip.position} &mdash; {$doc.payslip.position}{/if}</td></tr>
<tr><td>Grade</td><td>{$doc.payslip.grade|default:'—'}</td></tr>
<tr><td>Run</td><td>{$doc.payslip.run_no} ({$doc.payslip.run_type|replace:'_':' '}, {$doc.payslip.run_status})</td></tr>
<tr><td>Pay date</td><td>{$doc.payslip.pay_date}</td></tr>
<tr><td>Days paid</td><td>{$doc.payslip.days_paid|floatval} of {$doc.payslip.days_in_period|floatval}</td></tr>
</table></div>
<div class="col-md-4"><table class="table table-condensed">
<tr><td>TIN</td><td>{$doc.employee.tin|default:'—'}</td></tr>
<tr><td>Tax authority</td><td>{$doc.employee.tax_state|default:'—'}</td></tr>
<tr><td>RSA PIN / PFA</td><td>{$doc.employee.rsa_pin|default:'—'}{if $doc.employee.pfa} &mdash; {$doc.employee.pfa}{/if}</td></tr>
<tr><td>NHF</td><td>{if $doc.payslip.nhf > 0}Deducted &mdash; consent recorded {$doc.payslip.nhf_consent_date}{else}<span class="text-muted">Not deducted &mdash; no consent on file (NHF is voluntary)</span>{/if}</td></tr>
<tr><td>Bank</td><td>{$doc.payslip.bank_name|default:'—'} {$doc.payslip.account_no|default:''}</td></tr>
<tr><td>Paid by</td><td>{$doc.payslip.pay_method}</td></tr>
</table></div>
<div class="col-md-4"><table class="table table-condensed">
<tr><td>Annualisation</td><td>{$doc.payslip.annualisation_periods} period(s)</td></tr>
<tr><td>Chargeable income</td><td>{displayPrice price=$doc.payslip.chargeable_income}</td></tr>
<tr><td>Reliefs allowed</td><td>{displayPrice price=$doc.payslip.reliefs_total}</td></tr>
<tr><td>Annual tax charge</td><td>{displayPrice price=$doc.payslip.paye_annual}</td></tr>
<tr><td>Pension base (BHT)</td><td>{displayPrice price=$doc.payslip.bht}</td></tr>
<tr><td>Last viewed</td><td>{if $doc.payslip.viewed_at}{$doc.payslip.viewed_at}{else}<span class="text-muted">never</span>{/if}</td></tr>
</table></div>
</div>

<div class="row">
<div class="col-md-6"><h4>Earnings</h4>
<table class="table table-condensed"><thead><tr><th>Element</th><th class="text-right">Amount</th><th class="text-right">Year to date</th><th>Note</th></tr></thead><tbody>
{foreach $doc.earnings as $l}<tr><td>{$l.element_name}{if !$l.taxable} <small class="text-muted">(not taxable)</small>{/if}{if $l.pensionable} <small class="text-muted">(pensionable)</small>{/if}</td>
<td class="text-right">{displayPrice price=$l.amount}</td><td class="text-right text-muted">{if isset($doc.ytd_by_code[$l.element_code])}{displayPrice price=$doc.ytd_by_code[$l.element_code]}{/if}</td><td><small>{$l.note}</small></td></tr>{/foreach}
<tr class="active"><td><strong>Gross pay</strong></td><td class="text-right"><strong>{displayPrice price=$doc.payslip.gross}</strong></td><td class="text-right"><strong>{displayPrice price=$doc.ytd.gross}</strong></td><td></td></tr>
</tbody></table></div>

<div class="col-md-6"><h4>Deductions</h4>
<table class="table table-condensed"><thead><tr><th>Element</th><th class="text-right">Amount</th><th class="text-right">Year to date</th><th>Note</th></tr></thead><tbody>
{foreach $doc.deductions as $l}<tr><td>{$l.element_name}</td><td class="text-right">{displayPrice price=$l.amount}</td>
<td class="text-right text-muted">{if isset($doc.ytd_by_code[$l.element_code])}{displayPrice price=$doc.ytd_by_code[$l.element_code]}{/if}</td><td><small>{$l.note}</small></td></tr>{/foreach}
<tr class="active"><td><strong>Total deductions</strong></td><td class="text-right"><strong>{displayPrice price=$doc.payslip.total_deductions}</strong></td><td class="text-right"><strong>{displayPrice price=$doc.ytd.deductions}</strong></td><td></td></tr>
<tr class="success"><td><strong>NET PAY</strong></td><td class="text-right"><strong>{displayPrice price=$doc.payslip.net_pay}</strong></td><td class="text-right"><strong>{displayPrice price=$doc.ytd.net}</strong></td><td></td></tr>
</tbody></table></div>
</div>

<div class="row">
<div class="col-md-6"><h4>Employer contributions <small class="text-muted">(cost to the hotel, not deducted from you)</small></h4>
<table class="table table-condensed"><tbody>
{foreach $doc.employer as $l}<tr><td>{$l.element_name}</td><td class="text-right">{displayPrice price=$l.amount}</td><td><small class="text-muted">{$l.note}</small></td></tr>{/foreach}
<tr class="active"><td><strong>Total employer cost on top of gross</strong></td><td class="text-right"><strong>{displayPrice price=$doc.payslip.employer_cost}</strong></td><td></td></tr>
</tbody></table></div>
<div class="col-md-6">
{if $doc.information}<h4>For information</h4><table class="table table-condensed"><tbody>
{foreach $doc.information as $l}<tr class="warning"><td>{$l.element_name}</td><td class="text-right">{if $l.amount}{displayPrice price=$l.amount}{/if}</td><td><small>{$l.note}</small></td></tr>{/foreach}
</tbody></table>{/if}
{if $doc.tronc}<h4>Service charge this period</h4><table class="table table-condensed"><thead><tr><th>Pool</th><th>Basis</th><th>Points/hours</th><th>Share</th><th class="text-right">Amount</th></tr></thead><tbody>
{foreach $doc.tronc as $t}<tr><td>{$t.pool_no}</td><td>{$t.basis}</td><td>{if $t.basis=='hours'}{$t.hours|floatval}h{else}{$t.points|floatval}pt{/if} &times; {$t.dept_weight}</td><td>{$t.share_pct}%</td><td class="text-right">{displayPrice price=$t.amount}</td></tr>{/foreach}
</tbody></table>{/if}
{if $doc.loans.total > 0}<h4>Loan position</h4><p>Loans outstanding {displayPrice price=$doc.loans.loan}{if $doc.loans.arrears > 0}, arrears {displayPrice price=$doc.loans.arrears}{/if}.</p>{/if}
</div>
</div>

<form method="post" class="form-inline"><input type="hidden" name="id_payslip_a" value="{$doc.payslip.id_pulse_pr_payslip}">
{if $doc.payslip.run_status=='approved' || $doc.payslip.run_status=='paid' || $doc.payslip.run_status=='posted'}
<button name="emailSlip" class="btn btn-default btn-sm">Email this payslip to {$doc.employee.email|default:'the employee'}</button>
<button name="reissueSlip" class="btn btn-link btn-sm" onclick="return confirm('Reissue the download link? The existing link stops working immediately.')">Reissue the download link</button>
{/if}
<button type="button" class="btn btn-default btn-sm" onclick="window.print()">Print</button>
</form>
<p class="text-muted"><small>The employee opens this payslip from a tokenised link and must enter their payslip PIN. The link is never a guessable URL and it expires.</small></p>
</div>
{/if}
</div>
