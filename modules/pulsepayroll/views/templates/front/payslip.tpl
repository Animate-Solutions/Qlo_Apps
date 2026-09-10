<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Payslip{if isset($period)} — {$period}{/if}</title>
<link rel="stylesheet" href="{$css}"></head>
<body class="pr-slip-body">
<div class="pr-slip-wrap">
<header><h1>{$hotel|escape:'html':'UTF-8'}</h1>{if isset($period)}<p class="pr-sub">Payslip for {$period}{if isset($name)} — {$name|escape:'html':'UTF-8'}{/if}</p>{/if}</header>

{if $error}<div class="pr-msg pr-err">{$error|escape:'html':'UTF-8'}</div>{/if}

{if !$unlocked}
  {if !$error || isset($period)}
  <form method="post" class="pr-pin">
    <p>This payslip is confidential. Enter your payslip PIN to open it.</p>
    <input type="password" name="pin" inputmode="numeric" autocomplete="off" placeholder="PIN" required autofocus>
    <button name="unlock" value="1">Open my payslip</button>
    <p class="pr-hint">Your PIN is the last four characters of your staff number unless you have changed it. If you have forgotten it, ask the payroll office — they can reset it, but they cannot tell you what it is.</p>
  </form>
  {/if}
{else}
<div class="pr-slip">
<table class="pr-meta"><tbody>
<tr><th>Staff number</th><td>{$doc.payslip.staff_no|escape:'html':'UTF-8'}</td><th>Period</th><td>{$doc.payslip.period}</td></tr>
<tr><th>Department</th><td>{$doc.payslip.department|escape:'html':'UTF-8'}</td><th>Pay date</th><td>{$doc.payslip.pay_date}</td></tr>
<tr><th>Position</th><td>{$doc.payslip.position|escape:'html':'UTF-8'}</td><th>Days paid</th><td>{$doc.payslip.days_paid|floatval} of {$doc.payslip.days_in_period|floatval}</td></tr>
<tr><th>Paid by</th><td>{$doc.payslip.pay_method}{if $doc.payslip.account_no} — {$doc.payslip.bank_name|escape:'html':'UTF-8'} ****{$doc.payslip.account_no|truncate:4:"":true}{/if}</td><th>Currency</th><td>{$currency}</td></tr>
</tbody></table>

<div class="pr-cols">
<div class="pr-col"><h2>Earnings</h2><table><tbody>
{foreach $doc.earnings as $l}<tr><td>{$l.element_name|escape:'html':'UTF-8'}{if $l.note}<small>{$l.note|escape:'html':'UTF-8'}</small>{/if}</td><td class="pr-amt">{$l.amount|number_format:2}</td><td class="pr-ytd">{if isset($doc.ytd_by_code[$l.element_code])}{$doc.ytd_by_code[$l.element_code]|number_format:2}{/if}</td></tr>{/foreach}
<tr class="pr-tot"><td>Gross pay</td><td class="pr-amt">{$doc.payslip.gross|number_format:2}</td><td class="pr-ytd">{$doc.ytd.gross|number_format:2}</td></tr>
</tbody></table></div>

<div class="pr-col"><h2>Deductions</h2><table><tbody>
{foreach $doc.deductions as $l}<tr><td>{$l.element_name|escape:'html':'UTF-8'}{if $l.note}<small>{$l.note|escape:'html':'UTF-8'}</small>{/if}</td><td class="pr-amt">{$l.amount|number_format:2}</td><td class="pr-ytd">{if isset($doc.ytd_by_code[$l.element_code])}{$doc.ytd_by_code[$l.element_code]|number_format:2}{/if}</td></tr>
{foreachelse}<tr><td>Nothing deducted</td><td class="pr-amt">0.00</td><td class="pr-ytd"></td></tr>{/foreach}
<tr class="pr-tot"><td>Total deductions</td><td class="pr-amt">{$doc.payslip.total_deductions|number_format:2}</td><td class="pr-ytd">{$doc.ytd.deductions|number_format:2}</td></tr>
</tbody></table></div>
</div>

<div class="pr-net"><span>Net pay</span><strong>{$currency} {$doc.payslip.net_pay|number_format:2}</strong></div>

{if $doc.employer}<h2>Paid by the hotel on your behalf</h2><table class="pr-wide"><tbody>
{foreach $doc.employer as $l}<tr><td>{$l.element_name|escape:'html':'UTF-8'}</td><td class="pr-amt">{$l.amount|number_format:2}</td></tr>{/foreach}
</tbody></table>{/if}

{if $doc.information}<h2>For your information</h2><table class="pr-wide"><tbody>
{foreach $doc.information as $l}<tr><td>{$l.element_name|escape:'html':'UTF-8'}<small>{$l.note|escape:'html':'UTF-8'}</small></td><td class="pr-amt">{if $l.amount}{$l.amount|number_format:2}{/if}</td></tr>{/foreach}
</tbody></table>{/if}

{if $doc.tronc}<h2>Service charge</h2><table class="pr-wide"><tbody>
{foreach $doc.tronc as $t}<tr><td>{$t.period} — {if $t.basis=='hours'}{$t.hours|floatval} hours{else}{$t.points|floatval} points{/if} at a department weighting of {$t.dept_weight|floatval}, a {$t.share_pct}% share</td><td class="pr-amt">{$t.amount|number_format:2}</td></tr>{/foreach}
</tbody></table>{/if}

{if $doc.loans.total > 0}<h2>Loans</h2><table class="pr-wide"><tbody>
<tr><td>Loan balance outstanding</td><td class="pr-amt">{$doc.loans.loan|number_format:2}</td></tr>
{if $doc.loans.arrears > 0}<tr><td>Arrears carried forward</td><td class="pr-amt">{$doc.loans.arrears|number_format:2}</td></tr>{/if}
</tbody></table>{/if}

<h2>Year to date {$doc.year}</h2><table class="pr-wide"><tbody>
<tr><td>Periods paid</td><td class="pr-amt">{$doc.ytd.periods}</td></tr>
<tr><td>Gross</td><td class="pr-amt">{$doc.ytd.gross|number_format:2}</td></tr>
<tr><td>PAYE</td><td class="pr-amt">{$doc.ytd.paye|number_format:2}</td></tr>
<tr><td>Pension (your contribution)</td><td class="pr-amt">{$doc.ytd.pension_ee|number_format:2}</td></tr>
<tr><td>NHF</td><td class="pr-amt">{$doc.ytd.nhf|number_format:2}</td></tr>
<tr><td>Net received</td><td class="pr-amt">{$doc.ytd.net|number_format:2}</td></tr>
</tbody></table>

<p class="pr-foot">Tax is worked out on an annual charge of {$doc.payslip.paye_annual|number_format:2} on a chargeable income of {$doc.payslip.chargeable_income|number_format:2}, spread over {$doc.payslip.annualisation_periods} pay period(s).
{if $doc.payslip.nhf > 0}NHF is deducted under the consent you gave on {$doc.payslip.nhf_consent_date}.{else}NHF is voluntary and is not being deducted, because no consent is recorded for you.{/if}
Queries go to the payroll office. Please do not forward this link — it is personal to you.</p>
<p class="pr-print-btn"><button onclick="window.print()">Print or save as PDF</button></p>
</div>
{/if}
</div>
</body></html>
